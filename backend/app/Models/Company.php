<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class Company extends Model
{
    use HasFactory;

    // Allow mass assignment
    protected $fillable = [
        'name',
        'email',
        'subscription',
        'is_active',
        'industry',
        'country'
    ];

    /**
     * A company has many users
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function issueClusters()
    {
        return $this->hasMany(IssueCluster::class);
    }
}