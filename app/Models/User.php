<?php

namespace App\Models;

use App\Auth\Permission;
use App\Services\Geo\AddressResolver;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    // HasApiTokens: buyer mobile app logins (routes/buyer-api.php).
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'role', 'status',
        'last_name', 'first_name', 'middle_initial', 'sex',
        'email', 'password', 'contact_no', 'birthday', 'age',
        'province', 'municipality', 'barangay', 'street', 'house_no', 'postal_code',
        'pickup_lat', 'pickup_lng',     // seller's shop pin for Pickrush riders
        'delivery_lat', 'delivery_lng', // buyer's delivery pin for Pickrush riders
        'id_upload',
        // Seller
        'business_name', 'line_of_business', 'business_permit',
        'seller_type', 'tin', 'dti_certificate', 'sec_certificate', 'bir_certificate',
        'vacation_mode', 'vacation_started_at', 'vacation_ends_at', 'vacation_message',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'birthday' => 'date', 'email_verified_at' => 'datetime',
        'vacation_mode' => 'boolean', 'vacation_started_at' => 'datetime', 'vacation_ends_at' => 'datetime',
    ];

    /** Vacation days a seller may use per calendar month, across all vacations (see SellerVacations). */
    public const MAX_VACATION_DAYS_PER_MONTH = 7;

    /** Sent once per buyer per vacation when the seller left vacation_message blank; %s is the return date. */
    public const DEFAULT_VACATION_MESSAGE = "Thank you for your message! I'm on vacation until %s and not taking new orders. I'll get back to you as soon as I'm back.";

    /** Sent to a buyer who messages a seller, at most once per AUTO_REPLY_QUIET_HOURS. */
    public const DEFAULT_THANK_YOU_MESSAGE = "Thank you for your message! I'll get back to you as soon as I can.";

    public const AUTO_REPLY_QUIET_HOURS = 24;

    /** Order statuses still waiting on the seller (before rider pickup): vacation mode stays off while any exist. */
    public const SELLER_UNSHIPPED_STATUSES = ['pending', 'placed', 'confirmed', 'preparing', 'processing', 'ready_for_pickup'];

    /**
     * Context for the next status change (not persisted on users). Services set these before
     * update(); the history row below picks them up, so every status change is recorded
     * wherever it happens — Admin or registration review.
     */
    public ?string $statusChangeReason = null;

    public ?int $statusChangedBy = null;

    protected static function booted(): void
    {
        // Keep the PSGC codes in step with the address names, however the address was saved
        // (registration, account page, mobile API). Delivery routing uses the codes.
        static::saving(function (User $user): void {
            if ($user->isDirty(['province', 'municipality', 'barangay'])) {
                $user->forceFill(app(AddressResolver::class)->resolve($user->province, $user->municipality, $user->barangay));
            }
        });

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

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isDeactivated(): bool
    {
        return $this->status === 'deactivated';
    }

    /** Buyers' ratings of this seller's service: average (1 decimal) and how many. */
    public function sellerRating(): array
    {
        $rated = Order::where('seller_id', $this->id)->whereNotNull('seller_rating');

        return ['average' => round((float) (clone $rated)->avg('seller_rating'), 1), 'count' => $rated->count()];
    }

    /** Away until vacation_ends_at; past it the seller is back even if vacation_mode is not cleared yet. */
    public function isOnVacation(): bool
    {
        return $this->role === 'seller' && $this->vacation_mode
            && ($this->vacation_ends_at === null || $this->vacation_ends_at->isFuture());
    }

    /** Sellers (in a whereHas on the seller) who are taking orders: not on vacation, or past its end. */
    public function scopeTakingOrders($query)
    {
        return $query->where(fn ($seller) => $seller->where('vacation_mode', false)
            ->orWhere(fn ($ended) => $ended->whereNotNull('vacation_ends_at')->where('vacation_ends_at', '<=', now())));
    }

    public function vacationAutoReply(): string
    {
        return $this->vacation_message ?: sprintf(self::DEFAULT_VACATION_MESSAGE, $this->vacation_ends_at?->format('M j') ?? 'further notice');
    }

    /** The single Super Admin role; holds every admin permission (see config/permissions.php). */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, Permission::forRole($this->role), true);
    }

    public function registrationReviews()
    {
        return $this->hasMany(RegistrationReview::class)->latest('reviewed_at')->latest('id');
    }

    public function latestRegistrationReview()
    {
        return $this->hasOne(RegistrationReview::class)->latestOfMany('reviewed_at');
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages()
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    public function statusHistories()
    {
        return $this->hasMany(UserStatusHistory::class)->latest()->latest('id');
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class, 'filed_by');
    }

    public function complaintsAgainst()
    {
        return $this->hasMany(Complaint::class, 'against_user_id');
    }

    public function complianceCases()
    {
        return $this->hasMany(ComplianceCase::class, 'seller_id');
    }

    public function complianceActions()
    {
        return $this->hasMany(ComplianceAction::class, 'seller_id');
    }

    public function ordersAsBuyer()
    {
        return $this->hasMany(Order::class, 'buyer_id');
    }

    public function ordersAsSeller()
    {
        return $this->hasMany(Order::class, 'seller_id');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }

    public function financialTransactions()
    {
        return $this->hasMany(FinancialTransaction::class, 'seller_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'seller_id');
    }

    /** Buyers following this seller's store. */
    public function followers()
    {
        return $this->belongsToMany(User::class, 'seller_follows', 'seller_id', 'buyer_id')->withTimestamps();
    }

    /** A buyer's extra delivery addresses; the profile address is the default. */
    public function addresses()
    {
        return $this->hasMany(BuyerAddress::class)->orderBy('id');
    }

    /** Stores this buyer follows. */
    public function followedSellers()
    {
        return $this->belongsToMany(User::class, 'seller_follows', 'buyer_id', 'seller_id')->withTimestamps();
    }

    public function wishlistItems()
    {
        return $this->hasMany(WishlistItem::class);
    }

    public function recentlyViewedProducts()
    {
        return $this->hasMany(RecentlyViewedProduct::class);
    }
}
