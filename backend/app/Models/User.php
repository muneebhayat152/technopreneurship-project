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
        'role',
        'access_tier',
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

    /**
     * Effective plan tier: explicit access_tier overrides organization subscription.
     * Null access_tier means inherit from company.subscription.
     */
    public function computeEffectiveAccessTier(): string
    {
        if ($this->role === 'super_admin') {
            return 'premium';
        }

        if ($this->access_tier === 'free' || $this->access_tier === 'premium') {
            return $this->access_tier;
        }

        $company = $this->relationLoaded('company') ? $this->company : null;
        if (! $company && $this->company_id) {
            $company = Company::query()->find($this->company_id);
        }

        return ($company && $company->subscription === 'premium') ? 'premium' : 'free';
    }

    public function hasPremiumFeatures(): bool
    {
        return $this->computeEffectiveAccessTier() === 'premium';
    }
}
