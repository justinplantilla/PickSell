<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeliveryAssignmentResource;
use App\Models\DeliveryAssignment;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use App\Services\DeliveryDomainSynchronizer;
use App\Services\LogisticsRoutingService;
use App\Services\Orders\OrderLifecycleService;
use App\Services\Orders\RiderDeliveryWorkflow;
use App\Services\Orders\DeliveryStatusTransitionPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;
use RuntimeException;
use Throwable;

class RiderController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $rider = User::query()->where('email', $credentials['email'])->first();
        if (! $rider || ! Hash::check($credentials['password'], $rider->password)) {
            return response()->json(['message' => 'The provided credentials are invalid.'], 401);
        }
        abort_unless($rider->role === 'courier' && $rider->isApproved(), 403, 'This account cannot access the rider app.');

        return response()->json([
            'token' => $rider->createToken('flutter-rider', ['rider:read', 'rider:write'])->plainTextToken,
            'token_type' => 'Bearer',
            'rider' => $this->riderDetails($rider),
        ]);
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request)
    {
        return response()->json(['rider' => $this->riderDetails($request->user())]);
    }

    public function deliveries(Request $request)
    {
        $filters = $request->validate([
            'status' => ['sometimes', Rule::in(['offered', 'accepted', 'rejected', 'expired', 'cancelled'])],
        ]);

        $query = DeliveryAssignment::query()
            ->where('rider_id', $request->user()->id)
            ->with(['delivery.order.buyer', 'delivery.logs']);
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return DeliveryAssignmentResource::collection($query->latest('offered_at')->paginate(20));
    }

    public function respondToOffer(
        Request $request,
        int $assignment,
        LogisticsRoutingService $routing,
        OrderLifecycleService $lifecycle,
        DeliveryDomainSynchronizer $synchronizer,
    ) {
        $data = $request->validate([
            'action' => ['required_without:status', Rule::in(['accept', 'reject'])],
            'status' => ['required_without:action', Rule::in(['accepted', 'rejected'])],
            'reason' => ['nullable', 'string', 'min:5', 'max:1000'],
            'rejection_reason' => ['nullable', 'string', 'min:5', 'max:1000'],
        ]);
        $action = $data['action'] ?? match ($data['status']) {
            'accepted' => 'accept',
            'rejected' => 'reject',
        };
        $status = $action === 'accept' ? 'accepted' : 'rejected';
        $reason = $data['reason'] ?? $data['rejection_reason'] ?? null;

        $result = DB::transaction(function () use ($request, $assignment, $status, $reason, $routing, $lifecycle, $synchronizer): string {
            $offer = DeliveryAssignment::query()
                ->where('rider_id', $request->user()->id)
                ->lockForUpdate()
                ->findOrFail($assignment);
            abort_unless($offer->status === 'offered', 409, 'This rider offer has already been answered.');

            $delivery = $offer->delivery;
            $order = Order::query()->lockForUpdate()->findOrFail($delivery->order_id);
            abort_unless(
                $order->status === 'assigned_to_rider' && $order->courier_id === $request->user()->id,
                409,
                'This rider offer is no longer active.',
            );

            if ($offer->expires_at && $offer->expires_at->isPast()) {
                $offer->update(['status' => 'expired', 'responded_at' => now()]);
                $synchronizer->logAssignmentEvent(
                    $offer,
                    'offered',
                    'expired',
                    null,
                    'Rider offer expired before a response.',
                    'system',
                );
                $this->reofferAfterDecline($delivery, $order, $routing, $lifecycle);

                return 'expired';
            }

            $offer->update([
                'status' => $status,
                'responded_at' => now(),
                'rejection_reason' => $reason,
            ]);
            $synchronizer->logAssignmentEvent(
                $offer,
                'offered',
                $status,
                $request->user()->id,
                $reason,
            );

            if ($status === 'rejected') {
                $this->reofferAfterDecline($delivery, $order, $routing, $lifecycle);
            }

            return 'responded';
        });

        if ($result === 'expired') {
            return response()->json(['message' => 'This rider offer has expired.'], 409);
        }

        return new DeliveryAssignmentResource(
            DeliveryAssignment::with(['delivery.order.buyer', 'delivery.logs'])->findOrFail($assignment),
        );
    }

    private function reofferAfterDecline(
        Delivery $delivery,
        Order $order,
        LogisticsRoutingService $routing,
        OrderLifecycleService $lifecycle,
    ): void {
        $excludedRiders = DeliveryAssignment::query()
            ->where('delivery_id', $delivery->id)
            ->pluck('rider_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $nextRider = $routing->suggestedCourier($order, $excludedRiders);

        if ($nextRider) {
            $order->update(['courier_id' => $nextRider->id]);

            return;
        }

        $lifecycle->transition(
            $order,
            'at_sorting_center',
            null,
            'system',
            'No eligible rider remained after the offer was rejected or expired.',
        );
    }

    public function updateDeliveryStatus(
        Request $request,
        int $deliveryId,
        RiderDeliveryWorkflow $workflow,
        DeliveryStatusTransitionPolicy $policy,
    ) {
        $data = $request->validate([
            'status' => ['required', Rule::in(['out_for_delivery', 'delivered', 'delivery_failed'])],
            'failure_reason' => ['required_if:status,delivery_failed', 'nullable', 'string', 'min:5', 'max:1000'],
            'note' => ['nullable', 'string', 'max:1000'],
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        /** @var UploadedFile $photo */
        $photo = $data['photo'];
        $storedPath = null;
        try {
            DB::transaction(function () use ($request, $deliveryId, $data, $photo, $workflow, $policy, &$storedPath): void {
                $offer = DeliveryAssignment::query()
                    ->where('rider_id', $request->user()->id)
                    ->where('delivery_id', $deliveryId)
                    ->lockForUpdate()
                    ->latest('id')
                    ->firstOrFail();
                abort_unless($offer->status === 'accepted', 409, 'Accept the rider offer before updating delivery status.');
                $order = Order::query()->lockForUpdate()->findOrFail($offer->delivery->order_id);
                abort_unless($order->courier_id === $request->user()->id, 403, 'This delivery is no longer assigned to you.');
                abort_unless(
                    $policy->canTransition(
                        $order->status,
                        $data['status'],
                        'courier',
                        (int) $offer->delivery->delivery_attempts,
                    ),
                    409,
                    'This rider status transition is not allowed.',
                );

                $storedPath = $photo->store("deliveries/{$deliveryId}/proof", 'local');
                if ($storedPath === false) {
                    throw new RuntimeException('Unable to store delivery proof photo.');
                }

                $workflow->update(
                    $order,
                    $request->user(),
                    $data['status'],
                    $data['failure_reason'] ?? $data['note'] ?? null,
                    [
                        ...array_filter([
                            'latitude' => $data['latitude'] ?? null,
                            'longitude' => $data['longitude'] ?? null,
                        ], fn ($value) => $value !== null),
                        'proof_image_url' => $storedPath,
                    ],
                );
            });
        } catch (Throwable $exception) {
            if ($storedPath !== null) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }

        return new DeliveryAssignmentResource(
            DeliveryAssignment::with(['delivery.order.buyer', 'delivery.logs'])
                ->where('delivery_id', $deliveryId)
                ->where('rider_id', $request->user()->id)
                ->latest('id')
                ->firstOrFail(),
        );
    }

    public function assignments(Request $request)
    {
        $assignments = DeliveryAssignment::query()
            ->where('rider_id', $request->user()->id)
            ->where('status', 'offered')
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with(['delivery.order.buyer', 'delivery.logs'])
            ->latest('offered_at')
            ->paginate(20);

        return DeliveryAssignmentResource::collection($assignments);
    }

    public function showDeliveryById(Request $request, int $delivery)
    {
        $assignment = DeliveryAssignment::query()
            ->where('delivery_id', $delivery)
            ->where('rider_id', $request->user()->id)
            ->with(['delivery.order.buyer', 'delivery.logs'])
            ->latest('id')
            ->firstOrFail();

        return new DeliveryAssignmentResource($assignment);
    }

    public function showProofImage(Request $request, int $delivery, int $log)
    {
        $deliveryLog = \App\Models\DeliveryLog::query()
            ->where('delivery_id', $delivery)
            ->whereHas('delivery.assignments', fn ($query) => $query->where('rider_id', $request->user()->id))
            ->findOrFail($log);
        abort_unless($deliveryLog->proof_image_url, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($deliveryLog->proof_image_url), 404);

        return response()->file($disk->path($deliveryLog->proof_image_url));
    }

    private function riderDetails(User $rider): array
    {
        return [
            'id' => $rider->id,
            'name' => $rider->full_name,
            'email' => $rider->email,
            'contact_no' => $rider->contact_no,
            'delivery_area' => $rider->delivery_area,
        ];
    }
}
