<?php

namespace App\Pickrush\Models;

use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Support\Actor;
use App\Pickrush\Support\Audited;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;

/** A Pickrush employee who signs in to the web portal (HQ, province hub or station hub). */
class Staff extends Authenticatable
{
    use Audited;

    protected $table = 'pr_staff';

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'role' => StaffRole::class,
        'is_active' => 'boolean',
        'is_sample' => 'boolean',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * No global company scope here: staff are what sign in, and scoping the sign-in lookup would
     * ask who is signed in. Lists of staff use ofCompany() instead.
     */
    protected static function booted(): void
    {
        static::creating(function (self $staff) {
            $staff->company_id ??= Actor::companyId()
                ?? ($staff->hub_id ? Hub::withoutGlobalScopes()->whereKey($staff->hub_id)->value('company_id') : null)
                ?? Company::defaultId();
        });
    }

    public function scopeOfCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('pr_staff.company_id', $companyId);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function hub()
    {
        return $this->belongsTo(Hub::class, 'hub_id');
    }

    public function isCompany(): bool
    {
        return $this->role->isCompany();
    }

    public function hasRole(StaffRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }
}
