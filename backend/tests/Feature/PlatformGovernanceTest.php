<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_admin_cannot_view_platform_analytics(): void
    {
        $company = Company::create([
            'name' => 'Tenant Co',
            'email' => 'tenant@test.local',
            'subscription' => 'premium',
            'is_active' => true,
        ]);

        $admin = User::create([
            'name' => 'Tenant Admin',
            'email' => 'tenant-admin@test.local',
            'password' => Hash::make('password123'),
            'company_id' => $company->id,
            'role' => 'admin',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/analytics')->assertStatus(403);
        $this->getJson('/api/admin/audit-logs')->assertStatus(403);
    }

    public function test_super_admin_can_view_platform_analytics_and_audit_logs(): void
    {
        $super = User::create([
            'name' => 'Platform Owner',
            'email' => 'super@test.local',
            'password' => Hash::make('password123'),
            'company_id' => null,
            'role' => 'super_admin',
        ]);

        Sanctum::actingAs($super);

        $this->getJson('/api/admin/analytics')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'tenants' => ['active', 'inactive', 'premium_subscriptions', 'total'],
                'complaints' => ['total', 'last_24_hours', 'last_7_days', 'last_30_days'],
                'users' => ['total_accounts', 'soft_deleted_accounts'],
                'reliability' => ['failed_queue_jobs', 'note'],
                'governance' => ['audit_events_last_24_hours', 'note'],
            ]);

        $this->getJson('/api/admin/audit-logs')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['logs', 'pagination']);
    }
}
