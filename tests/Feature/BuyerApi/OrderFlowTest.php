<?php

namespace Tests\Feature\BuyerApi;

use App\Models\Complaint;
use App\Models\FinancialTransaction;
use App\Models\OrderStatusHistory;
use App\Models\ProductReview;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestEvent;
use App\Pickrush\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    public function test_website_order_steps_land_in_the_right_app_tabs(): void
    {
        $lamp = $this->product($this->seller());
        $buyer = $this->actingAsBuyer();
        foreach (['placed', 'confirmed', 'ready_for_pickup', 'sorted', 'out_for_delivery', 'delivered', 'cancelled', 'returned', 'pending'] as $status) {
            $this->order($buyer, $lamp, $status);
        }
        $this->order($buyer, $lamp, 'completed', ['rating' => 5]);
        $this->order($buyer, $lamp, 'completed');

        // Delivered waits for "Order Received" with out-for-delivery (the app's To Receive); only completed is rated.
        $this->getJson('/api/mobile/buyer/orders')->assertOk()->assertJsonPath('counts', [
            'to_ship' => 4, 'in_transit' => 1, 'out_for_delivery' => 2, 'to_rate' => 1, 'completed' => 2, 'cancelled' => 1,
        ]);
        $this->assertCount(1, $this->getJson('/api/mobile/buyer/orders?tab=to_rate')->json('orders'));
        // Legacy "pending" shows as the step it stands for.
        $this->assertContains('placed', collect($this->getJson('/api/mobile/buyer/orders?tab=to_ship')->json('orders'))->pluck('status'));
    }

    public function test_buyers_cancel_with_the_website_rule_and_stock_returns(): void
    {
        $lamp = $this->product($this->seller(), ['stock' => 3]);
        $buyer = $this->actingAsBuyer();

        foreach (['placed', 'confirmed', 'preparing'] as $status) {
            $order = $this->order($buyer, $lamp, $status);
            $this->getJson("/api/mobile/buyer/orders/{$order->id}")->assertJsonPath('order.actions.can_cancel', true);
            $this->postJson("/api/mobile/buyer/orders/{$order->id}/cancel", ['reason' => 'Ordered by mistake'])
                ->assertOk()->assertJsonPath('order.status', 'cancelled')->assertJsonPath('order.cancel_reason', 'Ordered by mistake');
        }
        // Each cancel puts the 2 units back, like the website's lifecycle does.
        $this->assertSame(9, $lamp->fresh()->stock);
        $history = OrderStatusHistory::where('to_status', 'cancelled')->latest('id')->first();
        $this->assertSame(['buyer', $buyer->id, 'Ordered by mistake'], [$history->source, $history->changed_by, $history->reason]);

        $handedOver = $this->order($buyer, $lamp, 'ready_for_pickup');
        $this->getJson("/api/mobile/buyer/orders/{$handedOver->id}")->assertJsonPath('order.actions.can_cancel', false);
        $this->postJson("/api/mobile/buyer/orders/{$handedOver->id}/cancel")->assertStatus(409);
        $this->assertSame('ready_for_pickup', $handedOver->fresh()->status);
    }

    public function test_the_buyer_confirms_receipt_then_rates_and_the_review_is_published(): void
    {
        $lamp = $this->product($this->seller());
        $buyer = $this->actingAsBuyer();
        $shipping = $this->order($buyer, $lamp, 'out_for_delivery');
        $delivered = $this->order($buyer, $lamp, 'delivered');
        $full = ['rating' => 5, 'seller_rating' => 5, 'delivery_rating' => 4, 'feedback' => 'Bright and sturdy'];

        // Not before Pickrush delivers it, and not before the buyer confirms receipt.
        $this->postJson("/api/mobile/buyer/orders/{$shipping->id}/received")->assertUnprocessable();
        $this->postJson("/api/mobile/buyer/orders/{$shipping->id}/rate", $full)->assertUnprocessable();
        $this->getJson("/api/mobile/buyer/orders/{$delivered->id}")->assertOk()
            ->assertJsonPath('order.tab', 'out_for_delivery')
            ->assertJsonPath('order.actions.can_confirm_received', true)
            ->assertJsonPath('order.actions.can_rate', false);
        $this->postJson("/api/mobile/buyer/orders/{$delivered->id}/rate", $full)->assertUnprocessable()
            ->assertJsonPath('message', 'Tap Order Received first, then rate your order.');

        // "Order Received" completes it (the seller's earnings are posted) and opens it for rating.
        $this->postJson("/api/mobile/buyer/orders/{$delivered->id}/received")->assertOk()
            ->assertJsonPath('order.status', 'completed')->assertJsonPath('order.tab', 'to_rate')
            ->assertJsonPath('order.actions.can_confirm_received', false)->assertJsonPath('order.actions.can_rate', true);
        $this->postJson("/api/mobile/buyer/orders/{$delivered->id}/received")->assertOk(); // a second tap changes nothing
        $this->assertSame(3, FinancialTransaction::where('reference_type', 'order')->where('reference_id', $delivered->id)->count());
        $history = OrderStatusHistory::where('order_id', $delivered->id)->where('to_status', 'completed')->sole();
        $this->assertSame(['buyer', $buyer->id], [$history->source, $history->changed_by]);

        // Like the website's rating form, the seller's service and the delivery are rated too.
        $this->postJson("/api/mobile/buyer/orders/{$delivered->id}/rate", ['rating' => 4, 'feedback' => 'Nice'])->assertUnprocessable()
            ->assertJsonPath('errors.seller_rating.0', 'Rate the seller’s service.')->assertJsonPath('errors.delivery_rating.0', 'Rate the delivery.');
        $this->postJson("/api/mobile/buyer/orders/{$delivered->id}/rate", $full)->assertOk()->assertJsonPath('order.tab', 'completed');
        $this->postJson("/api/mobile/buyer/orders/{$delivered->id}/rate", $full)->assertUnprocessable();

        // Published straight away.
        $review = ProductReview::sole();
        $this->assertSame([$lamp->id, $delivered->id, 5, 'Bright and sturdy'], [$review->product_id, $review->order_id, $review->rating, $review->body]);
        $this->getJson("/api/mobile/products/{$lamp->id}/reviews")->assertJsonPath('reviews.0.comment', 'Bright and sturdy');
    }

    public function test_an_older_rating_left_before_confirming_is_published_on_order_received(): void
    {
        $lamp = $this->product($this->seller());
        $buyer = $this->actingAsBuyer();
        $order = $this->order($buyer, $lamp, 'delivered', ['rating' => 4, 'seller_rating' => 5, 'delivery_rating' => 5, 'feedback' => 'Rated early']);

        $this->postJson("/api/mobile/buyer/orders/{$order->id}/received")->assertOk()->assertJsonPath('order.tab', 'completed');

        $this->assertSame([4, 'Rated early'], [ProductReview::sole()->rating, ProductReview::sole()->body]);
        $this->postJson("/api/mobile/buyer/orders/{$this->order($this->buyer(), $lamp, 'delivered')->id}/received")->assertNotFound();
    }

    public function test_return_requests_follow_the_website_workflow(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $lamp = $this->product($this->seller());
        $buyer = $this->actingAsBuyer();
        $order = $this->order($buyer, $lamp, 'delivered', ['quantity' => 2, 'amount' => 2000]);

        $this->post("/api/mobile/buyer/orders/{$order->id}/return", [
            'reason' => 'Damaged item', 'details' => 'The shade arrived cracked.', 'quantity' => 1,
            'evidence' => [UploadedFile::fake()->image('crack.jpg')],
        ], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('return_request.status', 'requested')->assertJsonPath('return_request.refund_amount', 1000);

        $return = ReturnRequest::sole();
        $this->assertSame(['damaged', 'requested', 1, '1000.00'], [$return->reason, $return->status, $return->quantity, (string) $return->refund_amount]);
        Storage::disk('public')->assertExists($return->attachments[0]);
        $this->assertStringStartsWith('return-requests/', $return->attachments[0]);
        $this->assertSame('request_submitted', ReturnRequestEvent::sole()->event_type);
        $this->assertSame('/admin/returns/'.$return->id, $admin->notifications()->sole()->data['url']);

        $this->postJson("/api/mobile/buyer/orders/{$order->id}/return", ['reason' => 'other', 'details' => 'Again'])->assertStatus(409);
        $notDelivered = $this->order($buyer, $lamp, 'out_for_delivery');
        $this->postJson("/api/mobile/buyer/orders/{$notDelivered->id}/return", ['reason' => 'other', 'details' => 'Early'])->assertUnprocessable();

        // Website decisions show up in the app.
        $return->forceFill(['status' => 'rejected', 'dispute_status' => 'open'])->save();
        $this->getJson("/api/mobile/buyer/orders/{$order->id}")
            ->assertJsonPath('order.return_request.status', 'rejected')
            ->assertJsonPath('order.return_request.stage_label', 'The seller declined; PickSell support is reviewing it');
    }

    public function test_reports_become_website_complaints(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $seller = $this->seller();
        $buyer = $this->actingAsBuyer();
        $order = $this->order($buyer, $this->product($seller), 'delivered');

        $this->post("/api/mobile/buyer/orders/{$order->id}/report", [
            'category' => 'Missing Item', 'details' => 'Only one lamp was in the box.',
            'evidence' => [UploadedFile::fake()->image('box.jpg')],
        ], ['Accept' => 'application/json'])->assertCreated();

        $complaint = Complaint::sole();
        $this->assertSame([$buyer->id, $seller->id, $order->id, 'open'], [$complaint->filed_by, $complaint->against_user_id, $complaint->order_id, $complaint->status]);
        Storage::disk('public')->assertExists($complaint->evidence_path);
        $this->assertSame('/admin/complaints/'.$complaint->id, $admin->notifications()->sole()->data['url']);
        $this->getJson('/api/mobile/buyer/support/tickets')->assertJsonPath('tickets.0.status', 'open');
    }

    public function test_pickrush_scans_and_website_notifications_reach_the_app(): void
    {
        $lamp = $this->product($this->seller());
        $buyer = $this->actingAsBuyer();
        $order = $this->order($buyer, $lamp, 'sorted');
        $parcel = DB::table('pr_parcels')->insertGetId([
            'company_id' => Company::defaultId(), 'tracking_no' => 'PR-123456', 'source' => 'picksell', 'external_ref' => (string) $order->id, 'current_status' => 'arrived_at_station',
            'shipper_name' => 'Lamp Shop', 'consignee_name' => $buyer->full_name, 'cod_amount' => 2000, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $hub = DB::table('pr_hubs')->insertGetId(['company_id' => Company::defaultId(), 'code' => 'CEB-1', 'name' => 'Cebu City Station', 'type' => 'station', 'psgc_code' => '072217000', 'region_code' => '070000000', 'province_name' => 'Cebu']);
        DB::table('pr_parcel_events')->insert([
            ['parcel_id' => $parcel, 'status' => 'picked_up', 'hub_id' => null, 'actor_type' => 'rider', 'occurred_at' => now()->subHours(5)],
            ['parcel_id' => $parcel, 'status' => 'arrived_at_station', 'hub_id' => $hub, 'actor_type' => 'staff', 'occurred_at' => now()],
        ]);
        // What the website's Pickrush bridge writes to the buyer.
        $buyer->notifications()->create(['id' => (string) \Illuminate\Support\Str::uuid(), 'type' => 'App\\Notifications\\BuyerOrderUpdate',
            'data' => ['order_id' => $order->id, 'order_number' => $order->order_number, 'message' => 'Your parcel has arrived at the station in your city.', 'status' => 'sorted']]);

        $this->getJson("/api/mobile/buyer/orders/{$order->id}")->assertOk()
            ->assertJsonPath('order.waybill_number', 'PR-123456')
            ->assertJsonPath('order.tracking.events.0.headline', 'Arrived at Cebu City Station')
            ->assertJsonPath('order.tracking.events.1.headline', 'Picked up from the seller');
        $this->getJson('/api/mobile/buyer/notifications')->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('notifications.0.title', 'Arrived at your local station')
            ->assertJsonPath('notifications.0.order_id', $order->id);
        $this->postJson('/api/mobile/buyer/notifications/read-all')->assertOk();
        $this->assertSame(0, $buyer->unreadNotifications()->count()); // read on the website too
    }

    public function test_buyers_cannot_reach_other_buyers_orders(): void
    {
        $lamp = $this->product($this->seller());
        $order = $this->order($this->buyer(), $lamp, 'delivered');
        $this->actingAsBuyer();

        $this->getJson("/api/mobile/buyer/orders/{$order->id}")->assertNotFound();
        $this->postJson("/api/mobile/buyer/orders/{$order->id}/cancel")->assertNotFound();
        $this->postJson("/api/mobile/buyer/orders/{$order->id}/rate", ['rating' => 1])->assertNotFound();
        $this->postJson("/api/mobile/buyer/orders/{$order->id}/return", ['reason' => 'other', 'details' => 'x'])->assertNotFound();
        $this->assertNull($order->fresh()->rating);
    }
}
