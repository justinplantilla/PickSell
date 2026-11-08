<?php

namespace App\Services\Finance;

use App\Models\OrderShipment;
use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Company;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * PickSell's money with each logistics company over a period: the delivery fees of the parcels
 * it delivered and their split (rider pay, the company's share, PickSell's commission), the
 * cash it collected on delivery, and how much of that its stations have remitted and its HQ
 * confirmed. Credited amounts come from the shipments' saved splits (ShipmentSettlement), so
 * they never change with later rate edits.
 */
class LogisticsSettlement
{
    /** @return Collection<int, array<string, mixed>> one row per company that was ever approved, Pickrush first */
    public function byCompany(?CarbonInterface $from = null): Collection
    {
        $delivered = OrderShipment::query()->where('status', 'delivered')->whereNotNull('earnings_posted_at')
            ->when($from, fn ($q) => $q->where('delivered_at', '>=', $from))
            ->selectRaw('logistics_company_id, count(*) as parcels, coalesce(sum(delivery_fee), 0) as fees, coalesce(sum(rider_pay), 0) as rider,
                coalesce(sum(logistics_share), 0) as logistics, coalesce(sum(platform_commission), 0) as picksell')
            ->groupBy('logistics_company_id')->get()->keyBy('logistics_company_id');

        $open = OrderShipment::query()->whereNotIn('status', array_merge(['delivered'], OrderShipment::VOID))
            ->selectRaw('logistics_company_id, count(*) as parcels')->groupBy('logistics_company_id')->pluck('parcels', 'logistics_company_id');

        $cod = DB::table('pr_parcels')->where('source', 'picksell')->where('current_status', ParcelStatus::Delivered->value)
            ->when($from, fn ($q) => $q->where('delivered_at', '>=', $from))
            ->selectRaw('company_id, coalesce(sum(cod_amount), 0) as collected')->groupBy('company_id')->pluck('collected', 'company_id');

        // Station remittances confirmed by the company's HQ, by the station's company.
        $remitted = DB::table('pr_hub_remittances')->join('pr_hubs', 'pr_hubs.id', '=', 'pr_hub_remittances.station_hub_id')
            ->where('pr_hub_remittances.status', 'confirmed')
            ->when($from, fn ($q) => $q->where('pr_hub_remittances.confirmed_at', '>=', $from))
            ->selectRaw('pr_hubs.company_id, coalesce(sum(pr_hub_remittances.remitted_amount), 0) as confirmed')
            ->groupBy('pr_hubs.company_id')->pluck('confirmed', 'company_id');

        return Company::whereNotNull('approved_at')->orderByDesc('is_default')->orderBy('name')->get()
            ->map(function (Company $company) use ($delivered, $open, $cod, $remitted) {
                $row = $delivered->get($company->id);

                return [
                    'company' => $company,
                    'delivered' => (int) ($row->parcels ?? 0),
                    'in_progress' => (int) ($open[$company->id] ?? 0),
                    'fees' => (float) ($row->fees ?? 0),
                    'rider' => (float) ($row->rider ?? 0),
                    'logistics' => (float) ($row->logistics ?? 0),
                    'picksell' => (float) ($row->picksell ?? 0),
                    'cod_collected' => (float) ($cod[$company->id] ?? 0),
                    'cod_confirmed' => (float) ($remitted[$company->id] ?? 0),
                ];
            });
    }
}
