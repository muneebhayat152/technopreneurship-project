<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\IssueCluster;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user()->loadMissing('company');

        if ($user->role === 'super_admin') {
            $complaints = Complaint::query();
            $clusterQuery = IssueCluster::query()->withCount('complaints');
        } elseif ($user->role === 'admin') {
            $complaints = Complaint::where('company_id', $user->company_id);
            $clusterQuery = IssueCluster::where('company_id', $user->company_id)->withCount('complaints');
        } else {
            $complaints = Complaint::where('user_id', $user->id);
            $clusterQuery = IssueCluster::where('company_id', $user->company_id)->withCount('complaints');
        }

        $isPremium = $user->role === 'super_admin'
            || ($user->company && $user->company->subscription === 'premium');

        $total = (clone $complaints)->count();

        $open = (clone $complaints)->where('status', 'open')->count();
        $resolved = (clone $complaints)->where('status', 'resolved')->count();
        $inProgress = (clone $complaints)->where('status', 'in_progress')->count();

        $positive = (clone $complaints)->where('sentiment', 'positive')->count();
        $negative = (clone $complaints)->where('sentiment', 'negative')->count();
        $neutral = (clone $complaints)
            ->where(function ($q) {
                $q->whereNull('sentiment')
                    ->orWhere('sentiment', 'neutral');
            })->count();

        $delivery = (clone $complaints)->where('category', 'delivery')->count();
        $payment = (clone $complaints)->where('category', 'payment')->count();
        $service = (clone $complaints)->where('category', 'service')->count();

        $todayStart = Carbon::today();
        $criticalToday = (clone $complaints)
            ->whereDate('created_at', '>=', $todayStart)
            ->where(function ($q) {
                $q->where('priority', 'high')
                    ->orWhere('sentiment', 'negative');
            })
            ->count();

        $trend7d = $this->trendLastDays(clone $complaints, 7);

        $topIssues = [];
        if ($isPremium) {
            $topIssues = (clone $clusterQuery)
                ->orderByDesc('complaints_count')
                ->limit(8)
                ->get()
                ->map(function (IssueCluster $c) {
                    return [
                        'id' => $c->id,
                        'title' => $c->title,
                        'count' => (int) ($c->complaints_count ?? $c->complaint_count),
                        'severity' => $c->severity,
                        'trend' => 'stable',
                    ];
                })
                ->values()
                ->all();
        }

        $customerMood = null;
        if ($isPremium) {
            if ($negative > $positive + $neutral) {
                $customerMood = 'Mostly negative';
            } elseif ($positive >= $negative && $positive >= $neutral) {
                $customerMood = 'Mostly positive / balanced';
            } else {
                $customerMood = 'Mixed';
            }
        }

        return response()->json([
            'total_complaints' => $total,
            'complaints_today' => (clone $complaints)->whereDate('created_at', Carbon::today())->count(),
            'critical_issues_today' => $criticalToday,
            'plan' => [
                'is_premium' => $isPremium,
                'subscription' => $user->company?->subscription ?? ($user->role === 'super_admin' ? 'platform' : 'free'),
            ],
            'status' => [
                'open' => $open,
                'in_progress' => $inProgress,
                'resolved' => $resolved,
            ],
            'priority' => [
                'low' => (clone $complaints)->where('priority', 'low')->count(),
                'medium' => (clone $complaints)->where('priority', 'medium')->count(),
                'high' => (clone $complaints)->where('priority', 'high')->count(),
            ],
            'sentiment' => [
                'positive' => $positive,
                'negative' => $negative,
                'neutral' => $neutral,
            ],
            'category' => [
                'delivery' => $delivery,
                'payment' => $payment,
                'service' => $service,
            ],
            'trend_7d' => $trend7d,
            'top_issues' => $topIssues,
            'customer_mood' => $customerMood,
        ]);
    }

    /**
     * @return list<array{date:string,count:int}>
     */
    private function trendLastDays($query, int $days): array
    {
        $start = Carbon::today()->subDays($days - 1)->startOfDay();

        $rows = (clone $query)
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->keyBy('d');

        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = Carbon::today()->subDays($i)->format('Y-m-d');
            $out[] = [
                'date' => $d,
                'count' => (int) ($rows[$d]->c ?? 0),
            ];
        }

        return $out;
    }
}
