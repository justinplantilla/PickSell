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
 * pickup pay that comes out of that share, damage the company recovered from riders at fault, the
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
            // A settled dispute counts for what the bank showed; its top-ups are confirmed deposits of their own.
            ->whereIn('pr_hub_remittances.status', ['confirmed', 'settled'])
            ->when($from, fn ($q) => $q->where('pr_hub_remittances.confirmed_at', '>=', $from))
            ->selectRaw("pr_hubs.company_id, coalesce(sum(case when pr_hub_remittances.status = 'settled' then coalesce(pr_hub_remittances.bank_amount, 0) else pr_hub_remittances.remitted_amount end), 0) as confirmed")
            ->groupBy('pr_hubs.company_id')->pluck('confirmed', 'company_id');

        // Rider pay that riders kept out of that cash before handing it to their station.
        $kept = DB::table('pr_cod_remittances')->join('pr_hubs', 'pr_hubs.id', '=', 'pr_cod_remittances.station_hub_id')
            ->when($from, fn ($q) => $q->where('pr_cod_remittances.created_at', '>=', $from))
            ->selectRaw('pr_hubs.company_id, coalesce(sum(pr_cod_remittances.rider_pay_kept), 0) as kept')
            ->groupBy('pr_hubs.company_id')->pluck('kept', 'company_id');

        // Pickup pay: the fee split reserves only the delivery rider's pay, so the pickup rider's
        // (the same class pay) comes out of the company's share.
        $pickupPay = DB::table('pr_rider_earnings')->join('pr_parcels', 'pr_parcels.id', '=', 'pr_rider_earnings.parcel_id')
            ->where('pr_parcels.source', 'picksell')->where('pr_rider_earnings.kind', 'pickup')
            ->when($from, fn ($q) => $q->where('pr_rider_earnings.earned_at', '>=', $from))
            ->selectRaw('pr_parcels.company_id, coalesce(sum(pr_rider_earnings.amount), 0) as pay')
            ->groupBy('pr_parcels.company_id')->pluck('pay', 'company_id');

        // Damaged or lost parcels the company recovers from the rider at fault (deducted from their pay).
        $recovered = DB::table('pr_rider_deductions')->join('pr_riders', 'pr_riders.id', '=', 'pr_rider_deductions.rider_id')
            ->whereIn('pr_rider_deductions.kind', ['damage', 'lost'])->where('pr_rider_deductions.status', 'active')
            ->when($from, fn ($q) => $q->where('pr_rider_deductions.occurred_at', '>=', $from))
            ->selectRaw('pr_riders.company_id, coalesce(sum(pr_rider_deductions.amount), 0) as recovered')
            ->groupBy('pr_riders.company_id')->pluck('recovered', 'company_id');

        // Claims for parcels the company lost or damaged, charged against its share.
        $claims = DB::table('logistics_claims')->where('status', 'charged')
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->selectRaw('company_id, coalesce(sum(amount), 0) as charged')->groupBy('company_id')->pluck('charged', 'company_id');

        return Company::whereNotNull('approved_at')->orderByDesc('is_default')->orderBy('name')->get()
            ->map(function (Company $company) use ($delivered, $open, $cod, $remitted, $kept, $claims, $pickupPay, $recovered) {
                $row = $delivered->get($company->id);
                $claimed = (float) ($claims[$company->id] ?? 0);
                $pickups = (float) ($pickupPay[$company->id] ?? 0);
                $fromRiders = (float) ($recovered[$company->id] ?? 0);

                return [
                    'claims' => $claimed,
                    'pickup_pay' => $pickups,
                    'rider_recovered' => $fromRiders,
                    // The company's share less pickup pay and claims, plus what riders at fault paid back.
                    'net_logistics' => round((float) ($row->logistics ?? 0) - $pickups - $claimed + $fromRiders, 2),
                    'company' => $company,
                    'delivered' => (int) ($row->parcels ?? 0),
                    'in_progress' => (int) ($open[$company->id] ?? 0),
                    'fees' => (float) ($row->fees ?? 0),
                    'rider' => (float) ($row->rider ?? 0),
                    'logistics' => (float) ($row->logistics ?? 0),
                    'picksell' => (float) ($row->picksell ?? 0),
                    'cod_collected' => (float) ($cod[$company->id] ?? 0),
                    'cod_rider_kept' => (float) ($kept[$company->id] ?? 0),
                    'cod_confirmed' => (float) ($remitted[$company->id] ?? 0),
                ];
            });
    }
}
