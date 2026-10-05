<?php

namespace Tests\Feature;

use App\Auth\Permission;
use App\Models\AuditLog;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductModerationLog;
use App\Models\User;
use App\Notifications\NewSellerOrder;
use App\Notifications\ProductModeratedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Module 4 — Products: moderation history, reasons, seller notice, fulfillment guard, audit. */
class ProductModerationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $seller;
    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->admin = $this->user('admin');
        $this->seller = $this->user('seller', ['business_name' => 'Tote House', 'line_of_business' => 'Fashion']);
        $this->buyer = $this->user('buyer');
        $this->user('logistics'); // checkout needs an approved logistics provider
    }

    private function user(string $role, array $overrides = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'first_name' => ucfirst($role), 'last_name' => "Prod{$n}", 'email' => "{$role}{$n}@prod.test",
            'password' => bcrypt('password'), 'role' => $role, 'status' => 'approved', 'sex' => 'Male',
            'contact_no' => '09171234567', 'birthday' => '1990-01-01', 'age' => 35,
            'province' => 'Metro Manila', 'municipality' => 'Pasig', 'barangay' => 'San Miguel',
        ], $overrides));
    }

    private function product(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'seller_id' => $this->seller->id, 'name' => 'Canvas Tote', 'category' => 'Fashion',
            'price' => 500, 'stock' => 10, 'status' => 'active',
        ], $overrides));
    }

    private function archive(Product $product, ?string $reason = 'Counterfeit branding reported by two buyers.')
    {
        return $this->actingAs($this->admin)->patch(route('admin.products.status', $product), array_filter(['status' => 'archived', 'reason' => $reason]));
    }

    private function cartItem(Product $product, int $quantity = 1): CartItem
    {
        $cart = Cart::firstOrCreate(['buyer_id' => $this->buyer->id]);

        return CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => $quantity]);
    }

    // Traceable + reason recorded ----------------------------------------------------------

    public function test_moderation_actions_are_logged_with_reason(): void
    {
        $product = $this->product(['is_featured' => true]);

        $this->archive($product)->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->patch(route('admin.products.status', $product), ['status' => 'active', 'reason' => 'Seller proved authenticity.']);
        $this->actingAs($this->admin)->patch(route('admin.products.featured', $product), ['reason' => 'Top seller this month.']);

        $logs = ProductModerationLog::orderBy('id')->get();
        $this->assertSame(['archived', 'restored', 'featured'], $logs->pluck('action')->all());
        $this->assertSame([['active', 'archived'], ['archived', 'active'], ['active', 'active']], $logs->map(fn ($l) => [$l->from_status, $l->to_status])->all());
        $this->assertSame(['Counterfeit branding reported by two buyers.', 'Seller proved authenticity.', 'Top seller this month.'], $logs->pluck('reason')->all());
        $this->assertSame([$this->admin->id], $logs->pluck('admin_id')->unique()->values()->all());
        $this->assertTrue($product->fresh()->is_featured, 'featured again after the final action');

        $this->actingAs($this->admin)->get(route('admin.products.show', $product))->assertOk()
            ->assertSeeInOrder(['Product information', 'Seller &amp; category', 'Current status', 'Moderation history'], false)
            ->assertSeeInOrder(['Featured', 'Top seller this month.', 'Restored', 'Seller proved authenticity.', 'Archived', 'Counterfeit branding reported by two buyers.']);
    }

    public function test_archiving_requires_a_reason(): void
    {
        $product = $this->product();

        $this->archive($product, null)->assertSessionHasErrors('reason');
        $this->archive($product, 'Bad')->assertSessionHasErrors('reason');
        $this->assertSame('active', $product->fresh()->status);
        $this->assertSame(0, ProductModerationLog::count());
    }

    public function test_repeated_or_invalid_actions_are_rejected(): void
    {
        $product = $this->product();
        $this->archive($product);

        $this->archive($product)->assertStatus(409);
        $this->actingAs($this->admin)->patch(route('admin.products.featured', $product))->assertForbidden(); // archived cannot be featured
        $this->actingAs($this->admin)->patch(route('admin.products.status', $product), ['status' => 'deleted', 'reason' => 'Not a product status.'])->assertForbidden();
        $this->assertSame(1, ProductModerationLog::count());
    }

    // Seller is notified --------------------------------------------------------------------

    public function test_seller_is_notified_with_the_reason(): void
    {
        $product = $this->product();
        $this->archive($product);

        Notification::assertSentTo($this->seller, ProductModeratedNotification::class, function ($notification) use ($product) {
            $data = $notification->toDatabase($this->seller);

            return $data['product_id'] === $product->id && $data['action'] === 'archived'
                && str_contains($data['message'], 'Counterfeit branding reported by two buyers.');
        });
    }

    // Archived products cannot improperly enter new fulfillment ----------------------------

    public function test_archived_product_cannot_be_added_to_cart(): void
    {
        $product = $this->product();
        $this->archive($product);

        $this->actingAs($this->buyer)->post(route('buyer.cart.add', $product), ['quantity' => 1])->assertNotFound();
        $this->assertSame(0, CartItem::count());
    }

    public function test_product_archived_while_in_cart_cannot_be_ordered(): void
    {
        $product = $this->product();
        $item = $this->cartItem($product, 2);
        $this->archive($product);

        $this->actingAs($this->buyer)->get(route('buyer.checkout.start', ['item_ids' => [$item->id]]))
            ->assertRedirect(route('buyer.cart'))->assertSessionHasErrors('item_ids');
        $this->actingAs($this->buyer)->post(route('buyer.checkout'), ['item_ids' => [$item->id]])
            ->assertRedirect(route('buyer.cart'))->assertSessionHasErrors('item_ids');

        $this->assertSame(0, Order::count());
        $this->assertSame(10, $product->fresh()->stock);
        $this->actingAs($this->buyer)->get(route('buyer.cart'))->assertSee('No longer available');
        Notification::assertNotSentTo($this->seller, NewSellerOrder::class);
    }

    public function test_one_unavailable_item_blocks_the_whole_order_and_stock_is_respected(): void
    {
        $good = $this->product(['name' => 'Good Tote']);
        $archived = $this->product(['name' => 'Bad Tote']);
        $goodItem = $this->cartItem($good);
        $badItem = $this->cartItem($archived);
        $this->archive($archived);

        $this->actingAs($this->buyer)->post(route('buyer.checkout'), ['item_ids' => [$goodItem->id, $badItem->id]])
            ->assertSessionHasErrors('item_ids');
        $this->assertSame(0, Order::count(), 'nothing is placed when any line is unavailable');

        $low = $this->product(['name' => 'Low Tote', 'stock' => 1]);
        $lowItem = $this->cartItem($low, 3);
        $this->actingAs($this->buyer)->post(route('buyer.checkout'), ['item_ids' => [$lowItem->id]])
            ->assertSessionHasErrors('item_ids');
        $this->assertSame(1, $low->fresh()->stock);

        $this->actingAs($this->buyer)->post(route('buyer.checkout'), ['item_ids' => [$goodItem->id]])->assertRedirect(route('buyer.orders'));
        $this->assertSame(1, Order::count());
        $this->assertSame(9, $good->fresh()->stock);
    }

    public function test_products_of_suspended_sellers_cannot_be_ordered(): void
    {
        $product = $this->product();
        $item = $this->cartItem($product);
        $this->seller->update(['status' => 'suspended']);

        $this->actingAs($this->buyer)->post(route('buyer.checkout'), ['item_ids' => [$item->id]])->assertSessionHasErrors('item_ids');
        $this->assertSame(0, Order::count());
    }

    public function test_seller_cannot_restore_an_admin_archived_product_but_can_toggle_their_own(): void
    {
        $held = $this->product(['name' => 'Held']);
        $this->archive($held);

        $this->actingAs($this->seller)->patch(route('seller.inventory.archive', $held))->assertSessionHasErrors('product');
        $this->assertSame('archived', $held->fresh()->status);
        $this->actingAs($this->seller)->get(route('seller.inventory', ['status' => 'archived']))->assertSee('Archived by Admin')
            ->assertSee('Counterfeit branding reported by two buyers.');

        $own = $this->product(['name' => 'Own']);
        $this->actingAs($this->seller)->patch(route('seller.inventory.archive', $own));
        $this->assertSame('archived', $own->fresh()->status);
        $this->actingAs($this->seller)->patch(route('seller.inventory.archive', $own));
        $this->assertSame('active', $own->fresh()->status);

        // Once an Admin restores it, the seller is back in control.
        $this->actingAs($this->admin)->patch(route('admin.products.status', $held), ['status' => 'active']);
        $this->actingAs($this->seller)->patch(route('seller.inventory.archive', $held))->assertSessionHasNoErrors();
        $this->assertSame('archived', $held->fresh()->status);
        $this->assertFalse($held->fresh()->isUnderAdminHold());
    }

    // Audited --------------------------------------------------------------------------------

    public function test_action_is_audited_and_linked_to_the_log(): void
    {
        $product = $this->product();
        $this->archive($product);

        $log = AuditLog::where('action', 'product.status_changed')->sole();
        $this->assertSame($this->admin->id, $log->actor_id);
        $this->assertTrue($log->subject->is($product));
        $this->assertSame(Permission::PRODUCTS_MODERATE, $log->permission);
        $this->assertSame(['status' => ['from' => 'active', 'to' => 'archived']], $log->changes);
        $this->assertSame('Counterfeit branding reported by two buyers.', $log->metadata['reason']);
        $this->assertSame(ProductModerationLog::sole()->id, $log->metadata['moderation_log_id']);
    }

    // Queue & authorization ---------------------------------------------------------------

    public function test_queue_filters_and_view_only_access(): void
    {
        $otherSeller = $this->user('seller', ['business_name' => 'Gadget Hub', 'line_of_business' => 'Electronics']);
        $tote = $this->product(['is_featured' => true]);
        $speaker = $this->product(['seller_id' => $otherSeller->id, 'name' => 'Speaker', 'category' => 'Electronics']);
        $held = $this->product(['name' => 'Held Item']);
        $this->archive($held);

        $this->actingAs($this->admin)->get(route('admin.products', ['seller' => $otherSeller->id]))->assertSee('Speaker')->assertDontSee('Canvas Tote');
        $this->actingAs($this->admin)->get(route('admin.products', ['category' => 'Electronics']))->assertSee('Speaker')->assertDontSee('Held Item');
        $this->actingAs($this->admin)->get(route('admin.products', ['featured' => 'yes']))->assertSee('Canvas Tote')->assertDontSee('Speaker');
        $this->actingAs($this->admin)->get(route('admin.products', ['status' => 'admin_hold']))->assertSee('Held Item')->assertDontSee('Speaker');

        config(['permissions.roles.admin' => [Permission::PRODUCTS_VIEW]]);
        $this->actingAs($this->admin)->get(route('admin.products.show', $tote))->assertOk()
            ->assertSee('requires the products moderation permission')->assertDontSee('Archive product</button>', false);
        $this->archive($tote)->assertForbidden();
    }
}
