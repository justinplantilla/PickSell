<?php

namespace Tests\Feature;

use App\Models\DeliveryLog;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReturnRefundWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_request_a_return_for_a_delivered_order_without_changing_shipping_status(): void
    {
        $buyer = $this->makeUser('buyer');
        $seller = $this->makeUser('seller');
        $order = $this->makeOrder($buyer, $seller, 'completed');
        $order->update(['quantity' => 2, 'amount' => 2400]);
        Storage::fake('public');

        $this->actingAs($buyer)
            ->get(route('buyer.orders'))
            ->assertOk()
            ->assertSee('Request return or refund');

        $this->actingAs($buyer)
            ->post(route('buyer.orders.returns.store', $order), [
                'reason' => 'damaged',
                'details' => 'The item arrived damaged.',
                'quantity' => 1,
                'attachments' => [UploadedFile::fake()->create('damaged-item.png', 10, 'image/png')],
            ])
            ->assertRedirect(route('buyer.orders'));

        $returnRequest = ReturnRequest::where('order_id', $order->id)->firstOrFail();
        $this->assertSame('requested', $returnRequest->status);
        $this->assertSame(1, $returnRequest->quantity);
        $this->assertSame('1200.00', $returnRequest->refund_amount);
        $this->assertCount(1, $returnRequest->attachments);
        Storage::disk('public')->assertExists($returnRequest->attachments[0]);
        $this->assertSame('completed', $order->fresh()->status);

        $this->actingAs($seller)
            ->get(route('seller.returns.show', $returnRequest))
            ->assertOk()
            ->assertSee('Item context')
            ->assertSee('Quantity requested')
            ->assertSee('1 of 2 units')
            ->assertSee('Not assigned')
            ->assertSee('data-return-attachment-open', false)
            ->assertSee('Buyer submitted request')
            ->assertSee('The item arrived damaged.');

        $this->actingAs($buyer)
            ->get(route('buyer.orders'))
            ->assertOk()
            ->assertSee('Requested')
            ->assertSee('Return / refund')
            ->assertSee('data-return-attachment-open', false);

        $this->actingAs($seller)
            ->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee('1 return request awaiting response')
            ->assertSee(route('seller.returns', ['status' => 'requested']))
            ->assertSee(route('seller.returns.count'))
            ->assertSee('sellerReturnsBadge');

        $this->get(route('seller.returns.count'))
            ->assertOk()
            ->assertExactJson(['count' => 1]);

        $this->actingAs($buyer)
            ->post(route('buyer.orders.returns.store', $order), [
                'reason' => 'damaged',
                'details' => 'Duplicate request.',
            ])
            ->assertStatus(409);
    }

    public function test_buyer_has_an_owned_delivery_tracking_page_with_the_delivery_log_timeline(): void
    {
        $buyer = $this->makeUser('buyer');
        $seller = $this->makeUser('seller');
        $otherBuyer = $this->makeUser('buyer');
        $order = $this->makeOrder($buyer, $seller, 'out_for_delivery');
        $delivery = $order->delivery()->firstOrFail();
        DeliveryLog::create([
            'delivery_id' => $delivery->id,
            'actor_id' => $seller->id,
            'actor_role' => 'logistics',
            'from_status' => 'picked_up',
            'to_status' => 'at_sorting_center',
            'note' => 'Parcel received at the destination hub.',
        ]);

        $this->actingAs($buyer)
            ->get(route('buyer.orders.tracking', $order))
            ->assertOk()
            ->assertSee('Delivery updates')
            ->assertSee($delivery->tracking_number)
            ->assertSee('Parcel received at the destination hub.')
            ->assertSee('Out for delivery');

        $this->actingAs($otherBuyer)
            ->get(route('buyer.orders.tracking', $order))
            ->assertNotFound();
    }

    public function test_seller_can_complete_the_approved_refund_lifecycle_and_cannot_change_another_sellers_request(): void
    {
        $buyer = $this->makeUser('buyer');
        $seller = $this->makeUser('seller');
        $otherSeller = $this->makeUser('seller');
        $order = $this->makeOrder($buyer, $seller, 'completed');
        $returnRequest = $this->makeReturnRequest($order);

        $this->actingAs($seller)
            ->get(route('seller.returns.show', $returnRequest))
            ->assertOk()
            ->assertSee('Seller decision')
            ->assertSee('Approve Return')
            ->assertSee('Item context')
            ->assertSee('Buyer submission')
            ->assertSee('Return logistics')
            ->assertSee('Return history')
            ->assertSee('Refund amount (PHP)')
            ->assertSee('Approve Return')
            ->assertSee('Reject Return');

        $this->actingAs($seller)
            ->patch(route('seller.returns.receive', $returnRequest))
            ->assertStatus(422);

        $this->actingAs($otherSeller)
            ->patch(route('seller.returns.approve', $returnRequest))
            ->assertForbidden();

        $this->actingAs($seller)
            ->patch(route('seller.returns.approve', $returnRequest), [
                'seller_note' => 'Please return the item.',
                'refund_amount' => 900,
                'carrier' => 'PickSell Logistics',
                'tracking_number' => 'RET-TRACK-123',
            ])
            ->assertRedirect();
        $this->assertSame('awaiting_item', $returnRequest->fresh()->status);
        $this->assertSame('900.00', $returnRequest->fresh()->refund_amount);
        $this->assertSame('label_generated', $returnRequest->fresh()->tracking_status);

        $this->patch(route('seller.returns.tracking', $returnRequest), [
            'tracking_status' => 'in_transit',
            'carrier' => 'PickSell Logistics',
            'tracking_number' => 'RET-TRACK-123',
        ])->assertRedirect();
        $this->assertSame('in_transit', $returnRequest->fresh()->tracking_status);

        $this->patch(route('seller.returns.receive', $returnRequest))->assertRedirect();
        $this->assertSame('received', $returnRequest->fresh()->status);
        $this->assertSame('delivered_to_seller', $returnRequest->fresh()->tracking_status);

        $this->patch(route('seller.returns.refund-due', $returnRequest))->assertStatus(422);
        $this->assertSame('received', $returnRequest->fresh()->status);

        $admin = $this->makeUser('admin');
        $this->actingAs($admin)
            ->patch(route('admin.returns.inspect', $returnRequest), ['admin_notes' => 'Item condition matches the buyer evidence.'])
            ->assertRedirect();
        $this->assertSame('inspected', $returnRequest->fresh()->status);
        $this->assertSame($admin->id, $returnRequest->fresh()->reviewed_by);

        $this->patch(route('admin.returns.approve-refund', $returnRequest), [
            'admin_notes' => 'Refund approved after inspection.',
            'refund_amount' => 900,
        ])->assertRedirect();
        $this->assertSame('approved_for_refund', $returnRequest->fresh()->status);
        $refund = $returnRequest->refunds()->sole();
        $this->assertSame('requested', $refund->status);
        $this->actingAs($admin)->patch(route('admin.refunds.approve', $refund), [
            'reason' => 'Refund amount approved for payout.',
        ])->assertRedirect();
        $this->assertSame('approved', $refund->fresh()->status);

        $this->actingAs($seller)->patch(route('seller.returns.complete', $returnRequest))->assertRedirect();
        $this->assertSame('completed', $returnRequest->fresh()->status);
        $this->assertNotNull($returnRequest->fresh()->completed_at);
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(7, $returnRequest->events()->count());
    }

    public function test_rejection_escalates_to_admin_and_admin_can_approve_or_uphold_it(): void
    {
        $buyer = $this->makeUser('buyer');
        $seller = $this->makeUser('seller');
        $admin = $this->makeUser('admin');
        $order = $this->makeOrder($buyer, $seller, 'delivered');
        $returnRequest = $this->makeReturnRequest($order);
        Storage::fake('public');
        $evidencePath = 'return-requests/buyer-evidence.png';
        Storage::disk('public')->put($evidencePath, 'test evidence');
        $returnRequest->update(['attachments' => [$evidencePath]]);

        $this->actingAs($seller)
            ->patch(route('seller.returns.reject', $returnRequest), ['seller_note' => 'The request is outside the return window.'])
            ->assertRedirect();
        $returnRequest->refresh();
        $this->assertSame('rejected', $returnRequest->status);
        $this->assertSame('open', $returnRequest->dispute_status);

        $this->get(route('seller.returns.show', $returnRequest))
            ->assertOk()
            ->assertSee('This return request was rejected. The buyer may escalate this decision to Platform Admin for final dispute resolution.');

        $this->actingAs($admin)
            ->get(route('admin.disputes', ['status' => 'open']))
            ->assertOk()
            ->assertSee($order->order_number);

        $this->get(route('admin.returns.show', $returnRequest))
            ->assertOk()
            ->assertSee('Admin resolution')
            ->assertSee('Buyer photo / video evidence')
            ->assertSee('data-return-attachment-open', false)
            ->assertSee('The request is outside the return window.');

        $this->patch(route('admin.returns.resolve', $returnRequest), [
            'decision' => 'approve_return',
            'admin_notes' => 'The return is approved after review.',
        ])->assertRedirect();
        $returnRequest->refresh();
        $this->assertSame('awaiting_item', $returnRequest->status);
        $this->assertSame('resolved', $returnRequest->dispute_status);

        $secondOrder = $this->makeOrder($buyer, $seller, 'completed');
        $secondRequest = $this->makeReturnRequest($secondOrder);
        $this->actingAs($seller)
            ->patch(route('seller.returns.reject', $secondRequest), ['seller_note' => 'Not eligible.'])
            ->assertRedirect();

        $this->actingAs($admin)
            ->patch(route('admin.returns.resolve', $secondRequest), [
                'decision' => 'uphold_rejection',
                'admin_notes' => 'The seller rejection is upheld.',
            ])->assertRedirect();

        $secondRequest->refresh();
        $this->assertSame('rejected', $secondRequest->status);
        $this->assertSame('resolved', $secondRequest->dispute_status);
    }

    public function test_buyer_cannot_request_a_return_for_another_buyers_order_or_an_undelivered_order(): void
    {
        $buyer = $this->makeUser('buyer');
        $otherBuyer = $this->makeUser('buyer');
        $seller = $this->makeUser('seller');
        $deliveredOrder = $this->makeOrder($buyer, $seller, 'delivered');
        $placedOrder = $this->makeOrder($buyer, $seller, 'placed');

        $this->actingAs($otherBuyer)
            ->post(route('buyer.orders.returns.store', $deliveredOrder), [
                'reason' => 'other',
                'details' => 'Not my order.',
            ])
            ->assertNotFound();

        $this->actingAs($buyer)
            ->post(route('buyer.orders.returns.store', $placedOrder), [
                'reason' => 'other',
                'details' => 'The item has not arrived.',
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('return_requests', 0);
    }

    public function test_seller_dashboard_and_nav_counter_fall_back_before_return_migration_is_applied(): void
    {
        $seller = $this->makeUser('seller');
        Schema::drop('return_requests');

        $this->actingAs($seller)
            ->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee('0 return requests awaiting response');

        $this->get(route('seller.returns.count'))
            ->assertOk()
            ->assertExactJson(['count' => 0]);
    }

    public function test_seller_returns_list_shows_a_clear_state_before_return_migration_is_applied(): void
    {
        $seller = $this->makeUser('seller');
        Schema::drop('return_requests');

        $this->actingAs($seller)
            ->get(route('seller.returns', ['status' => 'requested']))
            ->assertOk()
            ->assertSee('Return/refund requests are temporarily unavailable');
    }

    private function makeUser(string $role): User
    {
        $token = Str::lower(Str::random(8));

        return User::create([
            'first_name' => ucfirst($role),
            'last_name' => 'ReturnTest',
            'email' => $role.'.'.$token.'@example.com',
            'password' => bcrypt('password'),
            'role' => $role,
            'status' => 'approved',
            'sex' => 'Male',
            'contact_no' => '09'.random_int(100000000, 999999999),
            'birthday' => '1990-01-01',
            'age' => 36,
            'province' => 'Metro Manila',
            'municipality' => 'Quezon City',
            'barangay' => 'Bahay',
        ]);
    }

    private function makeOrder(User $buyer, User $seller, string $status): Order
    {
        return Order::create([
            'order_number' => 'RET-'.strtoupper(Str::random(8)),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'product_name' => 'Return Test Product',
            'quantity' => 1,
            'amount' => 1200,
            'commission' => 120,
            'status' => $status,
        ]);
    }

    private function makeReturnRequest(Order $order): ReturnRequest
    {
        return ReturnRequest::create([
            'order_id' => $order->id,
            'buyer_id' => $order->buyer_id,
            'seller_id' => $order->seller_id,
            'reason' => 'damaged',
            'details' => 'The product was damaged.',
            'status' => 'requested',
        ]);
    }
}
