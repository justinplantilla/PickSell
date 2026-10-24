<?php

namespace App\Pickrush\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Pickrush\Models\RiderBatch;
use App\Pickrush\Models\RiderReport;
use App\Pickrush\Services\Rider\DeliveryRoutes;
use App\Pickrush\Services\Rider\RiderBatches;
use App\Pickrush\Support\Actor;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Batches in the rider app: see them, accept or decline, arrive at each stop, arrive at the
 * station, and report a problem on the way. Pickups: scan the waybills at each seller. Deliveries:
 * scan every parcel onto the vehicle, start the route, then deliver (or fail) parcel by parcel.
 */
class RiderBatchController extends Controller
{
    public function __construct(private RiderBatches $batches) {}

    /** Open batches: offers waiting for an answer first, then accepted ones in progress. */
    public function index(): JsonResponse
    {
        return response()->json([
            'batches' => $this->batches->openFor(Actor::rider())->map(fn ($batch) => RiderBatchView::of($batch))->values(),
        ]);
    }

    public function show(int $batch): JsonResponse
    {
        $batch = $this->batches->sync($this->find($batch));

        return response()->json(['batch' => RiderBatchView::of($batch)]);
    }

    public function accept(int $batch): JsonResponse
    {
        return $this->attempt(fn () => $this->batches->accept($this->find($batch), Actor::rider()), 'Batch accepted. Head to the first stop.');
    }

    public function decline(Request $request, int $batch): JsonResponse
    {
        $data = $request->validate(['reason' => 'nullable|string|max:255']);

        return $this->attempt(fn () => $this->batches->decline($this->find($batch), Actor::rider(), $data['reason'] ?? null), 'Batch declined. The station will reassign it.');
    }

    /**
     * The rider scanned these waybills at a stop; any parcel they could not collect is sent
     * with a reason and goes back to the station's pickup queue.
     */
    public function pickUp(Request $request, int $batch): JsonResponse
    {
        $data = $request->validate([
            'tracking_nos' => 'present|array|max:200',
            'tracking_nos.*' => 'string|max:40',
            'not_collected' => 'nullable|array|max:200',
            'not_collected.*.tracking_no' => 'required|string|max:40',
            'not_collected.*.reason' => 'required|string|max:30',
            'not_collected.*.note' => 'nullable|string|max:255',
            'request_key' => 'nullable|string|max:60',     // the app's retry key, so a resend is not double-counted
        ]);
        if (! $data['tracking_nos'] && empty($data['not_collected'])) {
            return response()->json(['message' => 'Scan at least one waybill.'], 422);
        }
        $missed = collect($data['not_collected'] ?? [])->mapWithKeys(fn ($row) => [$row['tracking_no'] => ['reason' => $row['reason'], 'note' => $row['note'] ?? null]])->all();
        $found = $this->find($batch);

        return $this->attempt(function () use ($found, $data, $missed) {
            $this->batches->pickUp($found, Actor::rider(), $data['tracking_nos'], $missed, $data['request_key'] ?? null);

            return $found;
        }, $missed ? 'Pickup confirmed. Parcels not collected go back to the station.' : 'Pickup confirmed.');
    }

    /** Delivery: one parcel scanned onto the vehicle at the station. */
    public function load(Request $request, int $batch, DeliveryRoutes $routes): JsonResponse
    {
        $data = $request->validate(['tracking_no' => 'required|string|max:40']);

        return $this->attempt(function () use ($batch, $data, $routes) {
            $routes->load($this->find($batch), Actor::rider(), $data['tracking_no']);

            return $this->find($batch);
        }, 'Loaded.');
    }

    /** Delivery: leave the station; parcels not loaded need a reason and go back to the queue. */
    public function startRoute(Request $request, int $batch, DeliveryRoutes $routes): JsonResponse
    {
        $data = $request->validate(['leave_behind_reason' => 'nullable|string|max:255', 'request_key' => 'nullable|string|max:60']);

        return $this->attempt(fn () => $routes->startRoute($this->find($batch), Actor::rider(), $data['leave_behind_reason'] ?? null, $data['request_key'] ?? null),
            'Route started. Your parcels are out for delivery.');
    }

    /** Delivery: handed over at the door, with the scan, the COD confirmed and a proof photo. */
    public function deliver(Request $request, int $batch, int $assignment, DeliveryRoutes $routes): JsonResponse
    {
        $data = $request->validate([
            'tracking_no' => 'required|string|max:40',
            'cod_confirmed' => 'required|numeric|min:0',
            'photo' => 'required|file|mimes:jpg,jpeg,png,heic,webp|max:8192',
            'request_key' => 'nullable|string|max:60',
        ], ['photo.required' => 'Take a proof-of-delivery photo.']);

        return $this->attempt(fn () => $routes->deliver($this->find($batch), Actor::rider(), $assignment, $data['tracking_no'],
            (float) $data['cod_confirmed'], $request->file('photo'), $data['request_key'] ?? null), 'Delivered.');
    }

    /** Delivery: a failed attempt, with a reason and a photo. */
    public function fail(Request $request, int $batch, int $assignment, DeliveryRoutes $routes): JsonResponse
    {
        $data = $request->validate([
            'reason' => 'required|string|max:30',
            'note' => 'nullable|string|max:255',
            'photo' => 'required|file|mimes:jpg,jpeg,png,heic,webp|max:8192',
            'request_key' => 'nullable|string|max:60',
        ], ['photo.required' => 'Take a photo of the place (closed gate, house, etc.).']);

        return $this->attempt(fn () => $routes->fail($this->find($batch), Actor::rider(), $assignment, $data['reason'],
            $data['note'] ?? null, $request->file('photo'), $data['request_key'] ?? null), 'Failed delivery recorded. Bring the parcel back to the station.');
    }

    /** "Arrived at stop N", checked against the seller's shop pin. */
    public function arriveAtStop(Request $request, int $batch): JsonResponse
    {
        $data = $request->validate($this->position() + ['assignment_ids' => 'required|array|min:1|max:200', 'assignment_ids.*' => 'integer']);

        return $this->attempt(fn () => $this->batches->arriveAtStop($this->find($batch), Actor::rider(), array_map('intval', $data['assignment_ids']),
            (float) $data['lat'], (float) $data['lng'], isset($data['accuracy']) ? (float) $data['accuracy'] : null), 'Arrived. Scan each waybill.');
    }

    /** "Arrived at hub", checked against the station's pin. */
    public function arriveAtHub(Request $request, int $batch): JsonResponse
    {
        $data = $request->validate($this->position());

        return $this->attempt(fn () => $this->batches->arriveAtHub($this->find($batch), Actor::rider(),
            (float) $data['lat'], (float) $data['lng'], isset($data['accuracy']) ? (float) $data['accuracy'] : null), 'Arrived at the station. Hand the parcels to the staff.');
    }

    /** "Report a problem": lands on the station dashboard. */
    public function report(Request $request, int $batch): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', function ($attribute, $value, $fail) {
                if (! array_key_exists($value, config('pickrush.rider_problems'))) {
                    $fail('Pick a problem from the list.');
                }
            }],
            'note' => 'nullable|string|max:500|required_if:type,other',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
        ]);
        $found = $this->find($batch);
        $rider = Actor::rider();
        RiderReport::create([
            'rider_id' => $rider->id, 'station_hub_id' => $rider->station_hub_id, 'batch_id' => $found->id,
            'type' => $data['type'], 'note' => $data['note'] ?? null, 'lat' => $data['lat'] ?? null, 'lng' => $data['lng'] ?? null,
        ]);

        return response()->json(['message' => 'Sent to your station. They will contact you.']);
    }

    private function position(): array
    {
        return ['lat' => 'required|numeric|between:-90,90', 'lng' => 'required|numeric|between:-180,180', 'accuracy' => 'nullable|numeric|min:0'];
    }

    private function find(int $id): RiderBatch
    {
        return RiderBatch::where('rider_id', Actor::rider()->id)->findOrFail($id);
    }

    private function attempt(callable $operation, string $message): JsonResponse
    {
        try {
            $batch = $operation();
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => $message, 'batch' => RiderBatchView::of($batch->refresh())]);
    }
}
