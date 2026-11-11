<?php

namespace Tests\Feature;

use App\Models\ComplianceCase;
use App\Models\Message;
use App\Models\OffPlatformFlag;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OffPlatform\OffPlatformDetector;
use App\Services\OffPlatform\OffPlatformMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Keeping sales on PickSell: contact details are masked in buyer–seller chat and flagged, listings
 * carrying them are held until an admin approves, suspicious orders and buyer reports are
 * flagged, and admins warn, open a case or suspend (never automatically).
 */
class OffPlatformMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private User $buyer;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seller = $this->user('seller', ['business_name' => 'Tote House']);
        $this->buyer = $this->user('buyer');
        $this->admin = $this->user('admin');
    }

    public function test_the_detector_finds_and_masks_contact_details_but_leaves_tracking_numbers_alone(): void
    {
        $detector = app(OffPlatformDetector::class);
        $text = 'Text me 0917 123 4567 or juan@mail.com, ship via LBC, GCash ok. Tracking PR2611119609212T, order ORD-AB12CD34.';

        $signals = collect($detector->scan($text))->pluck('signal')->unique()->sort()->values()->all();
        $this->assertSame(['courier', 'email', 'payment', 'phone', 'phrase'], $signals);

        $masked = $detector->mask($text);
        $this->assertStringNotContainsString('0917 123 4567', $masked);
        $this->assertStringContainsString('67', $masked);
        $this->assertStringNotContainsString('juan@mail.com', $masked);
        $this->assertStringContainsString('PR2611119609212T', $masked);
        $this->assertStringContainsString('ORD-AB12CD34', $masked);
        $this->assertSame([], $detector->scan('Is this available in blue? Thanks!'));
    }

    public function test_chat_contact_details_are_masked_before_saving_and_flagged(): void
    {
        $this->actingAs($this->buyer)->post($this->url('/buyer/chat/send'), ['receiver_id' => $this->seller->id, 'body' => 'Pwede text mo ako 09171234567, LBC na lang'])
            ->assertSessionHasNoErrors();

        $message = Message::where('sender_id', $this->buyer->id)->sole();
        $this->assertStringNotContainsString('09171234567', $message->body);
        $flag = OffPlatformFlag::sole();
        $this->assertSame(['chat', 'phone', $this->seller->id, $this->buyer->id, $message->id], [$flag->kind, $flag->signal, $flag->seller_id, $flag->buyer_id, $flag->message_id]);
        $this->assertStringContainsString('09171234567', $flag->evidence, 'admins see what was actually written');

        // An ordinary message is untouched and not flagged.
        $this->actingAs($this->seller)->post($this->url('/seller/chat/send'), ['receiver_id' => $this->buyer->id, 'body' => 'Yes, it ships tomorrow through PickSell.'])->assertSessionHasNoErrors();
        $this->assertSame(1, OffPlatformFlag::count());
    }

    public function test_a_listing_with_contact_details_is_held_until_an_admin_approves_it(): void
    {
        $this->actingAs($this->seller)->post($this->url('/seller/inventory'), [
            'name' => 'Canvas Tote', 'description' => 'Message me on Viber 0917-123-4567 for J&T shipping', 'price' => 500, 'stock' => 5, 'weight_kg' => 1,
        ])->assertSessionHasNoErrors();

        $product = Product::sole();
        $this->assertSame('archived', $product->status);
        $this->assertTrue($product->isUnderAdminHold());
        $flag = OffPlatformFlag::sole();
        $this->assertSame(['product', $product->id], [$flag->kind, $flag->product_id]);

        // The seller cannot relist it themselves.
        $this->actingAs($this->seller)->patch($this->url("/seller/inventory/{$product->id}/archive"))->assertSessionHasErrors('product');
        $this->assertSame('archived', $product->fresh()->status);

        $this->actingAs($this->admin)->get($this->url("/admin/off-platform/sellers/{$this->seller->id}"))->assertOk()->assertSee('Approve listing');
        $this->actingAs($this->admin)->patch($this->url("/admin/off-platform/flags/{$flag->id}/restore-listing"))->assertSessionHasNoErrors();
        $this->assertSame('active', $product->fresh()->status, 'an admin approval is not undone by the check');
        $this->assertSame('listing_restored', $flag->fresh()->action);

        // A clean listing goes live straight away.
        $this->actingAs($this->seller)->post($this->url('/seller/inventory'), ['name' => 'Linen Tote', 'description' => 'Sturdy and washable.', 'price' => 400, 'stock' => 5, 'weight_kg' => 1]);
        $this->assertSame('active', Product::where('name', 'Linen Tote')->value('status'));
    }

    public function test_an_order_cancelled_right_after_contact_details_were_shared_is_flagged_once(): void
    {
        $this->actingAs($this->buyer)->post($this->url('/buyer/chat/send'), ['receiver_id' => $this->seller->id, 'body' => 'GCash ko na lang sayo, cancel ko order dito']);
        $order = $this->order('cancelled');

        $monitor = app(OffPlatformMonitor::class);
        $this->assertSame(1, $monitor->scanOrders());
        $this->assertSame(0, $monitor->scanOrders());
        $this->assertSame('cancel_after_contact', OffPlatformFlag::where('order_id', $order->id)->sole()->signal);
    }

    public function test_buyers_report_sellers_who_ask_to_deal_outside(): void
    {
        $order = $this->order('confirmed');

        $this->actingAs($this->buyer)->post($this->url("/buyer/orders/{$order->id}/report-off-platform"), ['details' => 'Seller told me to cancel and pay by GCash instead'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->buyer)->post($this->url("/buyer/orders/{$order->id}/report-off-platform"), ['details' => 'Reporting the same thing again'])
            ->assertSessionHasErrors('off_platform');
        $this->actingAs($this->user('buyer'))->post($this->url("/buyer/orders/{$order->id}/report-off-platform"), ['details' => 'Not even my order here'])->assertNotFound();

        $this->assertSame(['report', 'buyer_report', 5], [OffPlatformFlag::sole()->kind, OffPlatformFlag::sole()->signal, OffPlatformFlag::sole()->weight]);
    }

    public function test_admins_escalate_strike_by_strike_and_nothing_happens_automatically(): void
    {
        $this->actingAs($this->buyer)->post($this->url('/buyer/chat/send'), ['receiver_id' => $this->seller->id, 'body' => 'Add me on fb.com/toteshop']);
        $this->assertSame('approved', $this->seller->fresh()->status);

        $this->actingAs($this->admin)->get($this->url('/admin/off-platform'))->assertOk()->assertSee('Outside link or social account');
        $this->actingAs($this->admin)->get($this->url('/admin/off-platform?tab=sellers'))->assertOk()->assertSee('Tote House');
        $page = $this->actingAs($this->admin)->get($this->url("/admin/off-platform/sellers/{$this->seller->id}"))->assertOk();
        $page->assertSee('First offence: warn the seller');

        $this->actingAs($this->admin)->post($this->url("/admin/off-platform/sellers/{$this->seller->id}/action"), [
            'action' => 'warn', 'message' => 'Sharing social accounts to sell outside PickSell is not allowed.',
        ])->assertSessionHasNoErrors();
        $this->assertSame(['actioned', 'warned'], [OffPlatformFlag::sole()->status, OffPlatformFlag::sole()->action]);
        $this->assertSame(1, app(OffPlatformMonitor::class)->risk($this->seller)['strikes']);
        $this->actingAs($this->admin)->get($this->url("/admin/off-platform/sellers/{$this->seller->id}"))->assertSee('Strike 1 already: open a compliance case');

        $this->travel(1)->minutes();
        $this->actingAs($this->admin)->post($this->url("/admin/off-platform/sellers/{$this->seller->id}/action"), [
            'action' => 'case', 'severity' => 'high', 'message' => 'Repeated attempts to move buyers off PickSell.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('off_platform', ComplianceCase::sole()->type);

        $this->travel(1)->minutes();
        $this->actingAs($this->admin)->post($this->url("/admin/off-platform/sellers/{$this->seller->id}/action"), [
            'action' => 'suspend', 'message' => 'Third strike: suspended for off-platform selling.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('suspended', $this->seller->fresh()->status);
    }

    private function order(string $status): Order
    {
        $product = Product::create(['seller_id' => $this->seller->id, 'name' => 'Canvas Tote', 'category' => 'Fashion', 'price' => 500, 'stock' => 10, 'status' => 'active', 'weight_kg' => 1]);

        return Order::create([
            'order_number' => 'ORD-'.strtoupper(\Illuminate\Support\Str::random(8)), 'product_id' => $product->id, 'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id, 'product_name' => $product->name, 'quantity' => 1, 'amount' => 500, 'commission' => 0, 'commission_rate' => 0, 'status' => $status,
        ]);
    }

    private function url(string $path): string
    {
        return rtrim(config('app.url'), '/').$path;
    }

    private function user(string $role, array $extra = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'role' => $role, 'status' => 'approved', 'first_name' => ucfirst($role), 'last_name' => "Watch{$n}", 'sex' => 'Female',
            'email' => "{$role}{$n}@watch.test", 'password' => bcrypt('secret-password'), 'contact_no' => '09170000000',
            'birthday' => '1995-01-01', 'age' => 30, 'province' => 'Batangas', 'municipality' => 'City of Tanauan', 'barangay' => 'Altura Bata',
            'house_no' => '12', 'street' => 'Rizal St',
        ], $extra));
    }
}
