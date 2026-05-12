<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IssueCluster extends Model
{
    protected $fillable = [
        'company_id',
        'title',
        'keywords',
        'severity',
        'status',
        'complaint_count',
    ];

    protected $casts = [
        'keywords' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function timeseries(): HasMany
    {
        return $this->hasMany(IssueTimeseries::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }
}
