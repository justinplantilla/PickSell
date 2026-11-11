<?php

namespace App\Services\OffPlatform;

use App\Models\ComplianceCase;
use App\Models\Message;
use App\Models\OffPlatformFlag;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductModerationLog;
use App\Models\User;
use App\Notifications\ProductModeratedNotification;
use App\Services\Admin\ComplianceService;
use App\Services\Admin\ProductModerationService;
use App\Services\AdminNotificationService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;
use WeakMap;

/**
 * Keeps sales on PickSell. Chat between a buyer and a seller has contact details masked before
 * it is saved, and the attempt is flagged; a listing that carries contact details or an outside
 * courier is held (archived, admin-only restore) until an admin approves it; orders cancelled
 * right after such a chat, waybills never handed to the carrier and high cancellation rates are
 * flagged by a scheduled scan; buyers can report a seller who asked them to deal outside.
 * Admins review the flags: dismiss, warn (a strike), open a compliance case or suspend. The
 * system only flags and recommends; every penalty is an admin's decision.
 */
class OffPlatformMonitor
{
    /** Findings for messages being saved, kept off the model's attributes. */
    private static ?WeakMap $pending = null;

    public function __construct(
        private OffPlatformDetector $detector,
        private AdminNotificationService $notifications,
    ) {}

    /** Message::creating — mask contact details in a buyer–seller chat message. */
    public function masking(Message $message): void
    {
        if (str_starts_with((string) $message->body, Message::AUTO_REPLY_PREFIX) || ! $this->conversation($message)) {
            return;
        }
        $findings = $this->detector->scan($message->body);
        if ($findings === []) {
            return;
        }
        self::$pending ??= new WeakMap;
        self::$pending[$message] = $findings;
        $message->body = $this->detector->mask($message->body);
    }

    /** Message::created — flag what was found, once per message. */
    public function messageSaved(Message $message): void
    {
        $findings = self::$pending[$message] ?? null;
        if (! $findings || ! ($pair = $this->conversation($message))) {
            return;
        }
        unset(self::$pending[$message]);
        [$seller, $buyer] = $pair;
        $top = $this->strongest($findings);
        $this->flag([
            'seller_id' => $seller->id, 'buyer_id' => $buyer->id, 'kind' => 'chat', 'signal' => $top['signal'],
            'message_id' => $message->id, 'product_id' => $message->product_id,
            'evidence' => ($message->sender_id === $seller->id ? 'Seller' : 'Buyer').' wrote: '.$this->summary($findings),
        ]);
    }

    /** Product::saved — hold a listing whose name or description invites an off-platform deal. */
    public function productSaved(Product $product): void
    {
        // New, edited, or relisted by the seller (an archived copy edited and then restored).
        // An admin restoring a held listing has approved its text, so that is not re-checked.
        if (! $product->wasRecentlyCreated && ! $product->wasChanged(['name', 'description', 'status'])) {
            return;
        }
        if (! $product->wasRecentlyCreated && auth()->user()?->can(\App\Auth\Permission::PRODUCTS_MODERATE)) {
            return;
        }
        $findings = $this->detector->scan($product->name."\n".$product->description);
        // Only contact details, outside payment and couriers hold a listing; a stray phrase does not.
        $findings = array_values(array_filter($findings, fn ($f) => ! in_array($f['signal'], ['phrase', 'keyword'], true)));
        // A new product's status comes from the column default, so it is not on the model yet.
        if ($findings === [] || ($product->status ?? 'active') !== 'active') {
            return;
        }

        $log = DB::transaction(function () use ($product, $findings) {
            $product->updateQuietly(['status' => 'archived', 'is_featured' => false]);
            $log = $product->moderationLogs()->create([
                'admin_id' => null, 'action' => ProductModerationLog::ARCHIVED, 'from_status' => 'active', 'to_status' => 'archived',
                'reason' => 'Held automatically: the listing has contact details, outside payment or an outside courier ('.$this->summary($findings, false).'). Remove them and PickSell will review it.',
            ]);
            $this->flag([
                'seller_id' => $product->seller_id, 'kind' => 'product', 'signal' => $this->strongest($findings)['signal'],
                'product_id' => $product->id, 'evidence' => 'Listing: '.$this->summary($findings),
            ]);

            return $log;
        });
        $product->setRawAttributes($product->fresh()->getAttributes(), true);

        try {
            $product->seller?->notify(new ProductModeratedNotification($product, $log));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** A buyer reports that the seller of their order asked them to deal outside PickSell. */
    public function buyerReport(User $buyer, Order $order, string $details): OffPlatformFlag
    {
        if ($order->buyer_id !== $buyer->id) {
            throw new DomainException('You can only report your own orders.');
        }
        if (OffPlatformFlag::where('signal', 'buyer_report')->where('order_id', $order->id)->exists()) {
            throw new DomainException('You already reported this. PickSell is looking into it.');
        }

        return $this->flag([
            'seller_id' => $order->seller_id, 'buyer_id' => $buyer->id, 'kind' => 'report', 'signal' => 'buyer_report',
            'order_id' => $order->id, 'product_id' => $order->product_id, 'evidence' => $details,
        ], priority: 'critical');
    }

    /**
     * Scheduled: orders cancelled within 48 hours of a flagged chat between the same buyer and
     * seller; waybills printed but not handed over and then cancelled; sellers whose last 30
     * days' cancellations are 30% or more of at least 5 orders.
     *
     * @return int flags raised
     */
    public function scanOrders(): int
    {
        $raised = 0;
        $since = now()->subDays(7);

        Order::query()->where('status', 'cancelled')->where('updated_at', '>=', $since)
            ->whereNotExists(fn ($q) => $q->from('off_platform_flags')->whereColumn('off_platform_flags.order_id', 'orders.id')->where('signal', 'cancel_after_contact'))
            ->chunkById(200, function ($orders) use (&$raised) {
                foreach ($orders as $order) {
                    $chat = OffPlatformFlag::where('kind', 'chat')->where('seller_id', $order->seller_id)->where('buyer_id', $order->buyer_id)
                        ->whereBetween('created_at', [$order->updated_at->copy()->subHours(48), $order->updated_at])->latest()->first();
                    if ($chat) {
                        $raised += (int) (bool) $this->flag([
                            'seller_id' => $order->seller_id, 'buyer_id' => $order->buyer_id, 'kind' => 'order', 'signal' => 'cancel_after_contact',
                            'order_id' => $order->id, 'product_id' => $order->product_id,
                            'evidence' => "Order {$order->order_number} was cancelled {$chat->created_at->diffForHumans($order->updated_at, true)} after: {$chat->evidence}",
                        ], notify: false);
                    }
                }
            });

        Order::query()->where('status', 'cancelled')->where('updated_at', '>=', $since)->whereNotNull('waybill_number')
            ->whereNotExists(fn ($q) => $q->from('order_status_histories')->whereColumn('order_status_histories.order_id', 'orders.id')->where('to_status', 'picked_up'))
            ->whereNotExists(fn ($q) => $q->from('off_platform_flags')->whereColumn('off_platform_flags.order_id', 'orders.id')->where('signal', 'never_picked_up'))
            ->chunkById(200, function ($orders) use (&$raised) {
                foreach ($orders as $order) {
                    $raised += (int) (bool) $this->flag([
                        'seller_id' => $order->seller_id, 'buyer_id' => $order->buyer_id, 'kind' => 'order', 'signal' => 'never_picked_up',
                        'order_id' => $order->id, 'product_id' => $order->product_id,
                        'evidence' => "Waybill {$order->waybill_number} was printed for {$order->order_number}, but the parcel was never picked up before the order was cancelled.",
                    ], notify: false);
                }
            });

        $rates = Order::query()->where('created_at', '>=', now()->subDays(30))
            ->selectRaw("seller_id, count(*) as total, sum(case when status = 'cancelled' then 1 else 0 end) as cancelled")
            ->groupBy('seller_id')->havingRaw('count(*) >= 5')->get();
        foreach ($rates as $row) {
            $rate = (int) $row->cancelled / max(1, (int) $row->total);
            $recent = OffPlatformFlag::where('seller_id', $row->seller_id)->where('signal', 'high_cancellation')->where('created_at', '>=', now()->subDays(30))->exists();
            if ($rate >= 0.3 && ! $recent) {
                $this->flag([
                    'seller_id' => $row->seller_id, 'kind' => 'order', 'signal' => 'high_cancellation',
                    'evidence' => sprintf('%d of %d orders in the last 30 days were cancelled (%d%%).', $row->cancelled, $row->total, round($rate * 100)),
                ], notify: false);
                $raised++;
            }
        }

        return $raised;
    }

    /** @return array{score: int, level: string, strikes: int, open: int, recommended: string} */
    public function risk(User $seller): array
    {
        $score = (int) OffPlatformFlag::where('seller_id', $seller->id)->counting()->sum('weight');
        $strikes = $this->strikes($seller);

        return [
            'score' => $score,
            'level' => $score >= 12 ? 'high' : ($score >= 5 ? 'medium' : 'low'),
            'strikes' => $strikes,
            'open' => OffPlatformFlag::where('seller_id', $seller->id)->open()->count(),
            'recommended' => match (true) {
                $strikes >= 2 => 'suspend',
                $strikes === 1 => 'case',
                default => 'warn',
            },
        ];
    }

    public function strikes(User $seller): int
    {
        return OffPlatformFlag::where('seller_id', $seller->id)->whereIn('action', OffPlatformFlag::STRIKE_ACTIONS)
            ->where('reviewed_at', '>=', now()->subDays(180))->distinct()->count('reviewed_at');
    }

    public function dismiss(OffPlatformFlag $flag, User $admin, ?string $note): void
    {
        $this->review([$flag->id], $admin, 'dismissed', null, $note);
    }

    /** Warn the seller (a strike) and close their open flags with it. */
    public function warn(User $seller, User $admin, string $message): void
    {
        app(ComplianceService::class)->warn($seller, $admin, $message);
        $this->review($this->openIds($seller), $admin, 'actioned', 'warned', $message);
    }

    public function openCase(User $seller, User $admin, string $description, string $severity): ComplianceCase
    {
        $case = app(ComplianceService::class)->openCase($seller, $admin, ['type' => 'off_platform', 'severity' => $severity, 'description' => $description]);
        $this->review($this->openIds($seller), $admin, 'actioned', 'case_opened', "Compliance case #{$case->id}: {$description}");

        return $case;
    }

    public function suspend(User $seller, User $admin, string $reason): void
    {
        app(ComplianceService::class)->suspend($seller, $admin, $reason);
        $this->review($this->openIds($seller), $admin, 'actioned', 'suspended', $reason);
    }

    /** The listing is fine after all (or was fixed): restore it and close its flag. */
    public function restoreListing(OffPlatformFlag $flag, User $admin, ?string $note): void
    {
        if (! $flag->product || $flag->product->status !== 'archived') {
            throw new DomainException('This listing is not held.');
        }
        app(ProductModerationService::class)->setStatus($flag->product, $admin, 'active', $note ?: 'Reviewed: no off-platform details.');
        $this->review([$flag->id], $admin, 'actioned', 'listing_restored', $note);
    }

    private function review(array $ids, User $admin, string $status, ?string $action, ?string $note): void
    {
        OffPlatformFlag::whereIn('id', $ids)->update([
            'status' => $status, 'action' => $action, 'review_note' => $note, 'reviewed_by' => $admin->id, 'reviewed_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function openIds(User $seller): array
    {
        return OffPlatformFlag::where('seller_id', $seller->id)->open()->pluck('id')->all();
    }

    private function flag(array $attributes, bool $notify = true, string $priority = 'normal'): ?OffPlatformFlag
    {
        try {
            $flag = OffPlatformFlag::create($attributes + ['weight' => OffPlatformFlag::weightOf($attributes['signal'])]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return null; // this order signal was raised already
        }

        if ($notify) {
            $seller = $flag->seller;
            $this->notifications->notifyAdmins('off_platform.flagged', 'Off-platform signal: '.$flag->label(),
                ($seller?->business_name ?: $seller?->full_name ?: 'A seller').': '.Str::limit((string) $flag->evidence, 120),
                route('admin.off-platform.seller', $flag->seller_id, false), priority: $priority);
        }

        return $flag;
    }

    /** @return array{0: User, 1: User}|null [seller, buyer] when the message is between a seller and a buyer */
    private function conversation(Message $message): ?array
    {
        $people = User::whereIn('id', [$message->sender_id, $message->receiver_id])->get(['id', 'role'])->keyBy('role');

        return $people->has('seller') && $people->has('buyer') ? [$people['seller'], $people['buyer']] : null;
    }

    /** @param  list<array{signal: string, match: string}>  $findings */
    private function strongest(array $findings): array
    {
        return collect($findings)->sortByDesc(fn ($f) => OffPlatformFlag::weightOf($f['signal']))->first();
    }

    private function summary(array $findings, bool $withValues = true): string
    {
        return collect($findings)->map(fn ($f) => (OffPlatformFlag::SIGNALS[$f['signal']][0] ?? $f['signal']).($withValues ? ' "'.Str::limit($f['match'], 60).'"' : ''))
            ->unique()->join('; ');
    }
}
