<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Models\Company;

class Complaint extends Model
{
    use HasFactory;

    /**
     * 🔐 Mass assignable fields
     */
    protected $fillable = [
        'company_id',
        'user_id',
        'complaint_text',
        'sentiment',
        'category',
        'status',
        'priority',
        'issue_cluster_id',
        'created_at',
        'updated_at',
    ];

    /**
     * 👤 Complaint belongs to User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 🏢 Complaint belongs to Company
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function issueCluster()
    {
        return $this->belongsTo(IssueCluster::class);
    }
}