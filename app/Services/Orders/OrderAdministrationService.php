<?php

namespace App\Services\Orders;

use App\Auth\Permission;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderChangedByAdminNotification;
use App\Services\AuditLogger;
use App\Services\Finance\FinancialLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Admin interventions on orders. Callers authorize first (OrderPolicy); the order is re-read under
 * a row lock, the transition is validated by OrderLifecycleService, and the change, its history
 * row and the audit entry commit together. Buyer and seller are notified after commit.
 */
class OrderAdministrationService
{
    /** Statuses Admin cannot set by override: they need operational data (waybill, rider). */
    public const OVERRIDE_BLOCKED_TARGETS = ['ready_for_pickup', 'assigned_to_rider'];

    /** Named remedies for operational exceptions. */
    public const EXCEPTION_ACTIONS = [
        'reattempt_delivery' => ['label' => 'Re-attempt delivery', 'from' => ['delivery_failed'], 'to' => 'at_sorting_center',
            'help' => 'Send the parcel back to the sorting center and release the rider so it can be reassigned.'],
        'return_to_seller' => ['label' => 'Return to seller', 'from' => ['delivery_failed'], 'to' => 'returned',
            'help' => 'Close the order as returned; the item goes back to the seller and stock is restored.'],
        'release_rider' => ['label' => 'Release rider', 'from' => ['assigned_to_rider'], 'to' => 'at_sorting_center',
            'help' => 'The assigned rider cannot deliver; return the parcel to the sorting queue.'],
        'cancel_order' => ['label' => 'Cancel order', 'from' => ['placed', 'confirmed', 'preparing', 'ready_for_pickup'], 'to' => 'cancelled',
            'help' => 'Cancel before pickup; stock is restored.'],
    ];

    public function __construct(
        private OrderLifecycleService $lifecycle,
        private AuditLogger $audit,
        private FinancialLedgerService $ledger,
    ) {}

    /** @return string[] statuses an admin may override this order to */
    public static function overrideTargets(Order $order): array
    {
        return array_values(array_diff(OrderLifecycleService::allowedNext($order->status), self::OVERRIDE_BLOCKED_TARGETS));
    }

    /** @return array<string, array> exception remedies applicable to this order */
    public static function exceptionActions(Order $order): array
    {
        return array_filter(self::EXCEPTION_ACTIONS, fn ($action) => in_array($order->status, $action['from'], true));
    }

    public function overrideStatus(Order $order, User $admin, string $to, string $reason): void
    {
        $from = $this->apply($order, function (Order $locked) use ($to) {
            abort_unless(in_array($to, self::overrideTargets($locked), true), 409,
                'An order cannot be overridden from '.OrderLifecycleService::label($locked->status).' to '.OrderLifecycleService::label($to).'.');

            return $to;
        }, $admin, $reason, 'order.status_overridden', Permission::ORDERS_OVERRIDE_STATUS);

        $this->notifyParties($order, $from, $reason);
    }

    public function resolveException(Order $order, User $admin, string $action, string $reason, string $permission = Permission::ORDERS_MANAGE): void
    {
        $from = $this->apply($order, function (Order $locked) use ($action) {
            $definition = self::EXCEPTION_ACTIONS[$action] ?? abort(422, 'Unknown resolution.');
            abort_unless(in_array($locked->status, $definition['from'], true), 409,
                "{$definition['label']} does not apply to an order that is ".OrderLifecycleService::label($locked->status).'.');

            return $definition['to'];
        }, $admin, $reason, 'order.exception_resolved', $permission, ['resolution' => $action]);

        $this->notifyParties($order, $from, $reason);
    }

    /** @return string the status the order had before the change */
    private function apply(Order $order, callable $target, User $admin, string $reason, string $auditAction, string $permission, array $metadata = []): string
    {
        return DB::transaction(function () use ($order, $target, $admin, $reason, $auditAction, $permission, $metadata) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;
            $to = $target($locked);

            $this->lifecycle->transition($locked, $to, $admin->id, 'admin', $reason);
            if ($to === 'completed') {
                $this->ledger->postCompletedOrder($locked, $admin);
            }
            $order->setRawAttributes($locked->getAttributes(), true);

            $this->audit->record($auditAction, $order, ['status' => ['from' => $from, 'to' => $to]],
                ['reason' => $reason] + $metadata, $permission);

            return $from;
        });
    }

    private function notifyParties(Order $order, string $from, string $reason): void
    {
        foreach ([$order->buyer, $order->seller] as $party) {
            try {
                $party?->notify(new OrderChangedByAdminNotification($order, $from, $reason));
            } catch (Throwable $exception) {
                Log::warning('Order party could not be notified about an admin change.', ['order_id' => $order->id, 'error' => $exception->getMessage()]);
            }
        }
    }
}
