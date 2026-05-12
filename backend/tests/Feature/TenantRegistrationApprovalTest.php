<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantRegistrationApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_pending_free_organization_without_token(): void
    {
        $this->postJson('/api/auth/register', [
            'company_name' => 'Pending Co',
            'company_email' => 'pending-co@test.local',
            'industry' => 'Retail',
            'country' => 'Pakistan',
            'name' => 'First Admin',
            'email' => 'first-admin@test.local',
            'password' => 'password123',
        ])
            ->assertStatus(201)
            ->assertJsonPath('pending_owner_approval', true)
            ->assertJsonPath('company.subscription', 'free')
            ->assertJsonPath('company.registration_status', Company::REGISTRATION_PENDING)
            ->assertJsonMissingPath('token');

        $this->assertDatabaseHas('companies', [
            'email' => 'pending-co@test.local',
            'subscription' => 'free',
            'registration_status' => Company::REGISTRATION_PENDING,
            'is_active' => false,
        ]);
    }

    public function test_login_blocked_until_owner_approves(): void
    {
        $this->postJson('/api/auth/register', [
            'company_name' => 'Wait Co',
            'company_email' => 'wait-co@test.local',
            'industry' => 'Retail',
            'country' => 'Pakistan',
            'name' => 'Admin Wait',
            'email' => 'admin-wait@test.local',
            'password' => 'password123',
        ])->assertStatus(201);

        $this->postJson('/api/auth/login', [
            'email' => 'admin-wait@test.local',
            'password' => 'password123',
        ])
            ->assertStatus(403)
            ->assertJsonPath('registration_status', Company::REGISTRATION_PENDING);
    }

    public function test_super_admin_can_approve_as_premium_and_admin_can_login(): void
    {
        $this->postJson('/api/auth/register', [
            'company_name' => 'Approve Co',
            'company_email' => 'approve-co@test.local',
            'industry' => 'Retail',
            'country' => 'Pakistan',
            'name' => 'Admin Approve',
            'email' => 'admin-approve@test.local',
            'password' => 'password123',
        ])->assertStatus(201);

        $company = Company::where('email', 'approve-co@test.local')->firstOrFail();

        $super = User::create([
            'name' => 'Super',
            'email' => 'super-approve@test.local',
            'password' => Hash::make('password123'),
            'company_id' => null,
            'role' => 'super_admin',
        ]);

        Sanctum::actingAs($super);

        $this->postJson("/api/companies/{$company->id}/approve-registration", [
            'subscription' => 'premium',
        ])
            ->assertOk()
            ->assertJsonPath('company.registration_status', Company::REGISTRATION_ACTIVE)
            ->assertJsonPath('company.subscription', 'premium');

        $this->postJson('/api/auth/login', [
            'email' => 'admin-approve@test.local',
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('company.subscription', 'premium')
            ->assertJsonPath('company.registration_status', Company::REGISTRATION_ACTIVE);
    }

    public function test_super_admin_can_reject_and_admin_cannot_login(): void
    {
        $this->postJson('/api/auth/register', [
            'company_name' => 'Reject Co',
            'company_email' => 'reject-co@test.local',
            'industry' => 'Retail',
            'country' => 'Pakistan',
            'name' => 'Admin Reject',
            'email' => 'admin-reject@test.local',
            'password' => 'password123',
        ])->assertStatus(201);

        $company = Company::where('email', 'reject-co@test.local')->firstOrFail();

        $super = User::create([
            'name' => 'Super2',
            'email' => 'super-reject@test.local',
            'password' => Hash::make('password123'),
            'company_id' => null,
            'role' => 'super_admin',
        ]);

        Sanctum::actingAs($super);

        $this->postJson("/api/companies/{$company->id}/reject-registration", [
            'note' => 'Not a fit for pilot.',
        ])->assertOk();

        $this->postJson('/api/auth/login', [
            'email' => 'admin-reject@test.local',
            'password' => 'password123',
        ])
            ->assertStatus(403)
            ->assertJsonPath('registration_status', Company::REGISTRATION_REJECTED);
    }
}
