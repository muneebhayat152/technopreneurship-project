<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\User;

class CompanyController extends Controller
{
    /** Must match PlatformOwnersSeeder platform tenant email — cannot delete from UI. */
    private const RESERVED_OWNER_TENANT_EMAIL = 'owners@ai-complaint-doctor.platform';

    /**
     * 🔐 Check Super Admin
     */
    private function checkSuperAdmin($user)
    {
        if (!$user || $user->role !== 'super_admin') {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }
        return null;
    }

    /**
     * 🏢 GET ALL COMPANIES (SUPER ADMIN ONLY)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($response = $this->checkSuperAdmin($user)) return $response;

        $companies = Company::latest()->get();

        return response()->json([
            'success' => true,
            'companies' => $companies
        ]);
    }

    /**
     * 🔄 TOGGLE COMPANY STATUS (ACTIVE / INACTIVE)
     */
    public function toggleStatus(Request $request, $id)
    {
        $user = $request->user();

        if ($response = $this->checkSuperAdmin($user)) return $response;

        $company = Company::findOrFail($id);

        $wasActive = (bool) $company->is_active;
        $company->is_active = ! $company->is_active;
        $company->save();

        AuditLogger::record(
            $request,
            $user,
            $company->is_active ? 'company.activated' : 'company.deactivated',
            'company',
            (int) $company->id,
            [
                'company_name' => $company->name,
                'was_active' => $wasActive,
                'is_active' => (bool) $company->is_active,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Company status updated',
            'company' => $company
        ]);
    }

    /**
     * Set subscription to free or premium (direct apply).
     * Only platform super administrators may call this; tenant admins use approval requests.
     */
    /**
     * Route parameters arrive as strings; avoid strict int type-hints on the signature.
     */
    public function updateSubscription(Request $request, string|int $id)
    {
        $user = $request->user();

        $request->validate([
            'subscription' => 'required|in:free,premium',
        ]);

        $company = Company::findOrFail((int) $id);

        if ($user->role !== 'super_admin') {
            return response()->json([
                'message' => 'Only the platform super administrator may change organization plans directly. Organization admins must submit a plan-change request for approval.',
            ], 403);
        }

        $from = $company->subscription;
        $company->subscription = $request->subscription;
        $company->save();

        AuditLogger::record(
            $request,
            $user,
            'company.subscription_changed',
            'company',
            (int) $company->id,
            [
                'company_name' => $company->name,
                'from' => $from,
                'to' => $company->subscription,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Subscription updated',
            'company' => $company,
        ]);
    }

    /**
     * Permanently remove a tenant and cascaded data (super admin only).
     * Cannot delete the reserved platform owners organization.
     */
    public function destroy(Request $request, string|int $id)
    {
        if ($response = $this->checkSuperAdmin($request->user())) {
            return $response;
        }

        $company = Company::findOrFail((int) $id);

        if (strtolower($company->email) === strtolower(self::RESERVED_OWNER_TENANT_EMAIL)) {
            return response()->json([
                'message' => 'This reserved platform organization cannot be deleted.',
            ], 422);
        }

        if (User::query()
            ->where('company_id', $company->id)
            ->where('role', 'super_admin')
            ->exists()
        ) {
            return response()->json([
                'message' => 'Cannot delete an organization that still has platform super administrators. Reassign those users first.',
            ], 422);
        }

        $snapshot = [
            'company_name' => $company->name,
            'company_email' => $company->email,
        ];

        $company->delete();

        AuditLogger::record(
            $request,
            $request->user(),
            'company.deleted',
            'company',
            (int) $id,
            $snapshot
        );

        return response()->json([
            'success' => true,
            'message' => 'Organization deleted.',
        ]);
    }
}