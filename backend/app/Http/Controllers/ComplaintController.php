<?php

namespace App\Http\Controllers;

use App\Jobs\ReclusterCompanyJob;
use App\Models\AdminApprovalRequest;
use App\Models\Complaint;
use App\Models\User;
use App\Services\ApprovalRequestNotifier;
use App\Services\AuditLogger;
use App\Services\ComplaintSentimentAnalyzer;
use App\Services\PlatformActivityNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ComplaintController extends Controller
{
    public function __construct(
        private ComplaintSentimentAnalyzer $sentimentAnalyzer
    ) {}

    private function queueRecluster(?int $companyId): void
    {
        if (! $companyId) {
            return;
        }

        ReclusterCompanyJob::dispatch($companyId)->afterCommit();
    }

    public function store(Request $request)
    {
        $request->validate([
            'complaint_text' => 'required|string',
        ]);

        $user = $request->user();

        if (! $user->company_id) {
            return response()->json([
                'message' => 'Complaints can only be submitted by users linked to a company.',
            ], 422);
        }

        $analysis = $this->sentimentAnalyzer->analyze($request->complaint_text);
        $sentiment = $analysis['sentiment'];

        $complaint = Complaint::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'complaint_text' => $request->complaint_text,
            'sentiment' => $sentiment,
            'sentiment_score' => $analysis['sentiment_score'],
            'category' => $analysis['category'],
            'status' => 'open',
            'priority' => $sentiment === 'negative' ? 'high' : 'medium',
        ]);

        $this->queueRecluster((int) $user->company_id);

        try {
            PlatformActivityNotifier::notifyComplaintSubmitted($complaint->fresh());
        } catch (\Throwable $e) {
            Log::warning('complaint_notify_failed', [
                'complaint_id' => $complaint->id,
                'exception' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Complaint created successfully',
            'complaint' => $complaint->fresh(),
            'sentiment_analysis' => [
                'label' => $analysis['sentiment'],
                'score' => $analysis['sentiment_score'],
                'category' => $analysis['category'],
            ],
        ], 201);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'super_admin') {
            $query = Complaint::with(['user.company', 'issueCluster']);
        } elseif ($user->role === 'admin') {
            $query = Complaint::with(['user.company', 'issueCluster'])
                ->where('company_id', $user->company_id);
        } else {
            $query = Complaint::with(['user.company', 'issueCluster'])
                ->where('user_id', $user->id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('sentiment')) {
            $query->where('sentiment', $request->sentiment);
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $complaints = $query->latest()->get();

        return response()->json([
            'complaints' => $complaints,
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:open,in_progress,resolved',
        ]);

        $complaint = Complaint::findOrFail($id);
        $user = $request->user();

        if (! in_array($user->role, ['admin', 'super_admin'], true)) {
            return response()->json([
                'message' => 'Only administrators may update complaint status.',
            ], 403);
        }

        if ($user->role !== 'super_admin' && (int) $complaint->company_id !== (int) $user->company_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->role === 'super_admin') {
            return $this->applyComplaintStatus($request, $complaint, $user);
        }

        if ($user->role === 'admin' && $user->company_id) {
            if ($complaint->status === $request->status) {
                return response()->json([
                    'success' => true,
                    'message' => 'No change',
                    'complaint' => $complaint->fresh(),
                ]);
            }

            $dup = AdminApprovalRequest::query()
                ->where('company_id', (int) $user->company_id)
                ->where('type', 'complaint_status_change')
                ->where('status', AdminApprovalRequest::STATUS_PENDING)
                ->where('payload->complaint_id', (int) $complaint->id)
                ->exists();

            if ($dup) {
                return response()->json([
                    'success' => false,
                    'message' => 'A status change for this complaint is already pending super administrator approval.',
                ], 422);
            }

            $fromStatus = $complaint->status;
            $row = AdminApprovalRequest::create([
                'company_id' => (int) $user->company_id,
                'requester_id' => $user->id,
                'type' => 'complaint_status_change',
                'payload' => [
                    'complaint_id' => (int) $complaint->id,
                    'from_status' => $fromStatus,
                    'to_status' => $request->status,
                ],
                'status' => AdminApprovalRequest::STATUS_PENDING,
            ]);

            AuditLogger::record($request, $user, 'admin_approval.submitted', 'admin_approval_request', (int) $row->id, [
                'type' => 'complaint_status_change',
                'company_id' => $user->company_id,
                'complaint_id' => $complaint->id,
            ]);

            $row->loadMissing('company:id,name', 'requester:id,name,email');
            ApprovalRequestNotifier::notifySuperAdminsOfNewRequest($row);

            return response()->json([
                'success' => true,
                'pending' => true,
                'message' => 'Complaint status change submitted for platform super administrator approval.',
                'request_id' => $row->id,
            ], 202);
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }

    private function applyComplaintStatus(Request $request, Complaint $complaint, User $user): JsonResponse
    {
        $fromStatus = $complaint->status;
        $complaint->update([
            'status' => $request->status,
        ]);

        AuditLogger::record(
            $request,
            $user,
            'complaint.status_changed',
            'complaint',
            (int) $complaint->id,
            [
                'company_id' => $complaint->company_id,
                'from' => $fromStatus,
                'to' => $request->status,
            ]
        );

        $this->queueRecluster($complaint->company_id ? (int) $complaint->company_id : null);

        return response()->json([
            'message' => 'Status updated successfully',
            'complaint' => $complaint->fresh(),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $complaint = Complaint::findOrFail($id);

        if ($user->role === 'super_admin') {
            // Platform scope: allowed.
        } elseif ((int) $complaint->user_id === (int) $user->id) {
            // Only the submitting user may remove their own complaint.
        } else {
            return response()->json([
                'message' => 'Only The Complaint Owner May Delete This Record.',
            ], 403);
        }

        $companyId = $complaint->company_id;
        $complaint->delete();

        $this->queueRecluster($companyId ? (int) $companyId : null);

        return response()->json([
            'message' => 'Complaint deleted successfully',
        ]);
    }

    /**
     * Update complaint text (submitting user only).
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'complaint_text' => 'required|string|max:20000',
        ]);

        $user = $request->user();
        $complaint = Complaint::findOrFail($id);

        if ((int) $complaint->user_id !== (int) $user->id) {
            return response()->json([
                'message' => 'Only The Submitting User May Edit This Complaint.',
            ], 403);
        }

        $analysis = $this->sentimentAnalyzer->analyze($request->complaint_text);
        $sentiment = $analysis['sentiment'];

        $complaint->update([
            'complaint_text' => $request->complaint_text,
            'sentiment' => $sentiment,
            'sentiment_score' => $analysis['sentiment_score'],
            'category' => $analysis['category'],
            'priority' => $sentiment === 'negative' ? 'high' : $complaint->priority,
        ]);

        $this->queueRecluster($complaint->company_id ? (int) $complaint->company_id : null);

        return response()->json([
            'message' => 'Complaint updated',
            'complaint' => $complaint->fresh(),
            'sentiment_analysis' => [
                'label' => $analysis['sentiment'],
                'score' => $analysis['sentiment_score'],
                'category' => $analysis['category'],
            ],
        ]);
    }
}
