<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

class SellerBulkActionsTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    public function test_sellers_start_preparing_several_orders_at_once(): void
    {
        $seller = $this->seller();
        $buyer = $this->buyer();
        $lamp = $this->product($seller);
        $a = $this->order($buyer, $lamp, 'placed');
        $b = $this->order($buyer, $lamp, 'placed');
        $done = $this->order($buyer, $lamp, 'delivered');
        $notMine = $this->order($buyer, $this->product($this->seller()), 'placed');

        $this->actingAs($seller)->post(route('seller.orders.bulk'), ['action' => 'prepare', 'order_ids' => [$a->id, $b->id, $done->id, $notMine->id]])
            ->assertSessionHas('success', '2 orders are now being prepared. 1 skipped: not at that step yet or already done.');

        $this->assertSame(['preparing', 'preparing', 'delivered', 'placed'], [$a->fresh()->status, $b->fresh()->status, $done->fresh()->status, $notMine->fresh()->status]);
        $this->actingAs($seller)->post(route('seller.orders.bulk'), ['action' => 'print', 'order_ids' => [$a->id]])
            ->assertSessionHasErrors('order_ids');
        $this->actingAs($seller)->post(route('seller.orders.bulk'), ['action' => 'prepare'])->assertSessionHasErrors('order_ids');
        $this->actingAs($seller)->get(route('seller.orders'))->assertOk()->assertSee('data-bulk-order', false)->assertSee('Print waybills');
    }

    public function test_duplicating_a_product_copies_its_details_options_and_photos(): void
    {
        Storage::fake('public');
        $seller = $this->seller();
        $shirt = $this->product($seller, ['name' => 'Shirt', 'price' => 300, 'stock' => 9]);
        ProductVariation::create(['product_id' => $shirt->id, 'type' => 'Size', 'value' => 'M', 'price' => 350, 'stock' => 9]);
        Storage::disk('public')->put('products/shirt.jpg', UploadedFile::fake()->image('shirt.jpg')->getContent());
        ProductImage::where('product_id', $shirt->id)->update(['image_url' => 'products/shirt.jpg']);

        $this->actingAs($seller)->post(route('seller.inventory.duplicate', $shirt))->assertRedirect(route('seller.inventory', ['status' => 'archived']));

        $copy = Product::where('name', 'Shirt (copy)')->sole();
        $this->assertSame(['archived', 0, '300.00'], [$copy->status, $copy->stock, number_format((float) $copy->price, 2, '.', '')]);
        $this->assertSame([['Size', 'M', '350.00', 0]], $copy->variations->map(fn ($v) => [$v->type, $v->value, $v->price, $v->stock])->all());
        $this->assertCount(1, $copy->images);
        $this->assertNotSame('products/shirt.jpg', $copy->images->first()->image_url, 'the copy has its own file');
        Storage::disk('public')->assertExists($copy->images->first()->image_url);

        $this->actingAs($this->seller())->post(route('seller.inventory.duplicate', $shirt))->assertForbidden();
    }
}
