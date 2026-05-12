<?php

namespace App\Services;

use App\Models\AdminApprovalRequest;
use App\Models\User;
use App\Notifications\AdminApprovalDecisionAlert;
use App\Notifications\NewAdminApprovalRequestAlert;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * In-app (database) + email notifications for the admin approval workflow.
 */
class ApprovalRequestNotifier
{
    public static function notifySuperAdminsOfNewRequest(AdminApprovalRequest $row): void
    {
        $row->loadMissing('company:id,name', 'requester:id,name,email');

        $supers = User::query()
            ->where('role', 'super_admin')
            ->get();

        if ($supers->isEmpty()) {
            return;
        }

        try {
            Notification::send($supers, new NewAdminApprovalRequestAlert($row));
        } catch (\Throwable $e) {
            Log::warning('approval_request_notify_super_failed', [
                'request_id' => $row->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function notifyRequesterResolved(
        AdminApprovalRequest $row,
        string $decision,
        ?string $reviewerNote
    ): void {
        $row->loadMissing('requester:id,email,name');
        $requester = $row->requester;
        if (! $requester) {
            return;
        }

        try {
            $requester->notify(new AdminApprovalDecisionAlert($row, $decision, $reviewerNote));
        } catch (\Throwable $e) {
            Log::warning('approval_request_notify_requester_failed', [
                'request_id' => $row->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
