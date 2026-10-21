<?php

namespace App\Pickrush\Services\Hq;

use App\Models\Geo\Barangay;
use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\ParcelException;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\ExceptionDesk;
use App\Pickrush\Services\HubRouter;
use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Services\RateCard;
use Illuminate\Support\Facades\DB;

/**
 * Fixes a parcel's pickup or delivery address (confirmed with the buyer or seller) by choosing the
 * official barangay. The parcel gets its stations, route and fee, and a parcel held for having no
 * route is released so it can move on.
 */
class AddressFixer
{
    public function __construct(private HubRouter $router, private ExceptionDesk $desk) {}

    public function fix(Parcel $parcel, string $side, string $barangayCode, ?string $addressLine, Staff $staff): void
    {
        $barangay = Barangay::with('municipality.province')->find($barangayCode)
            ?? throw new OperationRefused('Pick a barangay from the list.');
        $station = $this->router->stationFor($barangay->municipality_code)
            ?? throw new OperationRefused("No station covers {$barangay->municipality->name} yet.");
        if (in_array($parcel->current_status, [ParcelStatus::Delivered, ParcelStatus::ReturnedToSender, ParcelStatus::Cancelled, ParcelStatus::Lost], true)) {
            throw new OperationRefused('This parcel has finished its journey.');
        }
        if ($side === 'pickup' && $parcel->current_status !== ParcelStatus::WaitingPickup) {
            throw new OperationRefused('The pickup address can only change before the parcel is collected.');
        }

        DB::transaction(function () use ($parcel, $side, $barangay, $station, $addressLine, $staff) {
            $line = $addressLine ?: "{$barangay->name}, {$barangay->municipality->name}, {$barangay->municipality->province->name}";
            $parcel->forceFill($side === 'pickup'
                ? ['pickup_barangay_code' => $barangay->code, 'pickup_municipality_code' => $barangay->municipality_code, 'pickup_address' => $line, 'origin_hub_id' => $station->id]
                : ['delivery_barangay_code' => $barangay->code, 'delivery_municipality_code' => $barangay->municipality_code, 'delivery_address' => $line, 'dest_hub_id' => $station->id]);
            $parcel->needs_address_fix = ! $parcel->origin_hub_id || ! $parcel->dest_hub_id;
            $parcel->shipping_fee = RateCard::quote($parcel->originHub()->first(), $parcel->destHub()->first(), $parcel->weight_kg ? (float) $parcel->weight_kg : null, (float) $parcel->cod_amount);

            // Re-route a parcel sitting in a hub (not on hold); waiting parcels start at their origin.
            if ($parcel->current_status === ParcelStatus::WaitingPickup) {
                $parcel->next_hub_id = $parcel->origin_hub_id;
            } elseif ($parcel->current_hub_id && $parcel->current_status !== ParcelStatus::OnHold && $parcel->current_status->isAtHub()) {
                $target = $parcel->is_return ? $parcel->originHub()->first() : $parcel->destHub()->first();
                $parcel->next_hub_id = $target ? $this->router->nextHop($parcel->currentHub()->first(), $target)?->id : null;
            }
            $parcel->save();

            // A parcel held for no route can move again.
            if (! $parcel->needs_address_fix) {
                ParcelException::withoutGlobalScopes()->where('parcel_id', $parcel->id)->where('type', 'no_route')->where('status', 'open')->get()
                    ->each(fn (ParcelException $e) => $this->desk->resolve($e, 'continue', $staff, 'Address fixed by HQ'));
            }
        });
    }
}
