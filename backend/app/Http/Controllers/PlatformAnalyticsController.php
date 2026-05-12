<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PlatformAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $companiesActive = Company::query()->where('is_active', true)->count();
        $companiesInactive = Company::query()->where('is_active', false)->count();
        $companiesPremium = Company::query()->where('subscription', 'premium')->count();

        $complaintsTotal = Complaint::query()->count();
        $complaints24h = Complaint::query()->where('created_at', '>=', now()->subDay())->count();
        $complaints7d = Complaint::query()->where('created_at', '>=', now()->subDays(7))->count();
        $complaints30d = Complaint::query()->where('created_at', '>=', now()->subDays(30))->count();

        $usersTotal = User::query()->count();
        $usersSoftDeleted = User::onlyTrashed()->count();

        $failedJobs = 0;
        if (Schema::hasTable('failed_jobs')) {
            $failedJobs = (int) DB::table('failed_jobs')->count();
        }

        $auditEvents24h = AuditLog::query()->where('created_at', '>=', now()->subDay())->count();

        return response()->json([
            'success' => true,
            'tenants' => [
                'active' => $companiesActive,
                'inactive' => $companiesInactive,
                'premium_subscriptions' => $companiesPremium,
                'total' => $companiesActive + $companiesInactive,
            ],
            'complaints' => [
                'total' => $complaintsTotal,
                'last_24_hours' => $complaints24h,
                'last_7_days' => $complaints7d,
                'last_30_days' => $complaints30d,
            ],
            'users' => [
                'total_accounts' => $usersTotal,
                'soft_deleted_accounts' => $usersSoftDeleted,
            ],
            'reliability' => [
                'failed_queue_jobs' => $failedJobs,
                'note' => 'Failed jobs usually mean background clustering hit an error. Check logs and re-run when fixed.',
            ],
            'governance' => [
                'audit_events_last_24_hours' => $auditEvents24h,
                'note' => 'Security-sensitive actions are written to the audit log.',
            ],
        ]);
    }
}
