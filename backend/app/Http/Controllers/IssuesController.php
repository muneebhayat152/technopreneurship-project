<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Complaint;
use App\Models\IssueCluster;
use App\Models\IssueTimeseries;
use Illuminate\Http\Request;

class IssuesController extends Controller
{
    private function forbidTenantInsights(Request $request): ?\Illuminate\Http\JsonResponse
    {
        if ($request->user()->role === 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Platform administrators manage organizations only. Issue patterns, alerts, and complaint analytics are available to each organization’s own administrators.',
            ], 403);
        }

        return null;
    }

    private function complaintScope(Request $request)
    {
        $user = $request->user();
        if ($user->role === 'admin') {
            return Complaint::where('company_id', $user->company_id);
        }

        return Complaint::where('user_id', $user->id);
    }

    private function clusterScope(Request $request)
    {
        $user = $request->user();

        return IssueCluster::where('company_id', $user->company_id);
    }

    /**
     * Premium: list detected issue patterns (clusters).
     */
    public function index(Request $request)
    {
        if ($r = $this->forbidTenantInsights($request)) {
            return $r;
        }

        $query = $this->clusterScope($request)
            ->with('company:id,name')
            ->withCount('complaints')
            ->orderByDesc('complaint_count');

        $limit = 60;
        $clusters = $query->limit($limit)->get()->map(function (IssueCluster $c) {
            return [
                'id' => $c->id,
                'company_id' => $c->company_id,
                'company_name' => $c->company?->name,
                'title' => $c->title,
                'keywords' => $c->keywords ?? [],
                'severity' => $c->severity,
                'status' => $c->status,
                'complaint_count' => $c->complaints_count ?? $c->complaint_count,
            ];
        });

        return response()->json(['success' => true, 'issues' => $clusters]);
    }

    /**
     * Premium: diagnosis for one cluster.
     */
    public function diagnosis(Request $request, string|int $id)
    {
        if ($r = $this->forbidTenantInsights($request)) {
            return $r;
        }

        $cluster = $this->clusterScope($request)->where('id', $id)->firstOrFail();

        $complaints = Complaint::where('issue_cluster_id', $id)->with('user:id,name')->latest()->limit(12)->get();

        $total = Complaint::where('issue_cluster_id', $id)->count();
        $sent = Complaint::where('issue_cluster_id', $id)
            ->selectRaw('sentiment, COUNT(*) as c')
            ->groupBy('sentiment')
            ->pluck('c', 'sentiment');

        $series = IssueTimeseries::where('issue_cluster_id', $id)->orderBy('bucket_date')->get();
        $arr = $series->values();
        $last7 = $arr->slice(max(0, $arr->count() - 7))->values();
        $prev7 = $arr->count() > 7 ? $arr->slice(max(0, $arr->count() - 14), 7)->values() : collect();

        $sumLast = $last7->sum(fn ($r) => (int) $r->count);
        $sumPrev = $prev7->sum(fn ($r) => (int) $r->count);
        $pctChange = $sumPrev > 0 ? round((($sumLast - $sumPrev) / $sumPrev) * 100, 1) : ($sumLast > 0 ? 100.0 : 0.0);

        $suggestions = $this->suggestedActions($cluster);

        return response()->json([
            'success' => true,
            'issue' => [
                'id' => $cluster->id,
                'title' => $cluster->title,
                'keywords' => $cluster->keywords ?? [],
                'severity' => $cluster->severity,
                'status' => $cluster->status,
                'complaint_count' => $total,
                'sentiment_breakdown' => $sent,
                'percent_change_last_period' => $pctChange,
                'chart_7d' => $last7->map(fn ($r) => [
                    'date' => $r->bucket_date->format('Y-m-d'),
                    'count' => (int) $r->count,
                ]),
                'suggested_actions' => $suggestions,
                'sample_complaints' => $complaints->map(fn ($c) => [
                    'id' => $c->id,
                    'text' => $c->complaint_text,
                    'sentiment' => $c->sentiment,
                    'user' => $c->user?->name,
                    'created_at' => $c->created_at?->toIso8601String(),
                ]),
            ],
        ]);
    }

    /**
     * Premium: story timeline derived from time series.
     */
    public function timeline(Request $request, string|int $id)
    {
        if ($r = $this->forbidTenantInsights($request)) {
            return $r;
        }

        $cluster = $this->clusterScope($request)->where('id', $id)->firstOrFail();

        $series = IssueTimeseries::where('issue_cluster_id', $id)->orderBy('bucket_date')->get();
        if ($series->isEmpty()) {
            return response()->json([
                'success' => true,
                'cluster_id' => $cluster->id,
                'status' => $cluster->status,
                'events' => [],
            ]);
        }

        $counts = $series->pluck('count')->map(fn ($c) => (int) $c)->all();
        $maxIdx = array_keys($counts, max($counts))[0];
        $events = [];
        $day = 1;
        $first = $series->first();
        $events[] = [
            'day' => $day++,
            'label' => 'Pattern detected',
            'detail' => 'AI grouped similar complaints; baseline volume recorded.',
            'date' => $first->bucket_date->format('Y-m-d'),
        ];

        if (count($counts) > 2) {
            $mid = (int) floor(count($counts) / 2);
            $events[] = [
                'day' => $day++,
                'label' => 'Volume trend',
                'detail' => 'Complaint frequency evolves; managers should watch for sustained spikes.',
                'date' => $series[$mid]->bucket_date->format('Y-m-d'),
            ];
        }

        $events[] = [
            'day' => $day++,
            'label' => 'Peak activity',
            'detail' => 'Highest single-day count for this pattern: '.max($counts).' complaints.',
            'date' => $series[$maxIdx]->bucket_date->format('Y-m-d'),
        ];

        $last = $series->last();
        $events[] = [
            'day' => $day++,
            'label' => 'Latest snapshot',
            'detail' => 'Most recent day: '.$last->count.' complaints. Status: '.$cluster->status.'.',
            'date' => $last->bucket_date->format('Y-m-d'),
        ];

        return response()->json([
            'success' => true,
            'cluster_id' => $cluster->id,
            'title' => $cluster->title,
            'status' => $cluster->status,
            'events' => $events,
        ]);
    }

    /**
     * Premium: smart alerts for tenant.
     */
    public function alerts(Request $request)
    {
        if ($r = $this->forbidTenantInsights($request)) {
            return $r;
        }

        $user = $request->user();
        $limit = 50;

        $q = Alert::query()
            ->with(['company:id,name'])
            ->orderByDesc('triggered_at')
            ->limit($limit)
            ->where('company_id', $user->company_id);

        return response()->json([
            'success' => true,
            'alerts' => $q->get(),
        ]);
    }

    /**
     * Mark alert read (premium).
     */
    public function markAlertRead(Request $request, string|int $id)
    {
        if ($r = $this->forbidTenantInsights($request)) {
            return $r;
        }

        $user = $request->user();
        $alert = Alert::findOrFail($id);
        if ((int) $alert->company_id !== (int) $user->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        $alert->is_read = true;
        $alert->save();

        return response()->json(['success' => true, 'alert' => $alert]);
    }

    private function suggestedActions(IssueCluster $cluster): array
    {
        $title = strtolower($cluster->title);
        $out = [];
        if (str_contains($title, 'payment') || str_contains($title, 'billing')) {
            $out[] = 'Review payment gateway timeouts and error logs.';
            $out[] = 'Align support scripts for refund and double-charge scenarios.';
        }
        if (str_contains($title, 'delivery') || str_contains($title, 'logistics')) {
            $out[] = 'Check carrier SLAs and last-mile partner performance.';
            $out[] = 'Communicate proactive delays to affected customers.';
        }
        if (str_contains($title, 'service') || str_contains($title, 'support')) {
            $out[] = 'Audit agent response times and escalation paths.';
            $out[] = 'Add training on top complaint phrases detected by AI.';
        }
        if ($out === []) {
            $out[] = 'Assign an owner, set a resolution target, and track daily volume.';
            $out[] = 'Share this diagnosis with product and operations leadership.';
        }

        return $out;
    }
}
