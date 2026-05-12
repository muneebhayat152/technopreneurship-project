<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Complaint;
use App\Models\IssueCluster;
use App\Models\IssueTimeseries;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ClusteringService
{
    /** @var array<string, true> */
    private static array $stopwords = [];

    public function __construct()
    {
        if (self::$stopwords === []) {
            $words = explode('|', 'the|a|an|and|or|but|in|on|at|to|for|of|as|is|was|are|were|be|been|being|have|has|had|do|does|did|will|would|could|should|may|might|must|shall|can|this|that|these|those|i|you|he|she|it|we|they|me|him|her|us|them|my|your|his|her|its|our|their|with|from|by|not|no|so|if|when|what|which|who|how|why|all|any|each|every|some|more|most|other|such|than|too|very|just|also|only|even|about|into|through|during|before|after|above|below|between|under|again|then|once|here|there|where|because|until|while|am|been|being|having|doing|said|says|get|got|go|went|come|came|make|made|take|took|see|saw|know|knew|think|thought|want|wanted|use|used|work|worked|call|called|try|tried|ask|asked|need|needed|feel|felt|become|became|leave|left|put|mean|means|keep|kept|let|help|helped|talk|talked|turn|turned|start|started|show|showed|hear|heard|play|played|run|ran|move|moved|live|lived|believe|believed|hold|held|bring|brought|happen|happened|write|wrote|sit|sat|stand|stood|lose|lost|pay|paid|meet|met|include|included|continue|continued|set|learn|learned|change|changed|lead|led|understand|understood|watch|watched|follow|followed|stop|stopped|create|created|speak|spoke|read|allow|allowed|add|added|spend|spent|grow|grew|open|opened|walk|walked|win|won|offer|offered|remember|remembered|love|loved|consider|considered|appear|appeared|buy|bought|wait|waited|serve|served|die|died|send|sent|expect|expected|build|built|stay|stayed|fall|fell|cut|reach|reached|kill|killed|remain|remained|suggest|suggested|raise|raised|pass|passed|sell|sold|require|required|report|reported|decide|decided|pull|pulled');
            foreach ($words as $w) {
                self::$stopwords[$w] = true;
            }
        }
    }

    public function reclusterCompany(int $companyId): void
    {
        DB::transaction(function () use ($companyId) {
            Complaint::where('company_id', $companyId)->update(['issue_cluster_id' => null]);

            $oldClusterIds = IssueCluster::where('company_id', $companyId)->pluck('id');
            if ($oldClusterIds->isNotEmpty()) {
                IssueTimeseries::whereIn('issue_cluster_id', $oldClusterIds)->delete();
                Alert::where('company_id', $companyId)->delete();
                IssueCluster::where('company_id', $companyId)->delete();
            }

            $complaints = Complaint::where('company_id', $companyId)->orderBy('id')->get();
            if ($complaints->isEmpty()) {
                return;
            }

            $docTokens = [];
            foreach ($complaints as $c) {
                $docTokens[$c->id] = $this->tokenize((string) $c->complaint_text);
            }

            $vocabList = $this->topVocabulary($docTokens, 180);
            if ($vocabList === []) {
                return;
            }

            $termIndex = array_flip($vocabList);
            $n = $complaints->count();
            $df = array_fill_keys($vocabList, 0);
            foreach ($docTokens as $toks) {
                $seen = [];
                foreach (array_unique($toks) as $t) {
                    if (isset($termIndex[$t])) {
                        $seen[$t] = true;
                    }
                }
                foreach (array_keys($seen) as $t) {
                    $df[$t]++;
                }
            }

            $idf = [];
            foreach ($vocabList as $t) {
                $idf[$t] = log(($n + 1) / ($df[$t] + 1)) + 1.0;
            }

            $vectors = [];
            foreach ($complaints as $c) {
                $vectors[$c->id] = $this->denseTfidf($docTokens[$c->id], $vocabList, $idf);
            }

            $ids = $complaints->pluck('id')->all();
            $k = $this->pickK($n);
            $assignment = $this->kMeansDense($vectors, $ids, $k, 25);

            $clustersByIndex = [];
            foreach ($assignment as $cid => $idx) {
                $clustersByIndex[$idx] ??= [];
                $clustersByIndex[$idx][] = $cid;
            }

            foreach ($clustersByIndex as $idx => $memberIds) {
                $memberComplaints = $complaints->whereIn('id', $memberIds);
                $freq = [];
                foreach ($memberComplaints as $mc) {
                    foreach ($docTokens[$mc->id] as $t) {
                        if (! isset($termIndex[$t])) {
                            continue;
                        }
                        $freq[$t] = ($freq[$t] ?? 0) + 1;
                    }
                }
                arsort($freq);
                $topKeys = array_slice(array_keys($freq), 0, 8);
                $title = $this->titleFromKeywords($topKeys, $memberComplaints);

                $neg = $memberComplaints->where('sentiment', 'negative')->count();
                $ratio = count($memberIds) > 0 ? $neg / count($memberIds) : 0;
                $severity = $ratio >= 0.45 ? 'high' : ($ratio >= 0.2 ? 'medium' : 'low');

                $cluster = IssueCluster::create([
                    'company_id' => $companyId,
                    'title' => $title,
                    'keywords' => array_values($topKeys),
                    'severity' => $severity,
                    'status' => 'open',
                    'complaint_count' => count($memberIds),
                ]);

                Complaint::whereIn('id', $memberIds)->update(['issue_cluster_id' => $cluster->id]);
            }

            $this->rebuildTimeseries($companyId);
            $this->generateAlerts($companyId);
        });
    }

    public function reclusterAllCompanies(): void
    {
        $ids = Complaint::query()->distinct()->pluck('company_id')->filter()->all();
        foreach ($ids as $id) {
            $this->reclusterCompany((int) $id);
        }
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $text): array
    {
        $text = strtolower($text);
        $parts = preg_split('/[^a-z0-9]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($parts as $p) {
            if (strlen($p) < 2) {
                continue;
            }
            if (isset(self::$stopwords[$p])) {
                continue;
            }
            $out[] = $p;
        }

        return $out;
    }

    /**
     * @param  array<int, list<string>>  $docTokens
     * @return list<string>
     */
    private function topVocabulary(array $docTokens, int $maxTerms): array
    {
        $freq = [];
        foreach ($docTokens as $toks) {
            foreach ($toks as $t) {
                $freq[$t] = ($freq[$t] ?? 0) + 1;
            }
        }
        arsort($freq);
        $keys = array_keys($freq);

        return array_slice($keys, 0, $maxTerms);
    }

    /**
     * @param  list<string>  $vocabList
     * @param  array<string, float>  $idf
     * @return list<float>
     */
    private function denseTfidf(array $tokens, array $vocabList, array $idf): array
    {
        $tf = array_fill_keys($vocabList, 0.0);
        $len = count($tokens);
        if ($len === 0) {
            return array_values(array_fill(0, count($vocabList), 0.0));
        }
        foreach ($tokens as $t) {
            if (isset($tf[$t])) {
                $tf[$t] += 1.0;
            }
        }
        foreach ($vocabList as $t) {
            $tf[$t] = ($tf[$t] / $len) * ($idf[$t] ?? 1.0);
        }
        $vec = [];
        foreach ($vocabList as $t) {
            $vec[] = (float) $tf[$t];
        }
        $norm = 0.0;
        foreach ($vec as $v) {
            $norm += $v * $v;
        }
        $norm = sqrt($norm) ?: 1.0;
        foreach ($vec as $i => $v) {
            $vec[$i] = $v / $norm;
        }

        return $vec;
    }

    private function pickK(int $n): int
    {
        if ($n < 4) {
            return 1;
        }

        return (int) max(2, min(8, (int) round(sqrt($n))));
    }

    /**
     * @param  array<int, list<float>>  $vectors
     * @param  list<int>  $ids
     * @return array<int, int> complaintId => clusterIndex
     */
    private function kMeansDense(array $vectors, array $ids, int $k, int $maxIter): array
    {
        $n = count($ids);
        if ($k >= $n) {
            $out = [];
            foreach ($ids as $i => $id) {
                $out[$id] = $i;
            }

            return $out;
        }

        $picked = $ids;
        shuffle($picked);
        $centroids = [];
        for ($i = 0; $i < $k; $i++) {
            $centroids[] = $vectors[$picked[$i]];
        }

        $assignment = [];
        for ($iter = 0; $iter < $maxIter; $iter++) {
            $changed = false;
            foreach ($ids as $id) {
                $best = 0;
                $bestSim = -INF;
                $v = $vectors[$id];
                for ($c = 0; $c < $k; $c++) {
                    $sim = $this->dot($v, $centroids[$c]);
                    if ($sim > $bestSim) {
                        $bestSim = $sim;
                        $best = $c;
                    }
                }
                if (! isset($assignment[$id]) || $assignment[$id] !== $best) {
                    $changed = true;
                }
                $assignment[$id] = $best;
            }

            $sums = array_fill(0, $k, null);
            $counts = array_fill(0, $k, 0);
            foreach ($ids as $id) {
                $c = $assignment[$id];
                $counts[$c]++;
                if ($sums[$c] === null) {
                    $sums[$c] = $vectors[$id];
                } else {
                    $sums[$c] = $this->vecAdd($sums[$c], $vectors[$id]);
                }
            }
            for ($c = 0; $c < $k; $c++) {
                if ($counts[$c] > 0) {
                    $centroids[$c] = $this->vecScale($sums[$c], 1.0 / $counts[$c]);
                }
            }

            if (! $changed) {
                break;
            }
        }

        return $assignment;
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    private function dot(array $a, array $b): float
    {
        $s = 0.0;
        $len = min(count($a), count($b));
        for ($i = 0; $i < $len; $i++) {
            $s += $a[$i] * $b[$i];
        }

        return $s;
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     * @return list<float>
     */
    private function vecAdd(array $a, array $b): array
    {
        $out = [];
        $len = min(count($a), count($b));
        for ($i = 0; $i < $len; $i++) {
            $out[] = $a[$i] + $b[$i];
        }

        return $out;
    }

    /**
     * @param  list<float>  $a
     * @return list<float>
     */
    private function vecScale(array $a, float $s): array
    {
        $out = [];
        foreach ($a as $v) {
            $out[] = $v * $s;
        }
        $norm = 0.0;
        foreach ($out as $v) {
            $norm += $v * $v;
        }
        $norm = sqrt($norm) ?: 1.0;
        foreach ($out as $i => $v) {
            $out[$i] = $v / $norm;
        }

        return $out;
    }

    /**
     * @param  list<string>  $topKeys
     */
    private function titleFromKeywords(array $topKeys, $memberComplaints): string
    {
        $cats = $memberComplaints->pluck('category')->filter()->countBy();
        $topCat = $cats->sortDesc()->keys()->first();
        $label = match ($topCat) {
            'payment' => 'Payment / billing issue',
            'delivery' => 'Delivery / logistics issue',
            'service' => 'Customer service issue',
            default => 'Recurring complaint pattern',
        };
        if ($topKeys !== []) {
            $kw = implode(', ', array_slice($topKeys, 0, 3));

            return $label.' ('.$kw.')';
        }

        return $label;
    }

    private function rebuildTimeseries(int $companyId): void
    {
        $clusters = IssueCluster::where('company_id', $companyId)->get();
        foreach ($clusters as $cl) {
            $rows = Complaint::query()
                ->where('issue_cluster_id', $cl->id)
                ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
                ->groupBy('d')
                ->orderBy('d')
                ->get();

            foreach ($rows as $row) {
                IssueTimeseries::updateOrCreate(
                    [
                        'issue_cluster_id' => $cl->id,
                        'bucket_date' => $row->d,
                    ],
                    ['count' => (int) $row->c]
                );
            }
        }
    }

    private function generateAlerts(int $companyId): void
    {
        $clusters = IssueCluster::where('company_id', $companyId)->get();
        foreach ($clusters as $cl) {
            $series = IssueTimeseries::where('issue_cluster_id', $cl->id)->orderBy('bucket_date')->get();
            if ($series->isEmpty()) {
                continue;
            }
            $counts = $series->pluck('count')->map(fn ($c) => (int) $c)->all();
            $avg = array_sum($counts) / max(1, count($counts));
            $max = max($counts);
            $last = (int) $series->last()->count;
            $lastDate = $series->last()->bucket_date;

            // Lower thresholds so demo / low-volume tenants still get actionable signals.
            if ($max >= max(2.0, $avg * 2.0 + 0.5)) {
                Alert::create([
                    'company_id' => $companyId,
                    'issue_cluster_id' => $cl->id,
                    'title' => 'Spike Detected: '.$cl->title,
                    'body' => sprintf(
                        'Complaints For This Pattern Peaked At %d In A Single Day (Avg %.1f). Review Operations And Customer Communications.',
                        $max,
                        $avg
                    ),
                    'severity' => 'critical',
                    'is_read' => false,
                    'triggered_at' => Carbon::parse($lastDate)->endOfDay(),
                ]);
            } elseif ($last >= max(1, ceil($avg * 1.2))) {
                Alert::create([
                    'company_id' => $companyId,
                    'issue_cluster_id' => $cl->id,
                    'title' => 'Elevated Volume: '.$cl->title,
                    'body' => 'Recent Day Counts Are Above The Rolling Average. Monitor And Assign Ownership.',
                    'severity' => 'warning',
                    'is_read' => false,
                    'triggered_at' => now(),
                ]);
            } else {
                // Baseline signal so Smart Alerts is never empty on Premium after clustering.
                Alert::create([
                    'company_id' => $companyId,
                    'issue_cluster_id' => $cl->id,
                    'title' => 'Monitoring Active: '.$cl->title,
                    'body' => 'This Pattern Is Being Tracked. Escalations Appear Automatically When Volume Crosses Thresholds.',
                    'severity' => 'info',
                    'is_read' => false,
                    'triggered_at' => now(),
                ]);
            }
        }
    }
}
