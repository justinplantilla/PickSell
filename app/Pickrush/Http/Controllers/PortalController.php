<?php

namespace App\Pickrush\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\ManifestService;
use App\Pickrush\Support\Actor;
use DomainException;
use Illuminate\Http\RedirectResponse;

/** Shared helpers for the hub portals (station and province). */
abstract class PortalController extends Controller
{
    protected function staff(): Staff
    {
        return Actor::staff();
    }

    protected function hub(): Hub
    {
        return $this->staff()->hub;
    }

    /** A form's one-time key, tied to the parcel scanned with it (a stale page can never swallow another parcel). */
    protected static function scanKey(array $data): ?string
    {
        return empty($data['scan_key']) ? null : $data['scan_key'].':'.ManifestService::normalize($data['tracking_no']);
    }

    /** A scanned manifest barcode (MF…) of a trip arriving at this hub, if that is what was scanned. */
    protected static function arrivingTrip(string $scanned, int $hubId): ?\App\Pickrush\Models\Manifest
    {
        $code = ManifestService::normalize($scanned);

        return str_starts_with($code, 'MF')
            ? \App\Pickrush\Models\Manifest::where('manifest_no', $code)->where('to_hub_id', $hubId)->whereIn('status', ['dispatched', 'received'])->first()
            : null;
    }

    /** Run an operation; a business-rule refusal goes back to the form as an error message. */
    protected function attempt(callable $operation, string|callable $success): RedirectResponse
    {
        try {
            $result = $operation();
        } catch (DomainException $e) {
            return back()->withErrors(['operation' => $e->getMessage()])->withInput();
        }

        return back()->with('success', is_callable($success) ? $success($result) : $success);
    }
}
