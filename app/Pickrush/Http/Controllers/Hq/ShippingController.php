<?php

namespace App\Pickrush\Http\Controllers\Hq;

use App\Pickrush\Bridge\PicksellBridge;
use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Http\Controllers\PortalController;
use App\Pickrush\Models\AuditLog;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\ParcelClass;
use App\Pickrush\Models\ShippingOverweightRate;
use App\Pickrush\Models\ShippingRate;
use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Services\Shipping\RegionResolver;
use App\Pickrush\Services\Shipping\RouteRateGenerator;
use App\Pickrush\Services\Shipping\ShippingQuote;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Marketplace shipping prices, owned by Pickrush: route price levels and the 5 × 5 route grid
 * that generate a price for every (seller region → buyer region) pair, the generic destination
 * prices used as fallback, parcel classes (rider pay) and the province map. A price is never
 * edited in place: a dated row is added and the old one ends the day before, so orders keep the
 * price they were quoted. Each company keeps its own rate card; parcel classes and the province map
 * are shared by the network. Super admins edit; finance and support read.
 */
class ShippingController extends PortalController
{
    private const REGION_ORDER = "case code when 'NCR' then 1 when 'LUZON' then 2 when 'VISAYAS' then 3 when 'MINDANAO' then 4 else 5 end";

    public function index(Request $request, RouteRateGenerator $routes)
    {
        $routes = $routes->forCompany($this->staff()->company_id);
        $today = today()->toDateString();
        $regions = DB::table('pr_shipping_regions')->orderByRaw(self::REGION_ORDER)->pluck('name', 'code');
        $from = $request->query('from', 'GENERIC');
        $levels = $routes->levels();
        $tiers = array_keys(reset($levels)['fees'] ?? []);
        $tier = in_array($request->query('tier'), $tiers, true) ? $request->query('tier') : ($tiers[1] ?? $tiers[0] ?? null);
        $delivered = Parcel::withoutGlobalScopes()->where('company_id', $this->staff()->company_id)->where('current_status', ParcelStatus::Delivered)->whereNotNull('logistics_share');
        $marketplace = Parcel::withoutGlobalScopes()->where('company_id', $this->staff()->company_id)->where('source', PicksellBridge::SOURCE)->whereNotNull('price_source');

        return view('pickrush.hq.shipping', [
            'regions' => $regions,
            'from' => $from,
            'rates' => ShippingRate::where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', today()->subDays(90)))
                ->when($from === 'GENERIC', fn ($q) => $q->whereNull('origin_region'), fn ($q) => $q->when($from !== 'ALL', fn ($q) => $q->where('origin_region', $from)))
                ->orderByRaw('origin_region is not null')->orderBy('origin_region')->orderBy('destination_region')->orderBy('max_weight_kg')->orderBy('effective_from')->get(),
            'overweight' => ShippingOverweightRate::where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', today()->subDays(90)))
                ->when($from === 'GENERIC', fn ($q) => $q->whereNull('origin_region'), fn ($q) => $q->when($from !== 'ALL', fn ($q) => $q->where('origin_region', $from)))
                ->orderByRaw('origin_region is not null')->orderBy('origin_region')->orderBy('destination_region')->orderBy('effective_from')->get(),
            'tiers' => $tiers,
            'tier' => $tier,
            'priceGrid' => $tier ? $this->priceGrid($tier, $today) : [],
            'levels' => $levels,
            'routeLevels' => $routes->grid(),
            'classes' => ParcelClass::orderBy('min_kg_exclusive')->get(),
            'provinces' => DB::table('pr_shipping_region_provinces')->orderBy('name')->get()->groupBy('region_code'),
            'today' => $today,
            'minRiderPay' => (float) config('pickrush.shipping.min_rider_pay'),
            'earnings' => [
                'month' => (clone $delivered)->where('delivered_at', '>=', now()->startOfMonth())
                    ->selectRaw('count(*) as parcels, coalesce(sum(shipping_fee), 0) as fees, coalesce(sum(rider_pay), 0) as rider, coalesce(sum(logistics_share), 0) as logistics, coalesce(sum(platform_commission), 0) as picksell')->first(),
                'all' => (clone $delivered)
                    ->selectRaw('count(*) as parcels, coalesce(sum(shipping_fee), 0) as fees, coalesce(sum(rider_pay), 0) as rider, coalesce(sum(logistics_share), 0) as logistics, coalesce(sum(platform_commission), 0) as picksell')->first(),
            ],
            'fallback' => [
                'month' => (clone $marketplace)->where('price_source', ShippingQuote::GENERIC_FALLBACK)->where('created_at', '>=', now()->startOfMonth())->count(),
                'all' => (clone $marketplace)->where('price_source', ShippingQuote::GENERIC_FALLBACK)->count(),
                'parcels' => (clone $marketplace)->count(),
                'sellers' => PicksellBridge::sellersWithoutShippingRegion(),
            ],
            'canEdit' => $this->canEdit(),
            'canEditNetwork' => $this->canEditNetwork(),
        ]);
    }

    /** Save the 5 × 5 route grid; route prices change from the date given. */
    public function updateRouteLevels(Request $request, RouteRateGenerator $routes)
    {
        $this->authorizeEdit();
        $data = $request->validate([
            'levels' => ['required', 'array'],
            'levels.*' => ['required', 'array'],
            'levels.*.*' => ['required', 'integer', 'min:0', 'max:255'],
            'effective_from' => ['required', 'date', 'after_or_equal:today'],
        ]);

        return $this->regenerate(fn () => $routes->forCompany($this->staff()->company_id)->updateGrid($data['levels'], $data['effective_from']), 'Route levels saved', 'levels');
    }

    /** Save the price levels (tier fees and per-kg rate); route prices change from the date given. */
    public function updatePriceLevels(Request $request, RouteRateGenerator $routes)
    {
        $this->authorizeEdit();
        $data = $request->validate([
            'prices' => ['required', 'array'],
            'prices.*.fees' => ['required', 'array'],
            'prices.*.fees.*' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'prices.*.per_kg' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'effective_from' => ['required', 'date', 'after_or_equal:today'],
        ]);
        // Tier keys arrive with "_" for "." so they survive the request parser.
        $prices = collect($data['prices'])->map(fn ($level) => [
            'fees' => collect($level['fees'])->mapWithKeys(fn ($fee, $kg) => [number_format((float) str_replace('_', '.', $kg), 2, '.', '') => (string) $fee])->all(),
            'per_kg' => (string) $level['per_kg'],
        ])->all();

        return $this->regenerate(fn () => $routes->forCompany($this->staff()->company_id)->updateLevels($prices, $data['effective_from']), 'Price levels saved', 'prices');
    }

    /** A new tier fee from a date; the tier's open row (if any) ends the day before. */
    public function storeRate(Request $request)
    {
        $this->authorizeEdit();
        $data = $request->validate([
            'origin_region' => ['nullable', Rule::exists('pr_shipping_regions', 'code')],
            'destination_region' => ['required', Rule::exists('pr_shipping_regions', 'code')],
            'max_weight_kg' => ['required', 'numeric', 'min:0.01', 'max:1000'],
            'fee' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'effective_from' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $rate = DB::transaction(function () use ($data) {
            $this->endOpenRows($this->route(ShippingRate::query(), $data)->where('max_weight_kg', $data['max_weight_kg']), $data['effective_from']);

            return ShippingRate::create($data);
        });

        return back()->with('success', ($rate->origin_region ?? 'Any')." → {$rate->destination_region} up to {$rate->max_weight_kg} kg: ₱{$rate->fee} from {$rate->effective_from->toDateString()}.");
    }

    /** A new per-kg rate above the heaviest tier, from a date. */
    public function storeOverweight(Request $request)
    {
        $this->authorizeEdit();
        $data = $request->validate([
            'origin_region' => ['nullable', Rule::exists('pr_shipping_regions', 'code')],
            'destination_region' => ['required', Rule::exists('pr_shipping_regions', 'code')],
            'over_kg' => ['required', 'numeric', 'min:0.01', 'max:1000'],
            'per_kg_fee' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'effective_from' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $rate = DB::transaction(function () use ($data) {
            $this->endOpenRows($this->route(ShippingOverweightRate::query(), $data), $data['effective_from']);

            return ShippingOverweightRate::create($data);
        });

        return back()->with('success', ($rate->origin_region ?? 'Any')." → {$rate->destination_region} above {$rate->over_kg} kg: ₱{$rate->per_kg_fee}/kg from {$rate->effective_from->toDateString()}.");
    }

    public function endRate(Request $request, ShippingRate $rate)
    {
        return $this->endRow($request, $rate);
    }

    public function endOverweight(Request $request, ShippingOverweightRate $rate)
    {
        return $this->endRow($request, $rate);
    }

    public function updateClass(Request $request, ParcelClass $class)
    {
        $this->authorizeNetworkEdit();
        $data = $request->validate([
            'min_kg_exclusive' => ['required', 'numeric', 'min:0', 'max:1000'],
            'max_kg_inclusive' => ['required', 'numeric', 'gt:min_kg_exclusive', 'max:1000'],
            'rider_pay' => ['required', 'numeric', 'min:0', 'max:100000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        $before = $class->only(array_keys($data));
        try {
            $class->fill($data)->save();
        } catch (OperationRefused $e) {
            return back()->withErrors(["class_{$class->code}" => $e->getMessage()])->withInput();
        }
        // Classes are keyed by code, which the audit log's numeric subject cannot hold: record it by action.
        AuditLog::record(null, "parcel_class.{$class->code}.updated", $before, $class->only(array_keys($data)));

        return back()->with('success', "{$class->code} saved. New orders use it; placed orders keep their rider pay.");
    }

    /** Map a province, city or alias to a region (adds it, or moves it to another region). */
    public function storeProvince(Request $request)
    {
        $this->authorizeNetworkEdit();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'region_code' => ['required', Rule::exists('pr_shipping_regions', 'code')],
        ]);
        $name = trim(preg_replace('/\s+/', ' ', $data['name']));
        $existing = DB::table('pr_shipping_region_provinces')->get()->first(fn ($row) => RegionResolver::key($row->name) === RegionResolver::key($name));

        DB::table('pr_shipping_region_provinces')->updateOrInsert(['name' => $existing->name ?? $name], ['region_code' => $data['region_code']]);
        AuditLog::record(null, 'shipping_province.mapped', $existing ? ['name' => $existing->name, 'region' => $existing->region_code] : [], ['name' => $existing->name ?? $name, 'region' => $data['region_code']]);

        return back()->with('success', ($existing->name ?? $name)." now ships as {$data['region_code']}.");
    }

    public function destroyProvince(Request $request)
    {
        $this->authorizeNetworkEdit();
        $name = (string) $request->validate(['name' => ['required', 'string', 'max:100']])['name'];
        $row = DB::table('pr_shipping_region_provinces')->where('name', $name)->first();
        abort_unless($row, 404);

        DB::table('pr_shipping_region_provinces')->where('name', $name)->delete();
        AuditLog::record(null, 'shipping_province.removed', ['name' => $row->name, 'region' => $row->region_code], []);

        return back()->with('success', "{$name} removed. Addresses in it can no longer be priced until it is mapped again.");
    }

    /**
     * Fees in force today for one tier: origin => destination => fee, plus the generic row
     * under the key "GENERIC". A route without its own row shows null (it uses the generic fee).
     */
    private function priceGrid(string $tier, string $today): array
    {
        $grid = [];
        foreach (ShippingRate::effectiveOn($today)->where('max_weight_kg', $tier)->orderBy('effective_from')->get() as $rate) {
            $grid[$rate->origin_region ?? 'GENERIC'][$rate->destination_region] = (string) $rate->fee;
        }

        return $grid;
    }

    private function regenerate(callable $save, string $done, string $errorKey)
    {
        try {
            $counts = $save();
        } catch (OperationRefused $e) {
            return back()->withErrors([$errorKey => $e->getMessage()])->withInput();
        }

        return back()->with('success', "{$done}: {$counts['created']} new price rows, {$counts['ended']} ended, {$counts['updated']} corrected, {$counts['unchanged']} unchanged.");
    }

    /** Rows for the same route: the given origin, or the generic rows when none is given. */
    private function route($query, array $data)
    {
        return $query->where('destination_region', $data['destination_region'])
            ->when($data['origin_region'] ?? null, fn ($q, $origin) => $q->where('origin_region', $origin), fn ($q) => $q->whereNull('origin_region'));
    }

    /** End-date a rate row: it applies up to and including that day. */
    private function endRow(Request $request, ShippingRate|ShippingOverweightRate $rate)
    {
        $this->authorizeEdit();
        $data = $request->validate(['effective_to' => ['required', 'date', 'after_or_equal:'.$rate->effective_from->toDateString(), 'after_or_equal:yesterday']]);
        $rate->update(['effective_to' => $data['effective_to']]);

        return back()->with('success', 'Rate ends after '.Carbon::parse($data['effective_to'])->toDateString().'.');
    }

    /**
     * Rows still in force on the new start date end the day before it. A row already scheduled to
     * start on or after that date is refused: two prices for one tier and day would be ambiguous.
     */
    private function endOpenRows($query, string $from): void
    {
        $start = Carbon::parse($from);
        $overlapping = $query->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $start))->get();
        if ($later = $overlapping->first(fn ($row) => $row->effective_from->gte($start))) {
            throw ValidationException::withMessages(['effective_from' => 'A rate for this already starts on '.$later->effective_from->toDateString().'. End that one first, or pick a later date.']);
        }
        $overlapping->each(fn ($row) => $row->update(['effective_to' => $start->copy()->subDay()->toDateString()]));
    }

    private function canEdit(): bool
    {
        return $this->staff()->role->value === 'super_admin';
    }

    private function authorizeEdit(): void
    {
        abort_unless($this->canEdit(), 403, 'Only a super admin changes shipping prices.');
    }

    /**
     * Parcel classes (rider pay) and the province-to-region map are shared by every company, so
     * only the default company's super admin keeps them.
     */
    private function canEditNetwork(): bool
    {
        return $this->canEdit() && (bool) $this->staff()->company?->is_default;
    }

    private function authorizeNetworkEdit(): void
    {
        abort_unless($this->canEditNetwork(), 403, 'Parcel classes and the province map are shared by every logistics company.');
    }
}
