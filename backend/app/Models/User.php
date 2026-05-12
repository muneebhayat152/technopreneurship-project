<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes; // ✅ ADDED
use App\Models\Company;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes; // ✅ UPDATED

    /**
     * 🔐 Mass Assignable Fields
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'company_id',
        'role'
    ];

    /**
     * 🙈 Hidden Fields
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * 🔄 Casts
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * 🏢 Relationship: User belongs to Company
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
