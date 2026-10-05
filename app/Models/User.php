<?php

namespace App\Models;

use App\Auth\Permission;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'role', 'status', 'provider_type',
        'last_name', 'first_name', 'middle_initial', 'sex',
        'email', 'password', 'contact_no', 'birthday', 'age',
        'province', 'municipality', 'barangay', 'street', 'house_no',
        'municipality_id', 'barangay_id',
        'id_upload',
        // Seller
        'business_name', 'line_of_business', 'business_permit',
        // Courier
        'vehicle_type', 'plate_number', 'or_cr_upload', 'delivery_area',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['birthday' => 'date', 'email_verified_at' => 'datetime'];

    /**
     * Context for the next status change (not persisted on users). Services set these before
     * update(); the history row below picks them up, so every status change is recorded
     * wherever it happens — Admin, Logistics portal or registration review.
     */
    public ?string $statusChangeReason = null;
    public ?int $statusChangedBy = null;

    protected static function booted(): void
    {
        static::updated(function (User $user): void {
            if (! $user->wasChanged('status')) {
                return;
            }

            $changedBy = $user->statusChangedBy ?? auth()->id();
            if ($changedBy === null) {
                // No acting user (console, seeders): nothing can satisfy changed_by, so log instead.
                logger()->info('User status changed without an acting user.', [
                    'user_id' => $user->id, 'from' => $user->getOriginal('status'), 'to' => $user->status,
                ]);
            } else {
                UserStatusHistory::create([
                    'user_id' => $user->id,
                    'changed_by' => $changedBy,
                    'from_status' => $user->getOriginal('status'),
                    'to_status' => $user->status,
                    'reason' => $user->statusChangeReason,
                ]);
            }

            $user->statusChangeReason = null;
            $user->statusChangedBy = null;
        });
    }

    public function getFullNameAttribute(): string
    {
        $mi = $this->middle_initial ? " {$this->middle_initial}." : '';
        return "{$this->first_name}{$mi} {$this->last_name}";
    }

    public function getAgeAttribute(): ?int
    {
        return $this->birthday ? $this->birthday->age : null;
    }

    public function isApproved(): bool { return $this->status === 'approved'; }
    public function isPending(): bool  { return $this->status === 'pending'; }
    public function isDeactivated(): bool { return $this->status === 'deactivated'; }

    /** The single Super Admin role; holds every admin permission (see config/permissions.php). */
    public function isSuperAdmin(): bool { return $this->role === 'admin'; }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, Permission::forRole($this->role), true);
    }

    public function registrationReviews() { return $this->hasMany(RegistrationReview::class)->latest('reviewed_at')->latest('id'); }
    public function latestRegistrationReview() { return $this->hasOne(RegistrationReview::class)->latestOfMany('reviewed_at'); }

    public function sentMessages()     { return $this->hasMany(Message::class, 'sender_id'); }
    public function receivedMessages() { return $this->hasMany(Message::class, 'receiver_id'); }
    public function statusHistories()  { return $this->hasMany(UserStatusHistory::class)->latest()->latest('id'); }
    public function complaints()       { return $this->hasMany(Complaint::class, 'filed_by'); }
    public function complaintsAgainst() { return $this->hasMany(Complaint::class, 'against_user_id'); }
    public function complianceCases()   { return $this->hasMany(ComplianceCase::class, 'seller_id'); }
    public function complianceActions() { return $this->hasMany(ComplianceAction::class, 'seller_id'); }
    public function ordersAsBuyer()    { return $this->hasMany(Order::class, 'buyer_id'); }
    public function ordersAsSeller()   { return $this->hasMany(Order::class, 'seller_id'); }
    public function ordersAsCourier()  { return $this->hasMany(Order::class, 'courier_id'); }
    public function products()         { return $this->hasMany(\App\Models\Product::class, 'seller_id'); }
    public function municipality()      { return $this->belongsTo(Municipality::class); }
    public function barangay()          { return $this->belongsTo(Barangay::class); }
    public function branchAssignments() { return $this->hasMany(BranchRider::class); }
}
