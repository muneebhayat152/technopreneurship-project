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

    public function test_tenant_admin_cannot_view_platform_audit_logs(): void
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

        $this->getJson('/api/admin/audit-logs')->assertStatus(403);
    }

    public function test_super_admin_can_view_audit_logs(): void
    {
        $super = User::create([
            'name' => 'Platform Owner',
            'email' => 'super@test.local',
            'password' => Hash::make('password123'),
            'company_id' => null,
            'role' => 'super_admin',
        ]);

        Sanctum::actingAs($super);

        $this->getJson('/api/admin/audit-logs')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['logs', 'pagination']);
    }
}
