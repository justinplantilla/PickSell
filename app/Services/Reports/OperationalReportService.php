<?php

namespace App\Services\Reports;

use App\Models\Complaint;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\Orders\OrderLifecycleService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OperationalReportService
{
    /**
     * @return array{
     *   orders: \Illuminate\Contracts\Pagination\LengthAwarePaginator,
     *   status_totals: Collection,
     *   order_count: int,
     *   average_fulfillment_hours: float,
     *   delivery_failures: int,
     *   return_count: int,
     *   complaint_count: int,
     *   rider_workload: Collection,
     *   sorting_backlog: int
     * }
     */
    public function report(
        CarbonInterface $from,
        CarbonInterface $to,
        string $status,
        ?int $sellerId,
    ): array {
        $base = $this->orders($from, $to, $status, $sellerId);
        $statusTotals = (clone $base)->selectRaw('status, count(*) as total')
            ->groupBy('status')->orderBy('status')->pluck('total', 'status');
        $orderCount = (clone $base)->count();
        $completed = (clone $base)->whereNotNull('completed_at')
            ->get(['created_at', 'completed_at']);
        $averageFulfillmentHours = $completed->isEmpty()
            ? 0.0
            : round($completed->avg(fn (Order $order): float => $order->created_at->diffInMinutes($order->completed_at) / 60), 2);

        $riderWorkload = (clone $base)->whereNotNull('courier_id')
            ->selectRaw('courier_id, count(*) as total')
            ->groupBy('courier_id')->orderByDesc('total')->get()
            ->map(function ($row): array {
                $rider = User::query()->find($row->courier_id);

                return [
                    'name' => $rider?->full_name ?? 'Unavailable rider',
                    'orders' => (int) $row->total,
                ];
            });

        return [
            'orders' => (clone $base)->with(['seller', 'courier'])->latest('created_at')->paginate(25)->withQueryString(),
            'status_totals' => $statusTotals,
            'order_count' => $orderCount,
            'average_fulfillment_hours' => $averageFulfillmentHours,
            'delivery_failures' => OrderStatusHistory::query()
                ->where('to_status', 'delivery_failed')
                ->whereBetween('created_at', [$from, $to])
                ->whereHas('order', function (Builder $query) use ($status, $sellerId): void {
                    $query->when($status !== 'all', fn (Builder $orders) => $orders->where('status', $status))
                        ->when($sellerId !== null, fn (Builder $orders) => $orders->where('seller_id', $sellerId));
                })->count(),
            'return_count' => ReturnRequest::query()
                ->whereBetween('created_at', [$from, $to])
                ->when($sellerId !== null, fn (Builder $query) => $query->where('seller_id', $sellerId))
                ->count(),
            'complaint_count' => Complaint::query()
                ->whereBetween('created_at', [$from, $to])
                ->when($sellerId !== null, fn (Builder $query) => $query->where(function (Builder $complaints) use ($sellerId): void {
                    $complaints->where('against_user_id', $sellerId)
                        ->orWhere('filed_by', $sellerId)
                        ->orWhereHas('order', fn (Builder $orders) => $orders->where('seller_id', $sellerId));
                }))->count(),
            'rider_workload' => $riderWorkload,
            'sorting_backlog' => (clone $base)->whereIn('status', ['picked_up', 'at_sorting_center', 'sorted'])->count(),
        ];
    }

    /** @return array<int, string> */
    public static function statuses(): array
    {
        return array_values(array_unique([
            ...Order::statusLifecycle(),
            ...OrderLifecycleService::LEGACY,
        ]));
    }

    private function orders(
        CarbonInterface $from,
        CarbonInterface $to,
        string $status,
        ?int $sellerId,
    ): Builder {
        return Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->when($sellerId !== null, fn (Builder $query) => $query->where('seller_id', $sellerId));
    }
}
