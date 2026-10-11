<?php

namespace Tests\Feature;

use App\Models\DeliveryAssignment;
use App\Models\Delivery;
use App\Models\DeliveryLog;
use App\Models\Barangay;
use App\Models\BranchRider;
use App\Models\LogisticsBranch;
use App\Models\Municipality;
use App\Models\Order;
use App\Models\RiderBarangay;
use App\Models\User;
use App\Services\DeliveryOfferExpiryService;
use App\Services\LogisticsRoutingService;
use App\Services\Orders\OrderLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RiderApiTest extends TestCase
{
    use RefreshDatabase;

    private User $rider;
    private User $buyer;
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rider = $this->user('courier', 'rider');
        $this->buyer = $this->user('buyer', 'buyer');
        $this->seller = $this->user('seller', 'seller');
        Storage::fake('local');
    }

    public function test_rider_can_sign_in_and_receive_a_bearer_token(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->rider->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('rider.id', $this->rider->id)
            ->assertJsonStructure(['token', 'token_type', 'rider' => ['id', 'name', 'email']]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $this->rider->id,
            'name' => 'flutter-rider',
        ]);
        $this->assertSame(
            ['rider:read', 'rider:write'],
            $this->rider->tokens()->sole()->abilities,
        );
    }

    public function test_non_riders_and_unapproved_riders_cannot_sign_in(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => $this->buyer->email,
            'password' => 'password',
        ])->assertForbidden();

        $pendingRider = $this->user('courier', 'pending-rider', 'pending');
        $this->postJson('/api/v1/auth/login', [
            'email' => $pendingRider->email,
            'password' => 'password',
        ])->assertForbidden();
    }

    public function test_rider_api_requires_a_token_and_scopes_deliveries_to_the_signed_in_rider(): void
    {
        $this->getJson('/api/v1/rider/deliveries')->assertUnauthorized();

        $assignment = $this->assignedDelivery();
        $token = $this->riderToken();

        $this->withToken($token)
            ->getJson('/api/v1/rider/deliveries')
            ->assertOk()
            ->assertJsonPath('data.0.id', $assignment->id)
            ->assertJsonPath('data.0.delivery.order.order_number', $assignment->delivery->order->order_number)
            ->assertJsonPath('data.0.delivery.recipient.name', $this->buyer->full_name)
            ->assertJsonPath('data.0.delivery.tracking_number', $assignment->delivery->tracking_number);

        $anotherRider = $this->user('courier', 'another-rider');
        $anotherToken = $this->riderToken($anotherRider);
        Auth::forgetGuards();
        $this->flushHeaders()
            ->withToken($anotherToken)
            ->getJson('/api/v1/rider/me')
            ->assertJsonPath('rider.id', $anotherRider->id);

        $this->withToken($anotherToken)
            ->getJson("/api/v1/rider/deliveries/{$assignment->delivery_id}")
            ->assertNotFound();
    }

    public function test_tokens_must_have_the_ability_required_by_each_endpoint(): void
    {
        $readOnlyToken = $this->rider->createToken('read-only-rider', ['rider:read'])->plainTextToken;
        $this->withToken($readOnlyToken)
            ->getJson('/api/v1/rider/assignments')
            ->assertOk();

        $this->withToken($readOnlyToken)
            ->postJson('/api/v1/rider/assignments/1/respond', ['action' => 'accept'])
            ->assertForbidden();

        $writeOnlyToken = $this->rider->createToken('write-only-rider', ['rider:write'])->plainTextToken;
        Auth::forgetGuards();
        $this->flushHeaders()
            ->withToken($writeOnlyToken)
            ->getJson('/api/v1/rider/assignments')
            ->assertForbidden();
    }

    public function test_assignments_and_delivery_details_return_only_owned_records_and_items(): void
    {
        $assignment = $this->assignedDelivery();
        $token = $this->riderToken();

        $this->withToken($token)
            ->getJson('/api/v1/rider/assignments')
            ->assertOk()
            ->assertJsonPath('data.0.id', $assignment->id)
            ->assertJsonPath('data.0.assignment_status', 'offered');

        $this->withToken($token)
            ->getJson("/api/v1/rider/deliveries/{$assignment->delivery_id}")
            ->assertOk()
            ->assertJsonPath('data.delivery.recipient.contact_no', $this->buyer->contact_no)
            ->assertJsonPath('data.delivery.order.items.0.name', 'Rider API parcel')
            ->assertJsonPath('data.delivery.order.items.0.quantity', 1);
    }

    public function test_status_upload_requires_an_image_and_enforces_the_five_megabyte_limit(): void
    {
        $assignment = $this->assignedDelivery();
        $token = $this->riderToken();

        $this->withToken($token)
            ->post("/api/v1/rider/deliveries/{$assignment->delivery_id}/status", [
                'status' => 'out_for_delivery',
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');

        $this->withToken($token)
            ->post("/api/v1/rider/deliveries/{$assignment->delivery_id}/status", [
                'status' => 'out_for_delivery',
                'photo' => UploadedFile::fake()->createWithContent('proof.pdf', '%PDF-1.4 not an image'),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');

        $this->withToken($token)
            ->post("/api/v1/rider/deliveries/{$assignment->delivery_id}/status", [
                'status' => 'out_for_delivery',
                'photo' => UploadedFile::fake()->createWithContent(
                    'large.png',
                    file_get_contents(public_path('images/transparent logo.png')).str_repeat('x', 5 * 1024 * 1024),
                ),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');
    }

    public function test_status_policy_prevents_riders_from_skipping_delivery_states(): void
    {
        $assignment = $this->assignedDelivery();
        $token = $this->riderToken();
        $this->withToken($token)
            ->postJson("/api/v1/rider/assignments/{$assignment->id}/respond", ['action' => 'accept'])
            ->assertOk();

        $this->submitStatus($token, $assignment->delivery, ['status' => 'delivered'])
            ->assertStatus(409);

        $this->assertSame('assigned_to_rider', $assignment->delivery->order->fresh()->status);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_uploaded_proof_photos_are_private_and_require_delivery_ownership(): void
    {
        $assignment = $this->assignedDelivery();
        $token = $this->riderToken();
        $this->withToken($token)
            ->postJson("/api/v1/rider/assignments/{$assignment->id}/respond", ['action' => 'accept'])
            ->assertOk();

        $this->submitStatus($token, $assignment->delivery, ['status' => 'out_for_delivery'])
            ->assertOk();
        $proof = DeliveryLog::where('delivery_id', $assignment->delivery_id)
            ->whereNotNull('proof_image_url')
            ->sole();
        Storage::disk('local')->assertExists($proof->proof_image_url);

        $this->withToken($token)
            ->get(route('api.v1.rider.deliveries.proof', [$assignment->delivery_id, $proof->id], false))
            ->assertOk();

        $otherRiderToken = $this->riderToken($this->user('courier', 'unrelated-rider'));
        Auth::forgetGuards();
        $this->flushHeaders()
            ->withToken($otherRiderToken)
            ->get(route('api.v1.rider.deliveries.proof', [$assignment->delivery_id, $proof->id], false))
            ->assertNotFound();
    }

    public function test_order_lifecycle_updates_are_synchronized_to_delivery_assignments_and_logs(): void
    {
        $assignment = $this->assignedDelivery();
        $token = $this->riderToken();

        $this->submitStatus($token, $assignment->delivery, [
                'status' => 'out_for_delivery',
                'note' => 'Parcel picked up.',
                'latitude' => 14.5764,
                'longitude' => 121.0851,
            ])->assertStatus(409);

        $this->withToken($token)
            ->postJson("/api/v1/rider/assignments/{$assignment->id}/respond", ['action' => 'accept'])
            ->assertOk()
            ->assertJsonPath('data.assignment_status', 'accepted');
        $this->assertSame($assignment->id, $assignment->delivery->fresh()->currentAssignment->id);

        $this->submitStatus($token, $assignment->delivery, [
                'status' => 'out_for_delivery',
                'note' => 'Parcel picked up.',
                'latitude' => 14.5764,
                'longitude' => 121.0851,
            ])
            ->assertOk()
            ->assertJsonPath('data.delivery.status', 'out_for_delivery')
            ->assertJsonPath('data.delivery.logs.4.to_status', 'out_for_delivery')
            ->assertJsonPath('data.delivery.logs.4.proof_image_url', route('api.v1.rider.deliveries.proof', [
                $assignment->delivery_id,
                DeliveryLog::where('delivery_id', $assignment->delivery_id)->orderBy('id')->skip(4)->value('id'),
            ]))
            ->assertJsonPath('data.delivery.logs.4.latitude', '14.5764000');

        $this->submitStatus($token, $assignment->delivery, [
                'status' => 'delivery_failed',
                'failure_reason' => 'Recipient was not available.',
            ])
            ->assertOk()
            ->assertJsonPath('data.delivery.status', 'assigned_to_rider')
            ->assertJsonPath('data.delivery.delivery_attempts', 1)
            ->assertJsonPath('data.delivery.logs.5.note', 'Recipient was not available.');

        $this->assertSame('assigned_to_rider', $assignment->delivery->order->fresh()->status);
        $this->submitStatus($token, $assignment->delivery, ['status' => 'out_for_delivery'])->assertOk();
        $this->submitStatus($token, $assignment->delivery, [
                'status' => 'delivery_failed',
                'failure_reason' => 'Recipient was not available again.',
            ])
            ->assertOk()
            ->assertJsonPath('data.delivery.delivery_attempts', 2)
            ->assertJsonPath('data.delivery.status', 'assigned_to_rider');
        $this->assertSame(10, DeliveryLog::where('delivery_id', $assignment->delivery_id)->count());
        $log = DeliveryLog::where('delivery_id', $assignment->delivery_id)->latest('id')->firstOrFail();
        $this->assertSame('rider', $log->actor_role);
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('delivery_logs', 'updated_at'));
        $this->expectException(\LogicException::class);
        $log->update(['note' => 'Attempted to edit append-only history.']);
    }

    public function test_fourth_failed_delivery_attempt_goes_to_sender_and_logistics_confirms_return(): void
    {
        $assignment = $this->assignedDelivery();
        $assignment->delivery->order->update(['logistics_id' => $this->user('logistics', 'logistics')->id]);
        $token = $this->riderToken();
        $accepted = $this->withToken($token)
            ->postJson("/api/v1/rider/assignments/{$assignment->id}/respond", ['action' => 'accept']);
        $this->assertSame(200, $accepted->getStatusCode(), $accepted->getContent());
        $this->submitStatus($token, $assignment->delivery, ['status' => 'out_for_delivery'])->assertOk();

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            if ($attempt > 1) {
                $this->submitStatus($token, $assignment->delivery, ['status' => 'out_for_delivery'])->assertOk();
            }

            $response = $this->submitStatus($token, $assignment->delivery, [
                    'status' => 'delivery_failed',
                    'failure_reason' => "Delivery attempt {$attempt} failed.",
                ]);
            $this->assertSame(200, $response->getStatusCode(), "Attempt {$attempt}: ".$response->getContent());

            if ($attempt <= 3) {
                $response->assertJsonPath('data.delivery.status', 'assigned_to_rider')
                    ->assertJsonPath('data.delivery.delivery_attempts', $attempt);
            } else {
                $response->assertJsonPath('data.delivery.status', 'return_to_sender')
                    ->assertJsonPath('data.delivery.delivery_attempts', 4);
            }
        }

        $logistics = User::where('role', 'logistics')->sole();
        $this->actingAs($logistics)
            ->patch(route('logistics.parcels.returned', $assignment->delivery->order))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('returned', $assignment->delivery->fresh()->status);
        $this->assertNotNull($assignment->delivery->fresh()->returned_at);
        $this->assertSame('returned', $assignment->delivery->order->fresh()->status);
        $this->assertDatabaseHas('delivery_logs', [
            'delivery_id' => $assignment->delivery_id,
            'from_status' => 'return_to_sender',
            'to_status' => 'returned',
            'actor_role' => 'logistics',
        ]);
    }

    public function test_reassignment_keeps_one_delivery_per_order_and_preserves_rider_log_history(): void
    {
        $assignment = $this->assignedDelivery();
        $firstRiderId = $this->rider->id;
        $replacement = $this->user('courier', 'replacement-rider');
        $order = $assignment->delivery->order;
        $lifecycle = app(OrderLifecycleService::class);

        $lifecycle->transition($order, 'at_sorting_center', $this->seller->id, 'logistics', null);
        $lifecycle->transition($order->fresh(), 'sorted', $this->seller->id, 'logistics', null);
        $lifecycle->transition(
            $order->fresh(),
            'assigned_to_rider',
            $this->seller->id,
            'logistics',
            null,
            ['courier_id' => $replacement->id],
        );

        $newAssignment = DeliveryAssignment::where('delivery_id', $assignment->delivery_id)
            ->where('rider_id', $replacement->id)->sole();
        $this->assertSame(2, DeliveryAssignment::where('delivery_id', $assignment->delivery_id)->count());
        $this->assertSame('cancelled', $assignment->fresh()->status);
        $this->assertDatabaseHas('delivery_logs', [
            'delivery_id' => $assignment->delivery_id,
            'actor_role' => 'logistics',
            'to_status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('delivery_logs', [
            'delivery_id' => $assignment->delivery_id,
            'to_status' => 'offered',
        ]);
        $this->assertSame($firstRiderId, $assignment->fresh()->rider_id);
        $this->assertSame('offered', $newAssignment->status);
    }

    public function test_rejected_offer_is_reoffered_to_another_eligible_rider(): void
    {
        $assignment = $this->assignedDelivery();
        $replacement = $this->user('courier', 'replacement-rider');
        $this->makeRiderEligibleForDelivery($assignment->delivery->order, $replacement);

        $token = $this->riderToken();
        $this->withToken($token)
            ->postJson("/api/v1/rider/assignments/{$assignment->id}/respond", [
                'action' => 'reject',
                'reason' => 'Unable to deliver in this area.',
            ])
            ->assertOk()
            ->assertJsonPath('data.assignment_status', 'rejected');

        $reoffer = DeliveryAssignment::query()
            ->where('delivery_id', $assignment->delivery_id)
            ->where('rider_id', $replacement->id)
            ->sole();
        $this->assertSame('offered', $reoffer->status);
        $this->assertSame($replacement->id, $assignment->delivery->order->fresh()->courier_id);
        $this->assertSame(1, Delivery::where('order_id', $assignment->delivery->order_id)->count());
    }

    public function test_expired_offer_is_recorded_and_routed_to_another_eligible_rider(): void
    {
        $assignment = $this->assignedDelivery();
        $replacement = $this->user('courier', 'replacement-rider');
        $this->makeRiderEligibleForDelivery($assignment->delivery->order, $replacement);
        $assignment->update(['expires_at' => now()->subMinute()]);

        $token = $this->riderToken();
        $this->withToken($token)
            ->postJson("/api/v1/rider/assignments/{$assignment->id}/respond", ['action' => 'accept'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'This rider offer has expired.');

        $this->assertSame('expired', $assignment->fresh()->status);
        $this->assertSame($replacement->id, $assignment->delivery->order->fresh()->courier_id);
        $this->assertSame('offered', DeliveryAssignment::where('rider_id', $replacement->id)->sole()->status);
    }

    public function test_expired_offers_are_routed_by_the_scheduled_expiry_service(): void
    {
        $assignment = $this->assignedDelivery();
        $replacement = $this->user('courier', 'replacement-rider');
        $this->makeRiderEligibleForDelivery($assignment->delivery->order, $replacement);
        $assignment->update(['expires_at' => now()->subMinute()]);

        $this->assertSame(1, app(DeliveryOfferExpiryService::class)->expireDue());
        $this->assertSame('expired', $assignment->fresh()->status);
        $this->assertSame($replacement->id, $assignment->delivery->order->fresh()->courier_id);
        $this->assertSame('offered', DeliveryAssignment::where('rider_id', $replacement->id)->sole()->status);
        $this->assertSame(0, app(DeliveryOfferExpiryService::class)->expireDue());
    }

    public function test_reoffer_prefers_primary_coverage_then_least_recently_assigned_rider(): void
    {
        $assignment = $this->assignedDelivery();
        $order = $assignment->delivery->order;
        $municipality = Municipality::create(['province' => 'Metro Manila', 'name' => 'Pasig']);
        $barangay = Barangay::create(['municipality_id' => $municipality->id, 'name' => 'San Miguel']);
        $branch = LogisticsBranch::create([
            'municipality_id' => $municipality->id,
            'name' => 'Routing Priority Hub',
            'status' => 'active',
        ]);
        $order->update([
            'destination_branch_id' => $branch->id,
            'destination_barangay_id' => $barangay->id,
        ]);

        $primaryRecent = $this->user('courier', 'primary-recent');
        $primaryOld = $this->user('courier', 'primary-old');
        $nonPrimary = $this->user('courier', 'non-primary-old');
        foreach ([[$primaryRecent, true], [$primaryOld, true], [$nonPrimary, false]] as [$rider, $isPrimary]) {
            $branchRider = BranchRider::create([
                'branch_id' => $branch->id,
                'user_id' => $rider->id,
                'status' => 'active',
            ]);
            RiderBarangay::create([
                'branch_rider_id' => $branchRider->id,
                'barangay_id' => $barangay->id,
                'is_primary' => $isPrimary,
            ]);
        }
        DeliveryAssignment::create([
            'delivery_id' => $assignment->delivery_id,
            'rider_id' => $primaryRecent->id,
            'status' => 'rejected',
            'offered_at' => now()->subDays(1),
        ]);
        DeliveryAssignment::create([
            'delivery_id' => $assignment->delivery_id,
            'rider_id' => $primaryOld->id,
            'status' => 'rejected',
            'offered_at' => now()->subDays(5),
        ]);
        DeliveryAssignment::create([
            'delivery_id' => $assignment->delivery_id,
            'rider_id' => $nonPrimary->id,
            'status' => 'rejected',
            'offered_at' => now()->subDays(30),
        ]);

        $suggestion = app(LogisticsRoutingService::class)->suggestedCourier($order, [
            $this->rider->id,
            $primaryRecent->id,
            $primaryOld->id,
            $nonPrimary->id,
        ]);

        $this->assertNull($suggestion);
        $suggestion = app(LogisticsRoutingService::class)->suggestedCourier($order, [$this->rider->id]);
        $this->assertSame($primaryOld->id, $suggestion?->id);
    }

    public function test_buyer_sees_the_delivery_tracking_number_not_the_seller_waybill(): void
    {
        $assignment = $this->assignedDelivery();
        $assignment->delivery->order->update(['waybill_number' => 'SELLER-WB-007']);
        $trackingNumber = $assignment->delivery->tracking_number;

        $this->actingAs($this->buyer)
            ->get('/buyer/orders')
            ->assertOk()
            ->assertSee('Tracking #:')
            ->assertSee($trackingNumber)
            ->assertDontSee('SELLER-WB-007');
    }

    public function test_delivery_failure_requires_a_reason_and_invalid_transitions_are_rejected(): void
    {
        $assignment = $this->assignedDelivery();
        $token = $this->riderToken();

        $this->submitStatus($token, $assignment->delivery, [
                'status' => 'delivery_failed',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('failure_reason');

        $this->submitStatus($token, $assignment->delivery, [
                'status' => 'delivered',
            ])
            ->assertStatus(409);

        $this->assertSame('assigned_to_rider', $assignment->delivery->order->fresh()->status);
        $this->assertSame(3, DeliveryLog::where('delivery_id', $assignment->delivery_id)->count());
    }

    public function test_logout_revokes_the_current_mobile_token(): void
    {
        $token = $this->riderToken();
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        Auth::forgetGuards();
        $this->getJson('/api/v1/rider/me')->assertUnauthorized();
    }

    private function assignedDelivery(): DeliveryAssignment
    {
        $order = Order::create([
            'order_number' => 'ORD-API-'.uniqid(),
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'product_name' => 'Rider API parcel',
            'quantity' => 1,
            'amount' => 100,
            'commission' => 10,
            'status' => 'sorted',
        ]);

        app(OrderLifecycleService::class)->transition(
            $order,
            'assigned_to_rider',
            $this->seller->id,
            'logistics',
            null,
            ['courier_id' => $this->rider->id],
        );

        return DeliveryAssignment::query()->whereHas('delivery', fn ($query) => $query->where('order_id', $order->id))->sole();
    }

    private function user(string $role, string $label, string $status = 'approved'): User
    {
        return User::create([
            'first_name' => ucfirst($label),
            'last_name' => 'Test',
            'email' => "{$label}@rider-api.test",
            'password' => bcrypt('password'),
            'role' => $role,
            'status' => $status,
            'sex' => 'Male',
            'contact_no' => '09171234567',
            'birthday' => '1990-01-01',
            'age' => 36,
            'province' => 'Metro Manila',
            'municipality' => 'Pasig',
            'barangay' => 'San Miguel',
        ]);
    }

    private function makeRiderEligibleForDelivery(Order $order, User $rider): void
    {
        $municipality = Municipality::create(['province' => 'Metro Manila', 'name' => 'Pasig']);
        $barangay = Barangay::create(['municipality_id' => $municipality->id, 'name' => 'San Miguel']);
        $branch = LogisticsBranch::create([
            'municipality_id' => $municipality->id,
            'name' => 'Pasig Delivery Hub',
            'status' => 'active',
        ]);
        $branchRider = BranchRider::create([
            'branch_id' => $branch->id,
            'user_id' => $rider->id,
            'status' => 'active',
        ]);
        RiderBarangay::create(['branch_rider_id' => $branchRider->id, 'barangay_id' => $barangay->id]);
        $order->update([
            'destination_branch_id' => $branch->id,
            'destination_barangay_id' => $barangay->id,
        ]);
    }

    private function riderToken(?User $rider = null): string
    {
        return ($rider ?? $this->rider)
            ->createToken('test-rider', ['rider:read', 'rider:write'])
            ->plainTextToken;
    }

    private function submitStatus(string $token, Delivery $delivery, array $data)
    {
        return $this->withToken($token)->post(
            "/api/v1/rider/deliveries/{$delivery->id}/status",
            ['photo' => $this->fakeProofPhoto(), ...$data],
            ['Accept' => 'application/json'],
        );
    }

    private function fakeProofPhoto(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'proof.png',
            file_get_contents(public_path('images/transparent logo.png')),
        );
    }
}
