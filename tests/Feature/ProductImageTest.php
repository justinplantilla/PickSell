<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seller = $this->makeSeller('seller@example.com');
    }

    private function makeSeller(string $email): User
    {
        return User::create([
            'first_name' => 'Seller',
            'last_name' => 'User',
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => 'seller',
            'status' => 'approved',
            'sex' => 'Male',
            'contact_no' => '09171234567',
            'birthday' => '1990-01-01',
            'age' => 35,
            'province' => 'Metro Manila',
            'municipality' => 'Quezon City',
            'barangay' => 'Bahay',
        ]);
    }

    private function payload(array $gallery, ?string $cover = null, array $overrides = [], array $alts = []): array
    {
        return array_merge([
            'name' => 'Canvas Tote',
            'category' => 'Fashion',
            'price' => 450,
            'discount' => 0,
            'voucher_discount' => 0,
            'stock' => 10,
            'gallery_managed' => '1',
            'gallery' => $gallery,
            'gallery_cover' => $cover,
            'gallery_alt' => $alts,
        ], $overrides);
    }

    /** Stage an upload through the async endpoint and return its gallery key. */
    private function stage(string $name = 'photo.jpg'): string
    {
        $response = $this->actingAs($this->seller)
            ->postJson('/seller/inventory/images', ['image' => UploadedFile::fake()->create($name, 20, 'image/jpeg')])
            ->assertCreated()
            ->assertJsonStructure(['token', 'url']);

        return 'upload:' . $response->json('token');
    }

    private function createProduct(int $images = 3): Product
    {
        $keys = array_map(fn ($i) => $this->stage("img{$i}.jpg"), range(1, $images));
        $this->actingAs($this->seller)->post('/seller/inventory', $this->payload($keys))->assertSessionHasNoErrors();

        return Product::latest('id')->firstOrFail();
    }

    private function keys(Product $product): array
    {
        return $product->fresh()->images->map(fn ($image) => "existing:{$image->id}")->all();
    }

    public function test_upload_endpoint_stages_file_and_rejects_non_images(): void
    {
        $key = $this->stage();
        $pending = session('seller_pending_product_images');
        $this->assertCount(1, $pending);
        $this->assertStringStartsWith('products/pending/', $pending[substr($key, 7)]);
        Storage::disk('public')->assertExists($pending[substr($key, 7)]);

        $this->actingAs($this->seller)
            ->postJson('/seller/inventory/images', ['image' => UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');

        $this->actingAs($this->seller)
            ->postJson('/seller/inventory/images', ['image' => UploadedFile::fake()->create('huge.jpg', 3000, 'image/jpeg')])
            ->assertUnprocessable()
            ->assertJsonPath('errors.image.0', 'Image must be 2MB or smaller.');
    }

    public function test_discarding_a_staged_upload_deletes_the_file(): void
    {
        $token = substr($this->stage(), 7);
        $path = session('seller_pending_product_images')[$token];

        $this->actingAs($this->seller)->deleteJson("/seller/inventory/images/{$token}")->assertOk();

        Storage::disk('public')->assertMissing($path);
        $this->assertArrayNotHasKey($token, session('seller_pending_product_images'));
    }

    public function test_create_saves_images_in_order_with_first_as_default_primary(): void
    {
        $product = $this->createProduct(3);
        $images = $product->images;

        $this->assertSame([1, 2, 3], $images->pluck('display_order')->all());
        $this->assertSame([true, false, false], $images->pluck('is_primary')->all());
        $this->assertSame(['Canvas Tote - Image 1', 'Canvas Tote - Image 2', 'Canvas Tote - Image 3'], $images->pluck('alt_text')->all());
        foreach ($images as $image) {
            $this->assertStringStartsWith('products/', $image->image_url);
            $this->assertStringNotContainsString('pending', $image->image_url);
            Storage::disk('public')->assertExists($image->image_url);
        }
        $this->assertSame([], session('seller_pending_product_images'));
    }

    public function test_make_cover_moves_primary_without_reordering(): void
    {
        $product = $this->createProduct(3);
        $keys = $this->keys($product);

        $this->actingAs($this->seller)->patch("/seller/inventory/{$product->id}", $this->payload($keys, $keys[2]))
            ->assertSessionHasNoErrors();

        $images = $product->fresh()->images;
        $this->assertSame(array_map(fn ($k) => (int) substr($k, 9), $keys), $images->pluck('id')->all());
        $this->assertSame([false, false, true], $images->pluck('is_primary')->all());
        $this->assertSame($images[2]->image_url, $product->fresh()->primary_image);
    }

    public function test_update_reorders_adds_and_deletes_in_one_save(): void
    {
        $product = $this->createProduct(3);
        [$a, $b, $c] = $this->keys($product);
        $deletedPath = $product->images->first()->image_url;
        $new = $this->stage('new.jpg');

        $this->actingAs($this->seller)->patch("/seller/inventory/{$product->id}", $this->payload([$c, $new, $b], $new))
            ->assertSessionHasNoErrors();

        $images = $product->fresh()->images;
        $this->assertCount(3, $images);
        $this->assertSame((int) substr($c, 9), $images[0]->id);
        $this->assertSame((int) substr($b, 9), $images[2]->id);
        $this->assertSame([1, 2, 3], $images->pluck('display_order')->all());
        $this->assertSame([false, true, false], $images->pluck('is_primary')->all());
        Storage::disk('public')->assertMissing($deletedPath);
    }

    public function test_deleting_the_cover_falls_back_to_first_image(): void
    {
        $product = $this->createProduct(3);
        [$a, $b, $c] = $this->keys($product);
        $this->actingAs($this->seller)->patch("/seller/inventory/{$product->id}", $this->payload([$a, $b, $c], $c));

        $this->actingAs($this->seller)->patch("/seller/inventory/{$product->id}", $this->payload([$b, $a], $c))
            ->assertSessionHasNoErrors();

        $images = $product->fresh()->images;
        $this->assertSame((int) substr($b, 9), $images->firstWhere('is_primary', true)->id);
        $this->assertSame(1, $images->where('is_primary', true)->count());
    }

    public function test_six_image_cap_is_enforced(): void
    {
        $this->assertSame(6, Product::MAX_IMAGES);
        $product = $this->createProduct(6);
        $before = $this->keys($product);

        $this->actingAs($this->seller)->patch("/seller/inventory/{$product->id}", $this->payload([...$before, $this->stage()]))
            ->assertSessionHasErrors('gallery');

        $this->assertSame($before, $this->keys($product));
    }

    public function test_foreign_or_unknown_images_are_rejected(): void
    {
        $mine = $this->createProduct(1);
        $other = $this->createProduct(1);
        $otherKey = $this->keys($other)[0];

        $this->actingAs($this->seller)->patch("/seller/inventory/{$mine->id}", $this->payload([$otherKey]))
            ->assertSessionHasErrors('gallery');
        $this->actingAs($this->seller)->patch("/seller/inventory/{$mine->id}", $this->payload(['upload:not-a-real-token']))
            ->assertSessionHasErrors('gallery');

        $this->assertCount(1, $other->fresh()->images);
        $this->assertCount(1, $mine->fresh()->images);
    }

    public function test_another_sellers_staged_upload_cannot_be_claimed(): void
    {
        $theirKey = $this->stage();
        $this->flushSession();
        $this->seller = $this->makeSeller('other@example.com');

        $this->actingAs($this->seller)->post('/seller/inventory', $this->payload([$theirKey]))
            ->assertSessionHasErrors('gallery');

        $this->assertSame(0, Product::count());
    }

    public function test_submit_without_gallery_control_leaves_images_untouched(): void
    {
        $product = $this->createProduct(2);
        $before = $this->keys($product);

        $this->actingAs($this->seller)->patch("/seller/inventory/{$product->id}", $this->payload([], null, [
            'gallery_managed' => null,
            'gallery' => null,
            'name' => 'Renamed',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $product->fresh()->name);
        $this->assertSame($before, $this->keys($product));
    }

    public function test_custom_alt_text_is_saved_and_blank_falls_back_to_name_and_position(): void
    {
        $product = $this->createProduct(3);
        [$a, $b, $c] = $this->keys($product);

        $this->actingAs($this->seller)->patch("/seller/inventory/{$product->id}", $this->payload(
            [$c, $a, $b],
            null,
            ['name' => 'Woven Tote'],
            [$a => '  Front view of the tote  ', $b => '   '],
        ))->assertSessionHasNoErrors();

        $this->assertSame(
            ['Woven Tote - Image 1', 'Front view of the tote', 'Woven Tote - Image 3'],
            $product->fresh()->images->pluck('alt_text')->all(),
        );
    }

    public function test_alt_text_longer_than_255_characters_is_rejected(): void
    {
        $product = $this->createProduct(1);
        [$a] = $this->keys($product);

        $this->actingAs($this->seller)->patch("/seller/inventory/{$product->id}", $this->payload([$a], null, [], [$a => str_repeat('x', 256)]))
            ->assertSessionHasErrors('gallery_alt.' . $a);
    }
}
