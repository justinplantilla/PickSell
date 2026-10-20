<?php

namespace App\Pickrush\Http\Controllers;

use App\Pickrush\Models\ParcelException;
use App\Pickrush\Services\ExceptionDesk;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Exceptions for hub staff: what this hub found (to resolve) and what other hubs reported against
 * it (to answer for). HQ sees every hub's.
 */
class ExceptionController extends PortalController
{
    public function index(Request $request)
    {
        $status = $request->query('status') === 'resolved' ? 'resolved' : 'open';
        $hubId = $this->staff()->hub_id;

        return view('pickrush.exceptions.index', [
            'status' => $status,
            'found' => ParcelException::with('parcel', 'againstHub', 'manifest')->where('status', $status)
                ->when($hubId, fn ($q) => $q->where('hub_id', $hubId))->latest()->limit(200)->get(),
            'against' => $hubId ? ParcelException::with('parcel', 'hub', 'manifest')->where('status', $status)->where('against_hub_id', $hubId)->latest()->limit(100)->get() : collect(),
            'actions' => ExceptionDesk::ACTIONS,
            'reportable' => array_intersect_key(ParcelException::TYPES, array_flip(['damaged', 'no_route', 'unlabeled'])),
        ]);
    }

    public function store(Request $request, ExceptionDesk $desk)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['damaged', 'no_route', 'unlabeled'])],
            'tracking_no' => 'required_unless:type,unlabeled|nullable|string|max:30',
            'note' => 'required|string|max:1000',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
        ]);
        $photo = $request->file('photo')?->store('pickrush/exceptions', 'local');

        return $this->attempt(fn () => $desk->report($data['type'], $this->staff(), $data['tracking_no'] ?? null, $data['note'], $photo),
            fn (ParcelException $e) => $e->parcel ? "{$e->label()} reported. {$e->parcel->tracking_no} is on hold until resolved." : "{$e->label()} reported.");
    }

    public function resolve(Request $request, ParcelException $exception, ExceptionDesk $desk)
    {
        $data = $request->validate([
            'action' => 'required|string|max:30',
            'note' => 'required|string|max:1000',
            'tracking_no' => 'required_if:action,link|nullable|string|max:30',
        ]);

        return $this->attempt(fn () => $desk->resolve($exception, $data['action'], $this->staff(), $data['note'], $data['tracking_no'] ?? null),
            'Exception resolved.');
    }
}
