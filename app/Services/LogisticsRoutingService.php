<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\LogisticsBranch;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

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

    public function suggestedCourier(Order $order, array $excludeRiderIds = []): ?User
    {
        return $this->eligibleCouriers($order, $excludeRiderIds)->first();
    }

    /** @return Collection<int, User> */
    public function eligibleCouriers(Order $order, array $excludeRiderIds = []): Collection
    {
        if (! $order->destination_barangay_id || ! $order->destination_branch_id) {
            return new Collection;
        }

        $query = User::query()
            ->where('role', 'courier')->where('status', 'approved')
            ->whereHas('branchAssignments', fn ($query) => $this->constrainAssignmentToOrder($query, $order))
            ->withCount(['ordersAsCourier as active_parcels' => function ($query) {
                $query->whereIn('status', ['assigned_to_rider', 'out_for_delivery']);
            }])
            ->withExists(['branchAssignments as primary_delivery_coverage' => function ($query) use ($order) {
                $this->constrainAssignmentToOrder($query, $order)
                    ->whereHas('barangays', fn ($barangays) => $barangays
                        ->where('barangay_id', $order->destination_barangay_id)
                        ->where('is_primary', true));
            }])
            ->withMax('deliveryAssignments as last_delivery_offered_at', 'offered_at')
            ->orderByDesc('primary_delivery_coverage')
            ->orderByRaw('CASE WHEN last_delivery_offered_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('last_delivery_offered_at')
            ->orderBy('active_parcels');
        if ($excludeRiderIds !== []) {
            $query->whereNotIn('id', $excludeRiderIds);
        }

        return $query->orderBy('id')->get();
    }

    public function courierCoversOrder(User $courier, Order $order): bool
    {
        if (!$order->destination_branch_id || !$order->destination_barangay_id) {
            return false;
        }

        return $courier->role === 'courier'
            && $courier->status === 'approved'
            && $courier->branchAssignments()
                ->where(fn ($query) => $this->constrainAssignmentToOrder($query, $order))
                ->exists();
    }

    private function constrainAssignmentToOrder($query, Order $order)
    {
        return $query->where('branch_id', $order->destination_branch_id)
            ->where('status', 'active')
            ->whereHas('branch.municipality', function ($municipality) use ($order) {
                $municipality->whereHas('barangays', fn ($barangay) => $barangay->whereKey($order->destination_barangay_id));
            })
            ->whereHas('barangays', fn ($barangays) => $barangays->where('barangay_id', $order->destination_barangay_id));
    }
}