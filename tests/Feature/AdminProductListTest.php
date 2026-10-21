<?php

namespace Tests\Feature;

use App\Auth\Permission;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Products list layout: tabs, chips, attention flags, preview drawer, CSV export and selected-row bulk actions. */
class AdminProductListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->admin = $this->user('admin');
        $this->seller = $this->user('seller', ['business_name' => 'Kelly & Co.']);
    }

    public function test_tabs_split_products_by_stock_listing_and_featured_state_with_counts(): void
    {
        $healthy = $this->product('Healthy Lamp', ['stock' => 40, 'is_featured' => true]);
        $low = $this->product('Low Basket', ['stock' => 3]);
        $out = $this->product('Empty Shelf', ['stock' => 0]);
        $archived = $this->product('Old Speaker', ['status' => 'archived']);

        $see = fn (string $tab, array $yes, array $no) => tap($this->actingAs($this->admin)->get(route('admin.products', ['tab' => $tab]))->assertOk(),
            function ($response) use ($yes, $no) {
                foreach ($yes as $product) $response->assertSee($product->name);
                foreach ($no as $product) $response->assertDontSee($product->name);
            });

        $see('active', [$healthy, $low, $out], [$archived]);
        $see('low_stock', [$low], [$healthy, $out]);
        $see('out_of_stock', [$out], [$low, $healthy]);
        $see('archived', [$archived], [$healthy]);
        $see('featured', [$healthy], [$low]);

        $this->actingAs($this->admin)->get(route('admin.products'))->assertOk()
            ->assertSeeInOrder(['All', '4', 'Needs attention', 'Active', '3', 'Low stock', '1', 'Out of stock', '1', 'Archived', '1', 'Featured', '1']);
    }

    public function test_needs_attention_lists_active_products_missing_photos_stock_or_new(): void
    {
        $fine = $this->product('Seasoned Listing', ['stock' => 20]);
        ProductImage::create(['product_id' => $fine->id, 'image_url' => 'products/fine.jpg', 'is_primary' => true]);
        Product::whereKey($fine->id)->update(['created_at' => now()->subDays(30)]);
        $noPhoto = $this->product('Photo Missing', ['stock' => 20]);
        Product::whereKey($noPhoto->id)->update(['created_at' => now()->subDays(30)]);

        $this->actingAs($this->admin)->get(route('admin.products', ['tab' => 'attention']))->assertOk()
            ->assertSee('Photo Missing')->assertSee('No photo')->assertDontSee('Seasoned Listing');
    }

    public function test_table_splits_price_and_stock_and_shows_a_thumbnail_placeholder(): void
    {
        $this->product('Discounted Tote', ['price' => 1000, 'discount' => 10, 'stock' => 2]);

        $this->actingAs($this->admin)->get(route('admin.products'))->assertOk()
            ->assertSeeInOrder(['Product', 'Seller', 'Price', 'Stock', 'Status'])
            ->assertSee('<span>900.00</span>', false)
            ->assertSee('product-thumb is-empty', false)
            ->assertSee('product-stock is-low-stock', false);
    }

    public function test_search_matches_seller_names_and_chips_remove_one_filter(): void
    {
        $mine = $this->product('Canvas Tote', ['category' => 'Fashion']);
        $other = $this->product('Speaker', ['seller_id' => $this->user('seller', ['business_name' => 'Gadget Hub'])->id]);

        $this->actingAs($this->admin)->get(route('admin.products', ['search' => 'Kelly']))->assertOk()
            ->assertSee($mine->name)->assertDontSee($other->name);

        $this->actingAs($this->admin)->get(route('admin.products', ['category' => 'Fashion', 'seller' => $this->seller->id, 'tab' => 'active']))->assertOk()
            ->assertSee('Category: Fashion')
            ->assertSee('Seller: Kelly &amp; Co.', false)
            ->assertSee('class="list-count-pill">1<', false)
            ->assertSee('href="'.e(route('admin.products', ['seller' => $this->seller->id, 'tab' => 'active'])).'"', false);
    }

    public function test_preview_shows_summary_and_quick_actions(): void
    {
        $product = $this->product('Preview Lamp');

        $this->actingAs($this->admin)->get(route('admin.products.preview', $product))->assertOk()
            ->assertSee('Preview Lamp')
            ->assertSee('Archive product')
            ->assertSee('★ Feature product')
            ->assertSee('Open full product')
            ->assertDontSee('<html', false);
    }

    public function test_preview_hides_actions_without_moderate_permission(): void
    {
        config(['permissions.roles.admin' => [Permission::DASHBOARD_VIEW, Permission::PRODUCTS_VIEW]]);
        $product = $this->product('Read Only Lamp');

        $this->actingAs($this->admin)->get(route('admin.products.preview', $product))->assertOk()
            ->assertSee('Read Only Lamp')->assertDontSee('Archive product');
        $this->actingAs($this->admin)->get(route('admin.products'))->assertOk()->assertDontSee('data-select-row', false);
    }

    public function test_export_streams_selected_products_and_is_audited(): void
    {
        $a = $this->product('Exported Lamp');
        $b = $this->product('=Formula Name');

        $csv = $this->actingAs($this->admin)->get(route('admin.products.export', ['product_ids' => "{$b->id}"]))->assertOk()->streamedContent();

        $this->assertStringContainsString("'=Formula Name", $csv);
        $this->assertStringNotContainsString('Exported Lamp', $csv);
        $this->assertSame([$b->id], AuditLog::where('action', 'products.exported')->sole()->metadata['product_ids']);
    }

    public function test_bulk_archive_applies_only_to_selected_products(): void
    {
        $picked = $this->product('Picked');
        $untouched = $this->product('Untouched');

        $this->actingAs($this->admin)->post(route('admin.products.bulk-status'), [
            'product_ids' => [$picked->id], 'matching_count' => 1, 'to_status' => 'archived',
            'reason' => 'Listing photos do not match the item.', 'confirm' => '1',
        ])->assertRedirect()->assertSessionHas('success', '1 product(s) updated.');

        $this->assertSame('archived', $picked->fresh()->status);
        $this->assertSame('active', $untouched->fresh()->status);
    }

    public function test_bulk_feature_features_active_products_and_skips_the_rest(): void
    {
        $active = $this->product('Feature Me');
        $archived = $this->product('Archived One', ['status' => 'archived']);
        $already = $this->product('Already Featured', ['is_featured' => true]);

        $this->actingAs($this->admin)->post(route('admin.products.bulk-featured'), [
            'product_ids' => [$active->id, $archived->id, $already->id], 'matching_count' => 3, 'featured' => '1', 'confirm' => '1',
        ])->assertRedirect()->assertSessionHas('success', '1 product(s) updated.')->assertSessionHas('bulkSkippedCount', 2);

        $this->assertTrue((bool) $active->fresh()->is_featured);
        $this->assertFalse((bool) $archived->fresh()->is_featured);

        $this->actingAs($this->admin)->post(route('admin.products.bulk-featured'), [
            'product_ids' => [$already->id], 'matching_count' => 1, 'featured' => '0', 'confirm' => '1',
        ])->assertSessionHas('success', '1 product(s) updated.');
        $this->assertFalse((bool) $already->fresh()->is_featured);
    }

    public function test_bulk_by_filters_reads_list_filters_from_the_bulk_form_fields(): void
    {
        $featured = $this->product('Featured Tote', ['is_featured' => true]);
        $plain = $this->product('Plain Tote');

        $this->actingAs($this->admin)->post(route('admin.products.bulk-featured'), [
            'featured_filter' => 'yes', 'matching_count' => 1, 'featured' => '0', 'confirm' => '1',
        ])->assertSessionHas('success', '1 product(s) updated.');

        $this->assertFalse((bool) $featured->fresh()->is_featured);
        $this->assertFalse((bool) $plain->fresh()->is_featured);
    }

    private function user(string $role, array $overrides = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'first_name' => ucfirst($role), 'last_name' => "Plist{$n}", 'email' => "{$role}{$n}@plist.test",
            'password' => bcrypt('password'), 'role' => $role, 'status' => 'approved', 'sex' => 'Male',
            'contact_no' => '09171234567', 'birthday' => '1990-01-01', 'age' => 35,
            'province' => 'Metro Manila', 'municipality' => 'Pasig', 'barangay' => 'San Miguel',
        ], $overrides));
    }

    private function product(string $name, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'seller_id' => $this->seller->id, 'name' => $name, 'category' => 'Home & Living',
            'price' => 500, 'discount' => 0, 'stock' => 10, 'status' => 'active',
        ], $overrides));
    }
}
