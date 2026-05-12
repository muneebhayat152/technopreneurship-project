<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\Company;
use App\Models\User;
use App\Notifications\NewComplaintSubmittedNotification;
use App\Notifications\NewTenantOrganizationNotification;
use Illuminate\Support\Facades\Notification;

/**
 * In-app + mail alerts for tenant administrators (not platform super admins — they do not receive complaint content).
 */
class PlatformActivityNotifier
{
    public static function notifyNewOrganization(Company $company, User $admin): void
    {
        $supers = User::query()->where('role', 'super_admin')->get();
        if ($supers->isEmpty()) {
            return;
        }

        Notification::send($supers, new NewTenantOrganizationNotification($company, $admin));
    }

    public static function notifyComplaintSubmitted(Complaint $complaint): void
    {
        $complaint->loadMissing('user:id,name,email', 'company:id,name');

        $submitterId = (int) $complaint->user_id;
        $companyId = (int) $complaint->company_id;

        $recipients = User::query()
            ->where('company_id', $companyId)
            ->where('role', 'admin')
            ->where('id', '!=', $submitterId)
            ->get();
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new NewComplaintSubmittedNotification($complaint));
    }
}
