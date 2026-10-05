<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Seller product gallery: images are uploaded asynchronously into a per-session staging area,
 * then the product form submits the final ordered list ("existing:{id}" / "upload:{token}")
 * plus the chosen cover, which sync() applies atomically.
 */
class ProductGallery
{
    private const SESSION_KEY = 'seller_pending_product_images';
    private const PENDING_DIR = 'products/pending';
    private const MAX_PENDING = 30;

    /** @return array{token: string, url: string} */
    public function stage(UploadedFile $file): array
    {
        $pending = session(self::SESSION_KEY, []);
        if (count($pending) >= self::MAX_PENDING) {
            throw ValidationException::withMessages(['image' => 'Too many unsaved uploads. Save or discard the product first.']);
        }

        $token = Str::random(32);
        $path  = $file->store(self::PENDING_DIR, 'public');
        $pending[$token] = $path;
        session([self::SESSION_KEY => $pending]);

        return ['token' => $token, 'url' => Storage::disk('public')->url($path)];
    }

    public function discard(string $token): void
    {
        $pending = session(self::SESSION_KEY, []);
        if (isset($pending[$token])) {
            Storage::disk('public')->delete($pending[$token]);
            unset($pending[$token]);
            session([self::SESSION_KEY => $pending]);
        }
    }

    /**
     * Make the product's images exactly match $entries (in order). The cover entry becomes the
     * single primary image; without a valid cover, the image at display_order = 1 is primary.
     * Images without custom alt text get "[Product Name] - Image [N]".
     *
     * @param  string[]  $entries
     * @param  array<string, ?string>  $alts  custom alt text keyed by gallery entry
     */
    public function sync(Product $product, array $entries, ?string $cover, array $alts = []): void
    {
        $entries = array_values(array_unique($entries));
        if (count($entries) > Product::MAX_IMAGES) {
            throw ValidationException::withMessages(['gallery' => 'A product can have at most ' . Product::MAX_IMAGES . ' images.']);
        }

        $pending  = session(self::SESSION_KEY, []);
        $existing = $product->images()->get()->keyBy('id');
        $plan     = [];
        foreach ($entries as $entry) {
            [$type, $ref] = array_pad(explode(':', (string) $entry, 2), 2, '');
            $valid = match ($type) {
                'existing' => $existing->has((int) $ref),
                'upload'   => isset($pending[$ref]),
                default    => false,
            };
            if (! $valid) {
                throw ValidationException::withMessages(['gallery' => 'One of the images is no longer available. Please re-upload it.']);
            }
            $plan[] = [$type, $ref, $entry];
        }
        if (! in_array($cover, $entries, true)) {
            $cover = $entries[0] ?? null;
        }

        // Move staged files into place before touching the database.
        $moved = [];
        foreach ($plan as [$type, $ref]) {
            if ($type === 'upload') {
                $final = 'products/' . basename($pending[$ref]);
                Storage::disk('public')->move($pending[$ref], $final);
                $moved[$ref] = $final;
            }
        }

        $keepIds = collect($plan)->where(0, 'existing')->map(fn ($step) => (int) $step[1]);
        $removed = $existing->except($keepIds->all());

        DB::transaction(function () use ($product, $plan, $moved, $cover, $removed, $alts) {
            $product->images()->whereIn('id', $removed->modelKeys())->delete();

            foreach ($plan as $index => [$type, $ref, $entry]) {
                $attributes = [
                    'display_order' => $index + 1,
                    'is_primary'    => $entry === $cover,
                    'alt_text'      => trim((string) ($alts[$entry] ?? '')) ?: self::fallbackAlt($product, $index + 1),
                ];
                if ($type === 'existing') {
                    $product->images()->whereKey((int) $ref)->update($attributes);
                } else {
                    $product->images()->create($attributes + ['image_url' => $moved[$ref]]);
                }
            }
        });

        Storage::disk('public')->delete($removed->pluck('image_url')->all());
        session([self::SESSION_KEY => array_diff_key($pending, $moved)]);
        $product->unsetRelation('images');
    }

    public static function fallbackAlt(Product $product, int $position): string
    {
        return "{$product->name} - Image {$position}";
    }
}
