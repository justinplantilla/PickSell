<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\LogisticsBranch;
use App\Models\Order;
use App\Models\User;

class LogisticsRoutingService
{
    public function branchForUser(?User $user): ?LogisticsBranch
    {
        if (!$user) return null;

        if ($user->municipality_id) {
            return LogisticsBranch::where('municipality_id', $user->municipality_id)
                ->where('status', 'active')->first();
        }

        if (!$user->municipality || !$user->province) return null;

        return LogisticsBranch::where('status', 'active')
            ->whereHas('municipality', function ($query) use ($user) {
                $query->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($user->municipality))])
                    ->whereRaw('LOWER(province) = ?', [mb_strtolower(trim($user->province))]);
            })->first();
    }

    public function barangayForUser(?User $user): ?Barangay
    {
        if (!$user) return null;

        if ($user->barangay_id) return Barangay::find($user->barangay_id);

        $branch = $this->branchForUser($user);
        if (!$branch || !$user->barangay) return null;

        return Barangay::where('municipality_id', $branch->municipality_id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($user->barangay))])->first();
    }

    public function routeOrder(Order $order, ?User $seller, ?User $buyer): void
    {
        $origin = $this->branchForUser($seller);
        $destination = $this->branchForUser($buyer);
        $barangay = $this->barangayForUser($buyer);

        $order->update([
            'origin_branch_id' => $origin?->id,
            'destination_branch_id' => $destination?->id,
            'destination_barangay_id' => $barangay?->id,
        ]);
    }

    public function suggestedCourier(Order $order): ?User
    {
        if (!$order->destination_barangay_id || !$order->destination_branch_id) return null;

        return User::query()
            ->where('role', 'courier')->where('status', 'approved')
            ->whereHas('branchAssignments', function ($query) use ($order) {
                $query->where('branch_id', $order->destination_branch_id)
                    ->where('status', 'active')
                    ->whereHas('barangays', function ($barangays) use ($order) {
                        $barangays->where('barangay_id', $order->destination_barangay_id);
                    });
            })
            ->withCount(['ordersAsCourier as active_parcels' => function ($query) {
                $query->whereIn('status', ['assigned_to_rider', 'out_for_delivery']);
            }])
            ->orderBy('active_parcels')->first();
    }
}