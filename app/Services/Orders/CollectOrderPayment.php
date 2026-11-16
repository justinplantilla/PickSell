<?php

namespace App\Services\Orders;

use App\Models\OrderPayment;
use App\Pickrush\Bridge\PicksellBridge;
use App\Pickrush\Models\Assignment;
use App\Pickrush\Services\OperationRefused;

/** Called inside the rider delivery transaction; remittance remains in PickRush's existing ledger. */
class CollectOrderPayment
{
    public function collect(Assignment $task): void
    {
        if ($task->type !== 'delivery' || $task->status !== 'completed' || $task->parcel->is_return) return;
        $orders = PicksellBridge::ordersFor($task->parcel);
        $payments = OrderPayment::whereIn('order_id', $orders->pluck('id'))->lockForUpdate()->get();
        if ($payments->isEmpty()) return; // Historical parcels retain their existing collection records.
        if ($payments->count() !== 1 || (int) round($payments->sum('amount_due') * 100) !== (int) round($task->cod_collected * 100)) {
            throw new OperationRefused('The order payment and parcel COD do not match. Ask the station to check this parcel.');
        }
        $payment = $payments->first();
        if ($payment->status === 'paid' && $payment->assignment_id === $task->id) return;
        if ($payment->status !== 'unpaid') throw new OperationRefused('This order payment cannot be collected again.');
        $payment->update([
            'status' => 'paid', 'amount_collected' => $task->cod_collected,
            'assignment_id' => $task->id, 'collected_at' => $task->completed_at,
        ]);
    }
}
