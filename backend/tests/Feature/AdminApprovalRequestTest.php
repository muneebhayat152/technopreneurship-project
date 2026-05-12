<?php

namespace Tests\Feature;

use App\Models\AdminApprovalRequest;
use App\Models\Company;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Notifications\AdminApprovalDecisionAlert;
use App\Notifications\NewAdminApprovalRequestAlert;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminApprovalRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_tenant_admin_cannot_update_subscription_directly(): void
    {
        $company = Company::create([
            'name' => 'Acme',
            'email' => 'acme@test.local',
            'subscription' => 'free',
            'is_active' => true,
        ]);

        $admin = User::create([
            'name' => 'Org Admin',
            'email' => 'org-admin@test.local',
            'password' => Hash::make('password123'),
            'company_id' => $company->id,
            'role' => 'admin',
        ]);

        Sanctum::actingAs($admin);

        $this->putJson("/api/companies/{$company->id}/subscription", [
            'subscription' => 'premium',
        ])->assertStatus(403);
    }

    public function test_tenant_admin_subscription_request_is_applied_when_super_admin_approves(): void
    {
        $company = Company::create([
            'name' => 'Beta Co',
            'email' => 'beta@test.local',
            'subscription' => 'free',
            'is_active' => true,
        ]);

        $tenantAdmin = User::create([
            'name' => 'Tenant Admin',
            'email' => 'tenant-admin-approval@test.local',
            'password' => Hash::make('password123'),
            'company_id' => $company->id,
            'role' => 'admin',
        ]);

        $super = User::create([
            'name' => 'Super',
            'email' => 'super-approval@test.local',
            'password' => Hash::make('password123'),
            'company_id' => null,
            'role' => 'super_admin',
        ]);

        Sanctum::actingAs($tenantAdmin);

        $this->postJson('/api/admin/approval-requests', [
            'type' => 'subscription_change',
            'payload' => ['subscription' => 'premium'],
        ])->assertCreated();

        Notification::assertSentTo($super, NewAdminApprovalRequestAlert::class);

        $row = AdminApprovalRequest::query()->first();
        $this->assertNotNull($row);
        $this->assertSame('pending', $row->status);

        Sanctum::actingAs($super);

        $this->postJson("/api/admin/approval-requests/{$row->id}/approve", [])
            ->assertOk();

        Notification::assertSentTo($tenantAdmin, AdminApprovalDecisionAlert::class);

        $company->refresh();
        $this->assertSame('premium', $company->subscription);

        $row->refresh();
        $this->assertSame('approved', $row->status);
    }

    public function test_tenant_admin_complaint_status_change_queues_until_super_admin_approves(): void
    {
        $company = Company::create([
            'name' => 'Gamma Co',
            'email' => 'gamma@test.local',
            'subscription' => 'free',
            'is_active' => true,
        ]);

        $customer = User::create([
            'name' => 'Customer',
            'email' => 'customer-gamma@test.local',
            'password' => Hash::make('password123'),
            'company_id' => $company->id,
            'role' => 'user',
        ]);

        $tenantAdmin = User::create([
            'name' => 'Tenant Admin Gamma',
            'email' => 'tenant-gamma@test.local',
            'password' => Hash::make('password123'),
            'company_id' => $company->id,
            'role' => 'admin',
        ]);

        $super = User::create([
            'name' => 'Super Gamma',
            'email' => 'super-gamma@test.local',
            'password' => Hash::make('password123'),
            'company_id' => null,
            'role' => 'super_admin',
        ]);

        $complaint = Complaint::create([
            'company_id' => $company->id,
            'user_id' => $customer->id,
            'complaint_text' => 'Late delivery',
            'sentiment' => 'negative',
            'category' => 'delivery',
            'status' => 'open',
            'priority' => 'high',
        ]);

        Sanctum::actingAs($tenantAdmin);

        $this->putJson("/api/complaints/{$complaint->id}/status", [
            'status' => 'in_progress',
        ])->assertStatus(202);

        Notification::assertSentTo($super, NewAdminApprovalRequestAlert::class);

        $this->assertSame('open', $complaint->fresh()->status);

        $row = AdminApprovalRequest::query()->where('type', 'complaint_status_change')->first();
        $this->assertNotNull($row);
        $this->assertSame('pending', $row->status);

        Sanctum::actingAs($super);

        $this->postJson("/api/admin/approval-requests/{$row->id}/approve", [])
            ->assertOk();

        $complaint->refresh();
        $this->assertSame('in_progress', $complaint->status);

        Notification::assertSentTo($tenantAdmin, AdminApprovalDecisionAlert::class);
    }

    public function test_super_admin_applies_complaint_status_without_queue(): void
    {
        $company = Company::create([
            'name' => 'Delta Co',
            'email' => 'delta@test.local',
            'subscription' => 'free',
            'is_active' => true,
        ]);

        $customer = User::create([
            'name' => 'Customer Delta',
            'email' => 'customer-delta@test.local',
            'password' => Hash::make('password123'),
            'company_id' => $company->id,
            'role' => 'user',
        ]);

        $super = User::create([
            'name' => 'Super Delta',
            'email' => 'super-delta@test.local',
            'password' => Hash::make('password123'),
            'company_id' => null,
            'role' => 'super_admin',
        ]);

        $complaint = Complaint::create([
            'company_id' => $company->id,
            'user_id' => $customer->id,
            'complaint_text' => 'Broken item',
            'sentiment' => 'negative',
            'category' => 'general',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        Sanctum::actingAs($super);

        $this->putJson("/api/complaints/{$complaint->id}/status", [
            'status' => 'resolved',
        ])->assertOk();

        $this->assertSame('resolved', $complaint->fresh()->status);
        $this->assertSame(0, AdminApprovalRequest::query()->count());
    }
}
