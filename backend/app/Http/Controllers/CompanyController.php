<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\Request;
use App\Models\Company;

class CompanyController extends Controller
{
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
}