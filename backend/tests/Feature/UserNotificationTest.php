<?php

namespace Tests\Feature;

use App\Models\AdminApprovalRequest;
use App\Models\Company;
use App\Models\User;
use App\Notifications\AdminApprovalDecisionAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_notifications_and_mark_read(): void
    {
        $company = Company::create([
            'name' => 'Notify Co',
            'email' => 'notify-co@test.local',
            'subscription' => 'free',
            'is_active' => true,
        ]);

        $admin = User::create([
            'name' => 'Notify Admin',
            'email' => 'notify-admin@test.local',
            'password' => Hash::make('password123'),
            'company_id' => $company->id,
            'role' => 'admin',
        ]);

        $row = AdminApprovalRequest::create([
            'company_id' => $company->id,
            'requester_id' => $admin->id,
            'type' => 'subscription_change',
            'payload' => ['subscription' => 'premium'],
            'status' => 'approved',
        ]);

        $admin->notify(new AdminApprovalDecisionAlert($row, 'approved', 'Looks good'));

        Sanctum::actingAs($admin);

        $list = $this->getJson('/api/user/notifications')->assertOk();
        $list->assertJsonPath('unread_count', 1);
        $id = $list->json('notifications.0.id');
        $this->assertNotNull($id);

        $this->postJson("/api/user/notifications/{$id}/read")->assertOk()->assertJsonPath('unread_count', 0);

        $this->getJson('/api/user/notifications')->assertOk()->assertJsonPath('unread_count', 0);
    }
}
