<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** An option can point at one of its product's gallery images, which buyers see when they pick it. */
class VariationImageTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    private User $shop;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->shop = $this->seller();
    }

    private function stage(string $name): string
    {
        $token = $this->actingAs($this->shop)
            ->postJson('/seller/inventory/images', ['image' => UploadedFile::fake()->create($name, 20, 'image/jpeg')])
            ->assertCreated()->json('token');

        return "upload:{$token}";
    }

    private function form(array $gallery, array $variations): array
    {
        return [
            'name' => 'Cotton Shirt', 'category' => 'Fashion', 'price' => 300, 'discount' => 0,
            'voucher_discount' => 0, 'stock' => 1, 'weight_kg' => 0.3,
            'gallery_managed' => 1, 'gallery' => $gallery,
            'variations_managed' => 1, 'variation_type' => 'Color', 'variations' => $variations,
        ];
    }

    /** A product with Red/Blue photos and Red/Blue options pointing at them. */
    private function shirt(): Product
    {
        $red = $this->stage('red.jpg');
        $blue = $this->stage('blue.jpg');
        $this->actingAs($this->shop)->post(route('seller.inventory.store'), $this->form([$red, $blue], [
            ['value' => 'Red', 'stock' => 2, 'image' => $red],
            ['value' => 'Blue', 'stock' => 3, 'image' => $blue],
            ['value' => 'Green', 'stock' => 1],
        ]))->assertSessionHasNoErrors();

        return Product::sole();
    }

    public function test_adding_a_product_links_options_to_their_newly_uploaded_images(): void
    {
        $product = $this->shirt();
        [$redImage, $blueImage] = $product->images;

        $this->assertSame([$redImage->id, $blueImage->id, null], $product->variations->pluck('image_id')->all());
    }

    public function test_editing_changes_an_options_image_and_removing_the_image_only_clears_the_link(): void
    {
        $product = $this->shirt();
        [$redImage, $blueImage] = $product->images;
        [$red, $blue, $green] = $product->variations;

        // Blue's photo is deleted from the gallery; Green now shows the red photo.
        $this->actingAs($this->shop)->patch(route('seller.inventory.update', $product), $this->form(["existing:{$redImage->id}"], [
            ['id' => $red->id, 'value' => 'Red', 'stock' => 2, 'image' => "existing:{$redImage->id}"],
            ['id' => $blue->id, 'value' => 'Blue', 'stock' => 3, 'image' => "existing:{$blueImage->id}"],
            ['id' => $green->id, 'value' => 'Green', 'stock' => 1, 'image' => "existing:{$redImage->id}"],
        ]))->assertSessionHasNoErrors();

        $this->assertModelMissing($blueImage);
        $this->assertSame([$redImage->id, null, $redImage->id], $product->variations()->pluck('image_id')->all());
    }

    public function test_an_option_cannot_point_at_another_products_image(): void
    {
        $product = $this->shirt();
        $other = $this->product($this->shop);
        $foreign = $other->images()->create(['image_url' => 'products/other.jpg', 'display_order' => 1, 'is_primary' => true]);
        $red = $product->variations->first();

        $this->actingAs($this->shop)->patch(route('seller.inventory.update', $product), [
            'name' => 'Cotton Shirt', 'category' => 'Fashion', 'price' => 300, 'discount' => 0, 'voucher_discount' => 0,
            'stock' => 1, 'weight_kg' => 0.3, 'variations_managed' => 1, 'variation_type' => 'Color',
            'variations' => [['id' => $red->id, 'value' => 'Red', 'stock' => 2, 'image' => "existing:{$foreign->id}"]],
        ])->assertSessionHasNoErrors();

        $this->assertNull($red->fresh()->image_id);
    }

    public function test_a_duplicate_points_its_options_at_the_copied_images(): void
    {
        $product = $this->shirt();
        foreach ($product->images as $image) {
            Storage::disk('public')->put($image->image_url, 'photo');
        }

        $this->actingAs($this->shop)->post(route('seller.inventory.duplicate', $product));

        $copy = Product::whereKeyNot($product->id)->sole();
        $this->assertSame([...$copy->images->pluck('id')->all(), null], $copy->variations->pluck('image_id')->all());
    }

    public function test_buyers_see_the_options_image_on_the_product_page_and_in_the_cart(): void
    {
        $product = $this->shirt();
        $blueImage = $product->images[1];
        $blue = ProductVariation::where('value', 'Blue')->sole();
        $buyer = $this->buyer();

        $this->actingAs($buyer)->get(route('buyer.product', $product))->assertOk()
            ->assertSee('data-image="'.$blueImage->url.'"', false);

        $cart = Cart::create(['buyer_id' => $buyer->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'variation_id' => $blue->id, 'quantity' => 1]);
        $this->get(route('buyer.cart'))->assertOk()->assertSee(Storage::url($blueImage->image_url), false);
        $this->assertSame($blueImage->image_url, $product->imageFor($blue));
        $this->assertSame($product->primary_image, $product->imageFor(ProductVariation::where('value', 'Green')->sole()));
    }
}
