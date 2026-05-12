<?php

namespace App\Http\Controllers;

use App\Jobs\ReclusterCompanyJob;
use App\Models\AdminApprovalRequest;
use App\Models\Company;
use App\Models\Complaint;
use App\Models\User;
use App\Services\ApprovalRequestNotifier;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminApprovalRequestController extends Controller
{
    private const TYPES = ['subscription_change', 'user_delete', 'user_promote_admin'];

    /**
     * Tenant admins: submit sensitive changes for platform super admin approval.
     */
    public function store(Request $request)
    {
        $actor = $request->user();

        if ($actor->role !== 'admin' || ! $actor->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Only organization administrators may submit approval requests.',
            ], 403);
        }

        $request->validate([
            'type' => 'required|string|in:'.implode(',', self::TYPES),
            'payload' => 'required|array',
        ]);

        $type = $request->type;
        $payload = $request->payload;
        $companyId = (int) $actor->company_id;

        if ($type === 'subscription_change') {
            $sub = $payload['subscription'] ?? null;
            if (! in_array($sub, ['free', 'premium'], true)) {
                return response()->json(['success' => false, 'message' => 'Invalid subscription value.'], 422);
            }
            if ($this->hasPending($companyId, 'subscription_change')) {
                return response()->json([
                    'success' => false,
                    'message' => 'A subscription change is already waiting for approval.',
                ], 422);
            }
        }

        if ($type === 'user_delete') {
            $targetId = (int) ($payload['target_user_id'] ?? 0);
            if (! $targetId) {
                return response()->json(['success' => false, 'message' => 'target_user_id is required.'], 422);
            }
            $target = User::where('company_id', $companyId)->where('id', $targetId)->first();
            if (! $target) {
                return response()->json(['success' => false, 'message' => 'User not found in your organization.'], 404);
            }
            if ($target->id === $actor->id) {
                return response()->json(['success' => false, 'message' => 'You cannot request deletion of yourself.'], 422);
            }
            if ($this->hasPendingUserAction($companyId, 'user_delete', $targetId)) {
                return response()->json(['success' => false, 'message' => 'A delete request for this user is already pending.'], 422);
            }
        }

        if ($type === 'user_promote_admin') {
            $targetId = (int) ($payload['target_user_id'] ?? 0);
            if (! $targetId) {
                return response()->json(['success' => false, 'message' => 'target_user_id is required.'], 422);
            }
            $target = User::where('company_id', $companyId)->where('id', $targetId)->first();
            if (! $target) {
                return response()->json(['success' => false, 'message' => 'User not found in your organization.'], 404);
            }
            if ($target->role === 'admin') {
                return response()->json(['success' => false, 'message' => 'User is already an administrator.'], 422);
            }
            if ($this->hasPendingUserAction($companyId, 'user_promote_admin', $targetId)) {
                return response()->json(['success' => false, 'message' => 'A promotion request for this user is already pending.'], 422);
            }
        }

        $row = AdminApprovalRequest::create([
            'company_id' => $companyId,
            'requester_id' => $actor->id,
            'type' => $type,
            'payload' => $payload,
            'status' => AdminApprovalRequest::STATUS_PENDING,
        ]);

        AuditLogger::record($request, $actor, 'admin_approval.submitted', 'admin_approval_request', (int) $row->id, [
            'type' => $type,
            'company_id' => $companyId,
        ]);

        $row->loadMissing('company:id,name', 'requester:id,name,email');
        ApprovalRequestNotifier::notifySuperAdminsOfNewRequest($row);

        return response()->json([
            'success' => true,
            'message' => 'Request submitted. A platform super administrator will review it.',
            'request' => $row,
        ], 201);
    }

    /**
     * Super admin: pending queue. Tenant admin: own submissions.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'super_admin') {
            $q = AdminApprovalRequest::query()
                ->with(['requester:id,name,email', 'company:id,name'])
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->orderByDesc('id');

            if ($request->boolean('pending_only', true)) {
                $q->where('status', AdminApprovalRequest::STATUS_PENDING);
            }

            return response()->json([
                'success' => true,
                'requests' => $q->limit(200)->get(),
            ]);
        }

        if ($user->role === 'admin' && $user->company_id) {
            $rows = AdminApprovalRequest::query()
                ->where('requester_id', $user->id)
                ->with(['company:id,name'])
                ->orderByDesc('id')
                ->limit(100)
                ->get();

            return response()->json([
                'success' => true,
                'requests' => $rows,
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    public function approve(Request $request, string|int $id)
    {
        $reviewer = $request->user();
        if ($reviewer->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $row = AdminApprovalRequest::query()->where('id', (int) $id)->firstOrFail();

        if ($row->status !== AdminApprovalRequest::STATUS_PENDING) {
            return response()->json(['success' => false, 'message' => 'This request is no longer pending.'], 409);
        }

        try {
            DB::transaction(function () use ($request, $row, $reviewer) {
                if ($row->type === 'subscription_change') {
                    $sub = $row->payload['subscription'] ?? null;
                    if (! in_array($sub, ['free', 'premium'], true) || ! $row->company_id) {
                        throw new \InvalidArgumentException('Invalid subscription payload');
                    }
                    $company = Company::findOrFail($row->company_id);
                    $from = $company->subscription;
                    $company->subscription = $sub;
                    $company->save();

                    AuditLogger::record($request, $reviewer, 'company.subscription_changed', 'company', (int) $company->id, [
                        'company_name' => $company->name,
                        'from' => $from,
                        'to' => $company->subscription,
                        'via' => 'admin_approval_request',
                        'approval_request_id' => $row->id,
                    ]);
                }

                if ($row->type === 'user_delete') {
                    $targetId = (int) ($row->payload['target_user_id'] ?? 0);
                    $target = User::where('id', $targetId)->first();
                    if (! $target || (int) $target->company_id !== (int) $row->company_id) {
                        throw new \InvalidArgumentException('Invalid delete target');
                    }
                    if ($target->id === $reviewer->id) {
                        throw new \InvalidArgumentException('Cannot delete reviewer self via approval');
                    }
                    $target->delete();

                    AuditLogger::record($request, $reviewer, 'user.soft_deleted', 'user', (int) $target->id, [
                        'target_email' => $target->email,
                        'target_name' => $target->name,
                        'target_role' => $target->role,
                        'company_id' => $target->company_id,
                        'via' => 'admin_approval_request',
                        'approval_request_id' => $row->id,
                    ]);
                }

                if ($row->type === 'user_promote_admin') {
                    $targetId = (int) ($row->payload['target_user_id'] ?? 0);
                    $target = User::where('id', $targetId)->first();
                    if (! $target || (int) $target->company_id !== (int) $row->company_id) {
                        throw new \InvalidArgumentException('Invalid promotion target');
                    }
                    $target->role = 'admin';
                    $target->save();

                    AuditLogger::record($request, $reviewer, 'user.promoted_to_admin', 'user', (int) $target->id, [
                        'target_email' => $target->email,
                        'company_id' => $target->company_id,
                        'via' => 'admin_approval_request',
                        'approval_request_id' => $row->id,
                    ]);
                }

                if ($row->type === 'complaint_status_change') {
                    $complaintId = (int) ($row->payload['complaint_id'] ?? 0);
                    $to = $row->payload['to_status'] ?? null;
                    if (! $complaintId || ! in_array($to, ['open', 'in_progress', 'resolved'], true)) {
                        throw new \InvalidArgumentException('Invalid complaint status payload');
                    }
                    $complaint = Complaint::query()->find($complaintId);
                    if (! $complaint || (int) $complaint->company_id !== (int) $row->company_id) {
                        throw new \InvalidArgumentException('Complaint not found in this organization');
                    }
                    $fromStatus = $complaint->status;
                    $complaint->status = $to;
                    $complaint->save();

                    AuditLogger::record($request, $reviewer, 'complaint.status_changed', 'complaint', (int) $complaint->id, [
                        'company_id' => $complaint->company_id,
                        'from' => $fromStatus,
                        'to' => $to,
                        'via' => 'admin_approval_request',
                        'approval_request_id' => $row->id,
                    ]);

                    if ($complaint->company_id) {
                        ReclusterCompanyJob::dispatch((int) $complaint->company_id)->afterCommit();
                    }
                }

                $row->status = AdminApprovalRequest::STATUS_APPROVED;
                $row->reviewed_by_id = $reviewer->id;
                $row->reviewed_at = now();
                $row->reviewer_note = $request->input('note');
                $row->save();

                AuditLogger::record($request, $reviewer, 'admin_approval.approved', 'admin_approval_request', (int) $row->id, [
                    'type' => $row->type,
                    'company_id' => $row->company_id,
                ]);
            });
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $row->refresh();
        ApprovalRequestNotifier::notifyRequesterResolved($row, 'approved', $request->input('note'));

        return response()->json([
            'success' => true,
            'message' => 'Request approved and applied.',
            'request' => $row->fresh()->loadMissing('requester:id,name,email', 'company:id,name'),
        ]);
    }

    public function reject(Request $request, string|int $id)
    {
        $reviewer = $request->user();
        if ($reviewer->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $row = AdminApprovalRequest::query()->where('id', (int) $id)->firstOrFail();

        if ($row->status !== AdminApprovalRequest::STATUS_PENDING) {
            return response()->json(['success' => false, 'message' => 'This request is no longer pending.'], 409);
        }

        $row->status = AdminApprovalRequest::STATUS_REJECTED;
        $row->reviewed_by_id = $reviewer->id;
        $row->reviewed_at = now();
        $row->reviewer_note = $request->input('note');
        $row->save();

        AuditLogger::record($request, $reviewer, 'admin_approval.rejected', 'admin_approval_request', (int) $row->id, [
            'type' => $row->type,
            'company_id' => $row->company_id,
        ]);

        $row->refresh();
        ApprovalRequestNotifier::notifyRequesterResolved($row, 'rejected', $request->input('note'));

        return response()->json([
            'success' => true,
            'message' => 'Request rejected.',
            'request' => $row->fresh(),
        ]);
    }

    private function hasPending(int $companyId, string $type): bool
    {
        return AdminApprovalRequest::query()
            ->where('company_id', $companyId)
            ->where('type', $type)
            ->where('status', AdminApprovalRequest::STATUS_PENDING)
            ->exists();
    }

    private function hasPendingUserAction(int $companyId, string $type, int $targetUserId): bool
    {
        return AdminApprovalRequest::query()
            ->where('company_id', $companyId)
            ->where('type', $type)
            ->where('status', AdminApprovalRequest::STATUS_PENDING)
            ->where('payload->target_user_id', $targetUserId)
            ->exists();
    }
}
