<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\Company;
use App\Models\User;
use App\Notifications\NewComplaintSubmittedNotification;
use App\Notifications\NewTenantOrganizationNotification;
use Illuminate\Support\Facades\Notification;

/**
 * In-app + mail alerts for platform owners (super admins) and tenant administrators.
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
            ->where(function ($q) use ($companyId, $submitterId) {
                $q->where(function ($q2) use ($companyId, $submitterId) {
                    $q2->where('company_id', $companyId)
                        ->where('role', 'admin')
                        ->where('id', '!=', $submitterId);
                })->orWhere(function ($q3) use ($submitterId) {
                    $q3->where('role', 'super_admin')
                        ->where('id', '!=', $submitterId);
                });
            })
            ->get();
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new NewComplaintSubmittedNotification($complaint));
    }
}
