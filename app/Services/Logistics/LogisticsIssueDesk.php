<?php

namespace App\Services\Logistics;

use App\Models\LogisticsClaim;
use App\Models\LogisticsIssue;
use App\Models\Order;
use App\Models\User;
use App\Pickrush\Bridge\PicksellBridge;
use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\ParcelException;
use App\Pickrush\Models\Staff;
use App\Services\AdminNotificationService;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Where admin follows up problems with the registered logistics companies. Issues open by
 * themselves from the companies' parcel exceptions and from lost or returned parcels; buyers
 * and sellers report the rest from their order page. Admin notes, escalates to the company
 * (which replies from its portal) and resolves with who was at fault. A carrier-fault loss or
 * damage charges a claim (item value + delivery fee, capped) against the company's share.
 */
class LogisticsIssueDesk
{
    public function __construct(private AdminNotificationService $notifications, private AuditLogger $audit) {}

    /** A hub raised an exception: mirror it as an issue (once). */
    public function fromException(ParcelException $exception): LogisticsIssue
    {
        $existing = LogisticsIssue::where('exception_id', $exception->id)->first();
        if ($existing) {
            return $existing;
        }
        $parcel = $exception->parcel;
        $companyId = $parcel?->company_id ?? $exception->hub()->withoutGlobalScopes()->value('company_id');

        return $this->open([
            'company_id' => $companyId,
            'parcel_id' => $parcel?->id,
            'exception_id' => $exception->id,
            'source' => 'exception',
            'type' => $exception->type,
            'reporter_role' => 'logistics',
            'description' => $exception->note,
        ] + $this->orderLinks($parcel), notify: false);
    }

    /** The company resolved its own exception: the mirrored issue is closed with its note. */
    public function exceptionResolved(ParcelException $exception): void
    {
        $issue = LogisticsIssue::where('exception_id', $exception->id)->unresolved()->first();
        if (! $issue) {
            return;
        }
        $resolution = \App\Pickrush\Services\ExceptionDesk::ACTIONS[$exception->type][$exception->resolution] ?? $exception->resolution;
        DB::transaction(function () use ($issue, $exception, $resolution) {
            $issue->update(['status' => 'resolved', 'resolved_at' => now(), 'resolution_notes' => trim("{$resolution}. {$exception->resolution_note}")]);
            $issue->events()->create(['staff_id' => $exception->resolved_by, 'action' => 'company_resolved', 'note' => trim("{$resolution}. {$exception->resolution_note}")]);
        });
    }

    /** A PickSell parcel was lost or came back undelivered. */
    public function fromParcelStatus(Parcel $parcel, ParcelStatus $status): ?LogisticsIssue
    {
        $type = match ($status) {
            ParcelStatus::Lost => 'lost',
            ParcelStatus::ReturnedToSender => 'returned',
            default => null,
        };
        if (! $type || $parcel->source !== PicksellBridge::SOURCE) {
            return null;
        }
        if ($existing = LogisticsIssue::where('parcel_id', $parcel->id)->where('type', $type)->first()) {
            return $existing;
        }

        return $this->open([
            'company_id' => $parcel->company_id,
            'parcel_id' => $parcel->id,
            'source' => 'parcel',
            'type' => $type,
            'reporter_role' => 'system',
            'description' => $type === 'lost'
                ? "The logistics company marked parcel {$parcel->tracking_no} as lost."
                : "Parcel {$parcel->tracking_no} could not be delivered and went back to the seller.",
        ] + $this->orderLinks($parcel), priority: $type === 'lost' ? 'critical' : 'normal');
    }

    /** The seller handed the parcel over but the courier has not picked it up in time (once per order). */
    public function pickupOverdue(Order $order): ?LogisticsIssue
    {
        $parcel = PicksellBridge::parcelFor($order);
        $companyId = $order->shipment?->logistics_company_id ?? $parcel?->company_id;
        if (! $companyId || LogisticsIssue::where('order_id', $order->id)->where('type', 'pickup_missed')->exists()) {
            return null;
        }

        return $this->open([
            'company_id' => $companyId,
            'order_id' => $order->id,
            'order_shipment_id' => $order->order_shipment_id,
            'parcel_id' => $parcel?->id,
            'source' => 'parcel',
            'type' => 'pickup_missed',
            'reporter_role' => 'system',
            'description' => "The seller handed order {$order->order_number} over {$order->handed_over_at?->diffForHumans()}, but the courier has not picked it up.",
        ]);
    }

    /** A buyer or seller reports a delivery problem on their order. */
    public function report(User $reporter, Order $order, string $type, string $description, ?UploadedFile $evidence = null): LogisticsIssue
    {
        $role = $order->buyer_id === $reporter->id ? 'buyer' : ($order->seller_id === $reporter->id ? 'seller' : null);
        $allowed = $role === 'buyer' ? LogisticsIssue::BUYER_REPORTS : LogisticsIssue::SELLER_REPORTS;
        if (! $role || ! in_array($type, $allowed, true)) {
            throw new DomainException('That problem cannot be reported on this order.');
        }
        $companyId = $order->shipment?->logistics_company_id ?? PicksellBridge::parcelFor($order)?->company_id;
        if (! $companyId) {
            throw new DomainException('This order has no logistics company yet. Contact the seller or PickSell support instead.');
        }
        if (LogisticsIssue::where('order_id', $order->id)->where('reported_by', $reporter->id)->where('type', $type)->unresolved()->exists()) {
            throw new DomainException('You already reported this problem. PickSell is looking into it.');
        }
        $parcel = PicksellBridge::parcelFor($order);

        return $this->open([
            'company_id' => $companyId,
            'order_id' => $order->id,
            'order_shipment_id' => $order->order_shipment_id,
            'parcel_id' => $parcel?->id,
            'source' => 'report',
            'type' => $type,
            'reported_by' => $reporter->id,
            'reporter_role' => $role,
            'description' => $description,
            'evidence_path' => $evidence?->store('logistics-issues', 'local'),
        ], actor: $reporter);
    }

    public function note(LogisticsIssue $issue, User $admin, string $note): void
    {
        $issue->events()->create(['user_id' => $admin->id, 'action' => 'note', 'note' => $note]);
    }

    /** Ask the company to answer: the issue shows on its portal until it replies. */
    public function escalate(LogisticsIssue $issue, User $admin, string $message): void
    {
        $this->assertUnresolved($issue);
        DB::transaction(function () use ($issue, $admin, $message) {
            $issue->update(['status' => 'escalated', 'escalated_at' => now(), 'company_response' => null, 'responded_at' => null]);
            $issue->events()->create(['user_id' => $admin->id, 'action' => 'escalated', 'note' => $message]);
        });
        $this->audit->record('logistics.issue.escalated', $issue, [], ['company_id' => $issue->company_id]);
    }

    /** The company answers an escalated issue from its portal; it goes back to admin. */
    public function respond(LogisticsIssue $issue, Staff $staff, string $response): void
    {
        if ($issue->status !== 'escalated' || $issue->company_id !== $staff->company_id) {
            throw new DomainException('This issue is not waiting on your company.');
        }
        DB::transaction(function () use ($issue, $staff, $response) {
            $issue->update(['status' => 'open', 'company_response' => $response, 'responded_at' => now()]);
            $issue->events()->create(['staff_id' => $staff->id, 'action' => 'company_response', 'note' => $response]);
        });
        $this->notifications->notifyAdmins('logistics.issue.response', 'Logistics company replied',
            "{$issue->company?->name} replied on issue #{$issue->id} ({$issue->label()}).", route('admin.logistics.issues.show', $issue, false));
    }

    /**
     * Close the issue with who was at fault. A carrier-fault loss or damage charges a claim; the
     * amount defaults to the item value + delivery fee and never exceeds the cap.
     */
    public function resolve(LogisticsIssue $issue, User $admin, string $fault, string $notes, ?float $claimAmount = null): ?LogisticsClaim
    {
        $this->assertUnresolved($issue);
        if (! array_key_exists($fault, LogisticsIssue::FAULTS)) {
            throw new DomainException('Choose who was at fault.');
        }

        $claim = DB::transaction(function () use ($issue, $admin, $fault, $notes, $claimAmount) {
            $issue->update(['status' => 'resolved', 'fault' => $fault, 'resolution_notes' => $notes, 'resolved_by' => $admin->id, 'resolved_at' => now()]);
            $issue->events()->create(['user_id' => $admin->id, 'action' => 'resolved', 'note' => LogisticsIssue::FAULTS[$fault].'. '.$notes]);

            if ($fault !== 'carrier' || ! $issue->isClaimable()) {
                return null;
            }
            $value = $this->claimValue($issue);
            $cap = LogisticsClaim::cap();
            $amount = $claimAmount !== null ? max(0, $claimAmount) : $value['item_value'] + $value['shipping_fee'];
            $capped = $amount > $cap;
            $amount = round(min($amount, $cap), 2);
            if ($amount <= 0) {
                return null;
            }
            $claim = $issue->claim()->create([
                'company_id' => $issue->company_id,
                'item_value' => $value['item_value'],
                'shipping_fee' => $value['shipping_fee'],
                'amount' => $amount,
                'capped' => $capped,
                'created_by' => $admin->id,
            ]);
            $issue->events()->create(['user_id' => $admin->id, 'action' => 'claim_charged', 'note' => '₱'.number_format($amount, 2).($capped ? ' (capped)' : '')]);

            return $claim;
        });

        $this->audit->record('logistics.issue.resolved', $issue, [], array_filter(['fault' => $fault, 'claim' => $claim?->amount]));

        return $claim;
    }

    public function waive(LogisticsClaim $claim, User $admin, string $reason): void
    {
        if ($claim->status !== 'charged') {
            throw new DomainException('This claim is already waived.');
        }
        DB::transaction(function () use ($claim, $admin, $reason) {
            $claim->update(['status' => 'waived', 'waive_reason' => $reason, 'waived_at' => now()]);
            $claim->issue->events()->create(['user_id' => $admin->id, 'action' => 'claim_waived', 'note' => $reason]);
        });
        $this->audit->record('logistics.claim.waived', $claim->issue, [], ['amount' => $claim->amount]);
    }

    /** @return array{item_value: float, shipping_fee: float} what the parcel was worth to the buyer */
    public function claimValue(LogisticsIssue $issue): array
    {
        $shipment = $issue->shipment ?? $issue->order?->shipment;
        $orders = $shipment ? $shipment->orders()->get() : collect([$issue->order])->filter();

        return [
            'item_value' => round((float) $orders->sum(fn (Order $order) => (float) $order->amount - (float) $order->store_discount), 2),
            'shipping_fee' => round((float) ($shipment?->delivery_fee ?? 0), 2),
        ];
    }

    private function open(array $attributes, bool $notify = true, string $priority = 'normal', ?User $actor = null): LogisticsIssue
    {
        $issue = DB::transaction(function () use ($attributes, $actor) {
            $issue = LogisticsIssue::create($attributes);
            $issue->events()->create(['user_id' => $actor?->id, 'action' => 'opened', 'note' => $attributes['description'] ?? null]);

            return $issue;
        });

        if ($notify) {
            $this->notifications->notifyAdmins('logistics.issue.opened', 'Logistics issue: '.$issue->label(),
                ($issue->company?->name ?? 'A logistics company').': '.\Illuminate\Support\Str::limit((string) $issue->description, 120),
                route('admin.logistics.issues.show', $issue, false), $actor?->id, $priority);
        }

        return $issue;
    }

    /** @return array{order_id?: int, order_shipment_id?: int} */
    private function orderLinks(?Parcel $parcel): array
    {
        $order = $parcel ? PicksellBridge::orderFor($parcel) : null;

        return $order ? ['order_id' => $order->id, 'order_shipment_id' => $order->order_shipment_id] : [];
    }

    private function assertUnresolved(LogisticsIssue $issue): void
    {
        if ($issue->status === 'resolved') {
            throw new DomainException('This issue is already resolved.');
        }
    }
}
