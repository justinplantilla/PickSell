<?php

namespace Tests\Feature;

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

    private const GCASH = ['gcash_number' => '09171234567', 'gcash_name' => 'Buyer ReturnTest'];

    public function test_buyer_can_request_a_return_for_a_delivered_order_without_changing_shipping_status(): void
    {
        $buyer = $this->makeUser('buyer');
        $seller = $this->makeUser('seller');
        $order = $this->makeOrder($buyer, $seller, 'delivered');
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
            ] + self::GCASH)
            ->assertRedirect(route('buyer.orders'));

        $returnRequest = ReturnRequest::where('order_id', $order->id)->firstOrFail();
        $this->assertSame('requested', $returnRequest->status);
        $this->assertSame(1, $returnRequest->quantity);
        $this->assertSame('1200.00', $returnRequest->refund_amount);
        $this->assertCount(1, $returnRequest->attachments);
        Storage::disk('public')->assertExists($returnRequest->attachments[0]);
        $this->assertSame('09171234567', $returnRequest->refund_gcash_number);
        $this->assertSame('delivered', $order->fresh()->status);

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
            ] + self::GCASH)
            ->assertStatus(409);
    }

    public function test_the_buyer_can_ask_for_a_return_only_within_the_return_window(): void
    {
        config(['pickrush.policies.return_window_days' => 5]);
        $buyer = $this->makeUser('buyer');
        $seller = $this->makeUser('seller');
        $order = $this->makeOrder($buyer, $seller, 'delivered');
        $order->forceFill(['delivered_at' => now()->subDays(4)])->save();
        $return = fn () => $this->actingAs($buyer)->post(route('buyer.orders.returns.store', $order), [
            'reason' => 'damaged', 'details' => 'The item arrived damaged.',
        ] + self::GCASH);

        $this->assertTrue($order->fresh()->returnWindowOpen());
        $this->actingAs($buyer)->get(route('buyer.orders'))->assertSee('Request return or refund');

        // Five days after delivery the window has closed: no button, and the request is refused.
        $this->travel(2)->days();
        $this->assertFalse($order->fresh()->returnWindowOpen());
        $this->actingAs($buyer)->get(route('buyer.orders'))->assertDontSee('Request return or refund');
        $return()->assertStatus(422);
        $this->assertFalse(ReturnRequest::where('order_id', $order->id)->exists());
    }

    public function test_buyer_tracking_page_belongs_to_the_buyer_who_placed_the_order(): void
    {
        $buyer = $this->makeUser('buyer');
        $seller = $this->makeUser('seller');
        $otherBuyer = $this->makeUser('buyer');
        $order = $this->makeOrder($buyer, $seller, 'preparing');

        $this->actingAs($buyer)
            ->get(route('buyer.orders.tracking', $order))
            ->assertOk()
            ->assertSee('Delivery updates')
            ->assertSee('No delivery scans or status updates have been recorded yet.');

        $this->actingAs($otherBuyer)
            ->get(route('buyer.orders.tracking', $order))
            ->assertNotFound();
    }

    public function test_the_refund_lifecycle_runs_from_seller_approval_to_the_gcash_transfer_and_other_sellers_cannot_act(): void
    {
        $buyer = $this->makeUser('buyer');
        $seller = $this->makeUser('seller');
        $otherSeller = $this->makeUser('seller');
        $order = $this->makeOrder($buyer, $seller, 'delivered');
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
            ->assertSee('Approving books the Pickrush pickup from the buyer.')
            ->assertSee('Approve Return')
            ->assertSee('Reject Return');

        $this->actingAs($seller)
            ->patch(route('seller.returns.receive', $returnRequest))
            ->assertStatus(422);

        $this->actingAs($otherSeller)
            ->patch(route('seller.returns.approve', $returnRequest))
            ->assertForbidden();

        $this->actingAs($seller)
            ->patch(route('seller.returns.approve', $returnRequest), ['seller_note' => 'Please return the item.'])
            ->assertRedirect();
        // The Pickrush return waybill is booked (no stations here, so it waits for an address fix at HQ).
        $this->assertSame('awaiting_item', $returnRequest->fresh()->status);
        $this->assertSame('1200.00', $returnRequest->fresh()->refund_amount, 'the item price, not chosen by the seller');
        $this->assertSame('label_generated', $returnRequest->fresh()->tracking_status);
        $this->assertTrue(\App\Pickrush\Bridge\PicksellBridge::returnParcel($returnRequest)->needs_address_fix);

        // Pickrush, not the seller, marks it received (Pickrush\BuyerReturnsTest drives the hand-over).
        $this->patch(route('seller.returns.receive', $returnRequest))->assertStatus(422);
        $returnRequest->update(['status' => 'received', 'received_at' => now(), 'tracking_status' => 'delivered_to_seller']);
        $this->patch(route('seller.returns.inspect', $returnRequest), ['result' => 'passed', 'inspection_note' => 'Damage matches the photos.'])
            ->assertRedirect();
        $this->assertSame(['approved_for_refund', 'passed'], [$returnRequest->fresh()->status, $returnRequest->fresh()->inspection_result]);
        $this->assertSame('delivered', $order->fresh()->status, 'the payment is still held');

        $admin = $this->makeUser('admin');
        $refund = $returnRequest->refunds()->sole();
        $this->assertSame('requested', $refund->status);
        $this->actingAs($admin)->patch(route('admin.refunds.sent', $refund), ['payout_reference' => 'GC-1'])->assertStatus(422);
        $this->actingAs($admin)->patch(route('admin.refunds.approve', $refund), [
            'reason' => 'Refund amount approved for payout.',
        ])->assertRedirect();
        $this->assertSame('approved', $refund->fresh()->status);
        $this->assertSame('completed', $order->fresh()->status, 'approving completes the order before the refund ledger');

        $this->actingAs($admin)->patch(route('admin.refunds.sent', $refund), ['payout_reference' => 'GC-0012345'])->assertRedirect();
        $this->assertSame('completed', $returnRequest->fresh()->status);
        $this->assertNotNull($returnRequest->fresh()->completed_at);
        $this->assertSame('GC-0012345', $refund->fresh()->payout_reference);
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
            ->assertSee('You rejected this return request. PickSell is reviewing it and will decide.');

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

        $secondOrder = $this->makeOrder($buyer, $seller, 'delivered');
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
        $this->assertSame('completed', $secondOrder->fresh()->status, 'the upheld rejection releases the payment');
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
                'reason' => 'damaged',
                'details' => 'Not my order.',
            ] + self::GCASH)
            ->assertNotFound();

        $this->actingAs($buyer)
            ->post(route('buyer.orders.returns.store', $placedOrder), [
                'reason' => 'damaged',
                'details' => 'The item has not arrived.',
            ] + self::GCASH)
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
            'quantity' => $order->quantity,
            'refund_amount' => $order->amount,
            'refund_gcash_number' => '09171234567',
            'refund_gcash_name' => 'Buyer ReturnTest',
            'status' => 'requested',
        ]);
    }
}
