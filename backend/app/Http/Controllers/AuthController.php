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
            ],

            'company' => $user->company ? [
                'id' => $user->company->id,
                'name' => $user->company->name,
                'email' => $user->company->email,
                'subscription' => $user->company->subscription,
                'is_active' => (bool) $user->company->is_active,
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
                // Freemium: new organizations land on Free; tenant admin can upgrade to Premium in-app.
                'subscription' => 'free',
                'is_active' => true
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'company_id' => $company->id,
                'role' => 'admin'
            ]);

            $token = $this->issueToken($user);

            DB::commit();

            try {
                PlatformActivityNotifier::notifyNewOrganization($company, $user);
            } catch (\Throwable $e) {
                Log::warning('tenant_register_notify_failed', [
                    'company_id' => $company->id,
                    'exception' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Registration successful',
                'token' => $token,

                'user' => $user->load('company'),

                'company' => $company
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

        // 🚫 Company inactive check (super_admin may have no company)
        if ($user->role !== 'super_admin') {
            if (!$user->company || !$user->company->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company is inactive or missing'
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
