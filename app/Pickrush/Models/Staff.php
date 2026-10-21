<?php

namespace App\Pickrush\Models;

use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Support\Audited;
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
