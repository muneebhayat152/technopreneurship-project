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

        if ($company->registration_status !== Company::REGISTRATION_ACTIVE) {
            return response()->json([
                'message' => 'Only approved organizations can be toggled active or inactive. Use approve or reject registration first.',
            ], 422);
        }

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

        if ($company->registration_status !== Company::REGISTRATION_ACTIVE) {
            return response()->json([
                'message' => 'This organization is not approved yet. Approve the registration (choose Free or Premium) before changing the plan.',
            ], 422);
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
     */
    public function destroy(Request $request, string|int $id)
    {
        if ($response = $this->checkSuperAdmin($request->user())) {
            return $response;
        }

        $company = Company::findOrFail((int) $id);

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

    /**
     * Approve a pending self-registered organization and activate it on Free or Premium.
     */
    public function approveRegistration(Request $request, string|int $id)
    {
        if ($response = $this->checkSuperAdmin($request->user())) {
            return $response;
        }

        $request->validate([
            'subscription' => 'required|in:free,premium',
        ]);

        $company = Company::findOrFail((int) $id);

        if ($company->registration_status !== Company::REGISTRATION_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Only organizations that are waiting for owner approval can be approved here.',
            ], 422);
        }

        $subscription = $request->input('subscription');
        $company->registration_status = Company::REGISTRATION_ACTIVE;
        $company->is_active = true;
        $company->subscription = $subscription;
        $company->save();

        AuditLogger::record(
            $request,
            $request->user(),
            'company.registration_approved',
            'company',
            (int) $company->id,
            [
                'company_name' => $company->name,
                'subscription' => $subscription,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Organization approved and activated.',
            'company' => $company->fresh(),
        ]);
    }

    /**
     * Reject a pending self-registered organization (tenant cannot sign in).
     */
    public function rejectRegistration(Request $request, string|int $id)
    {
        if ($response = $this->checkSuperAdmin($request->user())) {
            return $response;
        }

        $request->validate([
            'note' => 'nullable|string|max:2000',
        ]);

        $company = Company::findOrFail((int) $id);

        if ($company->registration_status !== Company::REGISTRATION_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending organizations can be rejected this way.',
            ], 422);
        }

        $company->registration_status = Company::REGISTRATION_REJECTED;
        $company->is_active = false;
        $company->save();

        AuditLogger::record(
            $request,
            $request->user(),
            'company.registration_rejected',
            'company',
            (int) $company->id,
            [
                'company_name' => $company->name,
                'note' => $request->input('note'),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Registration rejected. The organization admin cannot access the platform.',
            'company' => $company->fresh(),
        ]);
    }
}
