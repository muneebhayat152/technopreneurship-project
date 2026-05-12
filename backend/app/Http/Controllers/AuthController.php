<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Company;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\PlatformActivityNotifier;

class AuthController extends Controller
{
    private function issueToken(User $user): string
    {
        $abilities = ['*', 'role:'.$user->role];

        return $user->createToken('auth_token', $abilities)->plainTextToken;
    }

    private function loginSuccessPayload(User $user, string $token): array
    {
        return [
            'success' => true,
            'message' => 'Login successful',
            'token' => $token,

            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'company_id' => $user->company_id,
                'access_tier' => $user->access_tier,
                'effective_access_tier' => $user->computeEffectiveAccessTier(),
            ],

            'company' => $user->company ? [
                'id' => $user->company->id,
                'name' => $user->company->name,
                'email' => $user->company->email,
                'subscription' => $user->company->subscription,
                'is_active' => (bool) $user->company->is_active,
                'registration_status' => $user->company->registration_status ?? Company::REGISTRATION_ACTIVE,
            ] : null,
        ];
    }

    /**
     * Register a new company and its primary administrator.
     */
    public function register(Request $request)
    {
        $request->validate([
            'company_name' => 'required|string|max:255',
            'company_email' => 'required|email|unique:companies,email',
            'industry' => 'required|string|max:255',
            'country' => 'required|string|max:255',

            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6'
        ]);

        try {
            DB::beginTransaction();

            $company = Company::create([
                'name' => $request->company_name,
                'email' => $request->company_email,
                'industry' => $request->industry,
                'country' => $request->country,
                // Always Free until platform owners approve; Premium is assigned only at approval (or later).
                'subscription' => 'free',
                'is_active' => false,
                'registration_status' => Company::REGISTRATION_PENDING,
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'company_id' => $company->id,
                'role' => 'admin',
            ]);

            DB::commit();

            try {
                PlatformActivityNotifier::notifyNewOrganization($company->fresh(), $user->fresh());
            } catch (\Throwable $e) {
                Log::warning('tenant_register_notify_failed', [
                    'company_id' => $company->id,
                    'exception' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'success' => true,
                'pending_owner_approval' => true,
                'message' => 'Registration received. Your organization is on the Free plan and must be approved by AI Complaint Doctor platform owners before you can sign in.',
                'company' => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'email' => $company->email,
                    'subscription' => $company->subscription,
                    'registration_status' => $company->registration_status,
                ],
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Registration failed', [
                'email' => $request->email,
                'company_email' => $request->company_email,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
            ], 500);
        }
    }

    /**
     * Authenticate and issue an API token.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::with('company')->where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        // 🚫 Tenant access (super_admin may have no company)
        if ($user->role !== 'super_admin') {
            if (! $user->company) {
                return response()->json([
                    'success' => false,
                    'message' => 'No organization is linked to this account.',
                ], 403);
            }

            $reg = $user->company->registration_status ?? Company::REGISTRATION_ACTIVE;

            if ($reg === Company::REGISTRATION_PENDING) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your organization is waiting for approval from AI Complaint Doctor platform owners. You will be able to sign in after approval.',
                    'registration_status' => Company::REGISTRATION_PENDING,
                ], 403);
            }

            if ($reg === Company::REGISTRATION_REJECTED) {
                return response()->json([
                    'success' => false,
                    'message' => 'This organization was not approved for AI Complaint Doctor. Contact the platform team if you believe this is a mistake.',
                    'registration_status' => Company::REGISTRATION_REJECTED,
                ], 403);
            }

            if (! $user->company->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your organization is inactive. Contact your administrator or platform support.',
                ], 403);
            }
        }

        $token = $this->issueToken($user);

        return response()->json($this->loginSuccessPayload($user, $token));
    }

    /**
     * Revoke token for current session.
     */
    public function logout(Request $request)
    {
        $token = $request->user()?->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }
}
