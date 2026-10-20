<?php

namespace App\Pickrush\Services;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Manifest;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\ParcelException;
use App\Pickrush\Models\Staff;
use Illuminate\Support\Facades\DB;

/**
 * Exceptions and their resolutions. Every exception has a type, a parcel (except unlabeled ones),
 * a reason and a fixed set of actions. A parcel with an open problem is put on hold so it cannot
 * leave on a trip by accident; resolving it puts it back where it was or starts the return.
 */
class ExceptionDesk
{
    /** type => resolution => label */
    public const ACTIONS = [
        'missing' => ['mark_lost' => 'Mark as lost', 'close' => 'Close with a note (found or explained)'],
        'damaged' => ['continue' => 'Repacked: continue', 'return_to_sender' => 'Return to sender'],
        'no_route' => ['continue' => 'Route fixed: continue', 'return_to_sender' => 'Return to sender'],
        'mis_sorted' => ['close' => 'Rerouted: close'],
        'unlabeled' => ['link' => 'Identified: link to tracking number', 'close' => 'Close with a note'],
    ];

    /** Manager-only resolutions. */
    private const MANAGER_ONLY = ['mark_lost'];

    public function __construct(private ParcelLedger $ledger, private ManifestService $manifests) {}

    /** Damaged packaging, or a no-route parcel found on the floor: hold it and record why. */
    public function report(string $type, Staff $staff, ?string $trackingNo, string $note, ?string $photoPath = null): ParcelException
    {
        if (! in_array($type, ['damaged', 'no_route', 'unlabeled'], true)) {
            throw new OperationRefused('Missing and mis-sorted parcels are reported from the trip check and the scan screens.');
        }
        $hub = $staff->hub;

        return DB::transaction(function () use ($type, $staff, $trackingNo, $note, $photoPath, $hub) {
            $parcel = null;
            if ($type !== 'unlabeled') {
                $parcel = $this->onHand($trackingNo, $hub);
                $this->hold($parcel, $hub, $note);
            }

            return ParcelException::create([
                'hub_id' => $hub->id, 'parcel_id' => $parcel?->id, 'type' => $type, 'note' => $note,
                'photo_path' => $photoPath, 'opened_by' => $staff->id,
            ]);
        });
    }

    /**
     * A parcel that arrived here although it was going elsewhere: receive it here, let the router
     * send it on the right way, and log it against the hub that sent it.
     */
    public function receiveMisSorted(string $trackingNo, Staff $staff, ?string $note = null): ParcelException
    {
        $hub = $staff->hub;
        $parcel = Parcel::withoutGlobalScopes()->where('tracking_no', ManifestService::normalize($trackingNo))->first()
            ?? throw new OperationRefused('Unknown tracking number.');
        $inTransit = in_array($parcel->current_status, [ParcelStatus::InTransitToProvince, ParcelStatus::InTransitToStation], true);
        if (! $inTransit || $parcel->next_hub_id === $hub->id) {
            throw new OperationRefused("{$parcel->tracking_no} is not mis-sorted: ".($inTransit ? 'it is due here; scan it in normally.' : "it is {$parcel->current_status->label()}."));
        }

        return DB::transaction(function () use ($parcel, $hub, $staff, $note) {
            $intended = $parcel->nextHub;
            $trip = Manifest::withoutGlobalScopes()->whereIn('status', ['dispatched', 'received'])
                ->whereHas('items', fn ($q) => $q->where('parcel_id', $parcel->id))->latest('dispatched_at')->first();

            $this->ledger->record($parcel, $hub->isProvince() ? ParcelStatus::AtProvinceHub : ParcelStatus::ArrivedAtStation, $hub, [
                'note' => "Mis-sorted: was going to {$intended?->name}",
            ]);

            return ParcelException::create([
                'hub_id' => $hub->id, 'against_hub_id' => $trip?->from_hub_id, 'parcel_id' => $parcel->id, 'manifest_id' => $trip?->id,
                'type' => 'mis_sorted', 'note' => trim("Arrived here instead of {$intended?->name}. {$note}"), 'opened_by' => $staff->id,
            ]);
        });
    }

    public function resolve(ParcelException $exception, string $action, Staff $staff, string $note, ?string $trackingNo = null): void
    {
        $actions = self::ACTIONS[$exception->type] ?? [];
        if ($exception->status !== 'open' || ! array_key_exists($action, $actions)) {
            throw new OperationRefused('That action does not apply to this exception.');
        }
        if ($exception->hub_id !== $staff->hub_id && ! $staff->isCompany()) {
            throw new OperationRefused('The hub that found it resolves it.');
        }
        if (in_array($action, self::MANAGER_ONLY, true) && ! $staff->hasRole(StaffRole::HubManager, StaffRole::SuperAdmin)) {
            throw new OperationRefused('Only the hub manager can mark a parcel as lost.');
        }

        DB::transaction(function () use ($exception, $action, $staff, $note, $trackingNo) {
            $parcel = $exception->parcel;
            $hub = $exception->hub;

            match ($action) {
                'mark_lost' => $this->markLost($parcel, $note),
                'continue' => $this->release($parcel, $hub, $note),
                'return_to_sender' => $this->ledger->record($this->held($parcel, $hub), ParcelStatus::ReturningToSender, $hub, ['note' => $note]),
                'link' => $exception->parcel_id = $this->link($trackingNo, $hub)->id,
                'close' => null,
            };

            $exception->fill(['status' => 'resolved', 'resolution' => $action, 'resolution_note' => $note,
                'resolved_by' => $staff->id, 'resolved_at' => now()])->save();
        });
    }

    private function onHand(?string $trackingNo, Hub $hub): Parcel
    {
        $parcel = Parcel::withoutGlobalScopes()->where('tracking_no', ManifestService::normalize((string) $trackingNo))->first()
            ?? throw new OperationRefused('Unknown tracking number. Report it as unlabeled instead.');
        if ($parcel->current_hub_id !== $hub->id || ! $parcel->current_status->isAtHub()) {
            throw new OperationRefused("{$parcel->tracking_no} is not on hand here ({$parcel->current_status->label()}).");
        }

        return $parcel;
    }

    private function hold(Parcel $parcel, Hub $hub, string $note): void
    {
        if ($parcel->current_status !== ParcelStatus::OnHold) {
            $this->ledger->record($parcel, ParcelStatus::OnHold, $hub, ['note' => $note]);
        }
    }

    /** Make sure the parcel is on hold before it changes course. */
    private function held(Parcel $parcel, Hub $hub): Parcel
    {
        $this->hold($parcel, $hub, 'Held for exception resolution');

        return $parcel->refresh();
    }

    /** Off hold, back to the status it had in this hub before. */
    private function release(Parcel $parcel, Hub $hub, string $note): void
    {
        if ($parcel->current_status !== ParcelStatus::OnHold) {
            return;
        }
        $before = $parcel->events()->reorder()->latest('id')->where('status', '!=', ParcelStatus::OnHold)->first()?->status;
        $back = in_array($before, [ParcelStatus::AtOriginHub, ParcelStatus::AtProvinceHub, ParcelStatus::ArrivedAtStation, ParcelStatus::ReturningToSender], true)
            ? $before
            : ($hub->isProvince() ? ParcelStatus::AtProvinceHub : ParcelStatus::ArrivedAtStation);
        $this->ledger->record($parcel, $back, $hub, ['note' => "Released: {$note}"]);
    }

    private function markLost(?Parcel $parcel, string $note): void
    {
        if (! $parcel) {
            return;
        }
        if (! $parcel->current_status->canMoveTo(ParcelStatus::Lost)) {
            throw new OperationRefused("{$parcel->tracking_no} has been scanned since ({$parcel->current_status->label()}); it is not lost.");
        }
        $this->ledger->record($parcel, ParcelStatus::Lost, null, ['note' => $note]);
    }

    /** An unlabeled parcel was identified: receive it under its tracking number. */
    private function link(?string $trackingNo, Hub $hub): Parcel
    {
        $parcel = Parcel::withoutGlobalScopes()->where('tracking_no', ManifestService::normalize((string) $trackingNo))->first()
            ?? throw new OperationRefused('Unknown tracking number.');
        if ($parcel->next_hub_id === $hub->id && in_array($parcel->current_status, [ParcelStatus::InTransitToProvince, ParcelStatus::InTransitToStation], true)) {
            $this->manifests->arrive($parcel, $hub);
        }

        return $parcel;
    }
}
