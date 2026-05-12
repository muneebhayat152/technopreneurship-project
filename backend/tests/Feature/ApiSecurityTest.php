<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_route_is_rate_limited(): void
    {
        User::create([
            'name' => 'Rate Limit User',
            'email' => 'ratelimit@test.local',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'ratelimit@test.local',
                'password' => 'wrong-password',
            ])->assertStatus(401);
        }

        $this->postJson('/api/auth/login', [
            'email' => 'ratelimit@test.local',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_logout_revokes_current_access_token(): void
    {
        $company = Company::create([
            'name' => 'Token Co',
            'email' => 'token@test.local',
            'subscription' => 'premium',
            'is_active' => true,
        ]);

        $user = User::create([
            'name' => 'Token User',
            'email' => 'token-user@test.local',
            'password' => Hash::make('password123'),
            'company_id' => $company->id,
            'role' => 'admin',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_free_subscription_cannot_access_premium_routes(): void
    {
        $company = Company::create([
            'name' => 'Free Co',
            'email' => 'free@test.local',
            'subscription' => 'free',
            'is_active' => true,
        ]);

        $user = User::create([
            'name' => 'Free Admin',
            'email' => 'free-admin@test.local',
            'password' => Hash::make('password123'),
            'company_id' => $company->id,
            'role' => 'admin',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/alerts')
            ->assertStatus(402)
            ->assertJson([
                'success' => false,
                'upgrade_required' => true,
            ]);
    }

    public function test_register_creates_organization_pending_owner_approval_on_free_plan(): void
    {
        $this->postJson('/api/auth/register', [
            'company_name' => 'Proposal Align Co',
            'company_email' => 'proposal-align-co@test.local',
            'industry' => 'Professional Services',
            'country' => 'United Kingdom',
            'name' => 'Primary Admin',
            'email' => 'primary-admin@test.local',
            'password' => 'password123',
        ])
            ->assertStatus(201)
            ->assertJsonPath('company.subscription', 'free')
            ->assertJsonPath('company.registration_status', 'pending')
            ->assertJsonMissingPath('token');

        $this->assertDatabaseHas('companies', [
            'email' => 'proposal-align-co@test.local',
            'subscription' => 'free',
            'registration_status' => 'pending',
            'is_active' => false,
        ]);
    }
}

