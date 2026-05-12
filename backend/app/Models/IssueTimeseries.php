<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssueTimeseries extends Model
{
    protected $table = 'issue_timeseries';

    protected $fillable = [
        'issue_cluster_id',
        'bucket_date',
        'count',
    ];

    protected $casts = [
        'bucket_date' => 'date',
    ];

    public function issueCluster(): BelongsTo
    {
        return $this->belongsTo(IssueCluster::class, 'issue_cluster_id');
    }
}
