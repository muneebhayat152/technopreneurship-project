<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Models\AdminApprovalRequest;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    /**
     * 🔐 Check admin access
     */
    private function checkAdmin($user)
    {
        if (!$user || !in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }
        return null;
    }

    /**
     * 👥 Get all users (WITH SOFT DELETE 🔥)
     */
    public function users(Request $request)
    {
        $admin = $request->user();

        if ($response = $this->checkAdmin($admin)) return $response;

        try {
            // 🔥 SUPER ADMIN → ALL USERS (INCLUDING DELETED)
            if ($admin->role === 'super_admin') {
                $users = User::with('company')
                    ->withTrashed()
                    ->latest()
                    ->get();
            } 
            // 🔒 ADMIN → ONLY COMPANY USERS
            else {
                $users = User::with('company')
                    ->withTrashed()
                    ->where('company_id', $admin->company_id)
                    ->latest()
                    ->get();
            }

            return response()->json([
                'success' => true,
                'users' => $users
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Failed to load users', [
                'admin_id' => $admin?->id,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load users',
            ], 500);
        }
    }

    /**
     * ➕ Create new user
     */
    public function createUser(Request $request)
    {
        $admin = $request->user();

        if ($response = $this->checkAdmin($admin)) return $response;

        $roleRule = $admin->role === 'super_admin'
            ? 'required|in:super_admin,admin,user'
            : 'required|in:user';

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'role' => $roleRule,
            'company_id' => 'nullable|exists:companies,id',
            'access_tier' => 'nullable|in:free,premium',
        ]);

        $companyId = $admin->role === 'super_admin'
            ? ($request->input('company_id') ?: $admin->company_id)
            : $admin->company_id;

        if (! $companyId) {
            return response()->json([
                'success' => false,
                'message' => 'company_id is required when creating users as super admin',
            ], 422);
        }

        $accessTier = $request->input('access_tier');

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'company_id' => $companyId,
            'role' => $request->role,
            'access_tier' => $accessTier ?: null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'user' => $user->loadMissing('company'),
        ], 201);
    }

    /**
     * ✏️ Update user
     */
    public function updateUser(Request $request, $id)
    {
        $admin = $request->user();

        if ($response = $this->checkAdmin($admin)) return $response;

        // 🔥 INCLUDE DELETED USERS
        if ($admin->role === 'super_admin') {
            $user = User::withTrashed()->find($id);
        } else {
            $user = User::withTrashed()
                ->where('company_id', $admin->company_id)
                ->where('id', $id)
                ->first();
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $allowedRoles = $admin->role === 'super_admin'
            ? ['super_admin', 'admin', 'user']
            : ($user->role === 'admin'
                ? ['admin']
                : ['user']);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => ['required', Rule::in($allowedRoles)],
            'password' => 'nullable|min:6',
            'access_tier' => 'nullable|in:free,premium',
        ]);

        if ($request->has('access_tier')) {
            if ($user->role === 'super_admin' && $admin->role !== 'super_admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only platform super administrators may change plan access for platform administrators.',
                ], 403);
            }
            $raw = $request->input('access_tier');
            $user->access_tier = ($raw === '' || $raw === null) ? null : $raw;
        }

        $user->name = $request->name;
        $user->email = $request->email;
        $user->role = $request->role;

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'user' => $user->fresh()->loadMissing('company'),
        ]);
    }

    /**
     * ❌ Delete user (SOFT DELETE)
     */
    public function deleteUser(Request $request, $id)
    {
        $admin = $request->user();

        if ($response = $this->checkAdmin($admin)) return $response;

        if ($admin->role === 'super_admin') {
            $user = User::find($id);
        } else {
            $user = User::where('company_id', $admin->company_id)
                ->where('id', $id)
                ->first();
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        if ($user->id === $admin->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete yourself'
            ], 400);
        }

        if ($admin->role !== 'super_admin') {
            if (! $admin->company_id || (int) $user->company_id !== (int) $admin->company_id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $dup = AdminApprovalRequest::query()
                ->where('company_id', $admin->company_id)
                ->where('type', 'user_delete')
                ->where('status', AdminApprovalRequest::STATUS_PENDING)
                ->where('payload->target_user_id', $user->id)
                ->exists();

            if ($dup) {
                return response()->json([
                    'success' => false,
                    'message' => 'A removal request for this user is already pending super administrator approval.',
                ], 422);
            }

            $row = AdminApprovalRequest::create([
                'company_id' => (int) $admin->company_id,
                'requester_id' => $admin->id,
                'type' => 'user_delete',
                'payload' => ['target_user_id' => $user->id],
                'status' => AdminApprovalRequest::STATUS_PENDING,
            ]);

            AuditLogger::record($request, $admin, 'admin_approval.submitted', 'admin_approval_request', (int) $row->id, [
                'type' => 'user_delete',
                'company_id' => $admin->company_id,
                'target_user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'pending' => true,
                'message' => 'Removal submitted for platform super administrator approval. The account stays active until approved.',
                'request_id' => $row->id,
            ], 202);
        }

        $user->delete(); // ✅ SOFT DELETE

        AuditLogger::record(
            $request,
            $admin,
            'user.soft_deleted',
            'user',
            (int) $user->id,
            [
                'target_email' => $user->email,
                'target_name' => $user->name,
                'target_role' => $user->role,
                'company_id' => $user->company_id,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * ♻️ RESTORE USER (NEW 🔥)
     */
    public function restoreUser(Request $request, $id)
    {
        $admin = $request->user();

        if ($response = $this->checkAdmin($admin)) return $response;

        if ($admin->role === 'super_admin') {
            $user = User::withTrashed()->find($id);
        } else {
            $user = User::withTrashed()
                ->where('company_id', $admin->company_id)
                ->where('id', $id)
                ->first();
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $user->restore();

        return response()->json([
            'success' => true,
            'message' => 'User restored successfully',
            'user' => $user
        ]);
    }
}