<?php

namespace Tests\Feature;

use App\Models\WishlistItem;
use App\Notifications\WishlistProductUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

class WishlistAlertsTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    public function test_wishlisters_hear_about_price_drops_and_restocks(): void
    {
        Notification::fake();
        $seller = $this->seller();
        $fan = $this->buyer();
        $other = $this->buyer();
        $lamp = $this->product($seller, ['price' => 1000, 'stock' => 0]);
        WishlistItem::create(['user_id' => $fan->id, 'product_id' => $lamp->id]);

        $lamp->update(['price' => 1200]);
        Notification::assertNothingSent();

        $lamp->update(['discount' => 25]);   // ₱1,200 → ₱900
        Notification::assertSentTo($fan, WishlistProductUpdate::class, fn ($n) => $n->kind === WishlistProductUpdate::PRICE_DROP
            && str_contains($n->toDatabase($fan)['message'], 'now ₱900.00 (was ₱1,200.00)'));

        $lamp->update(['stock' => 4]);
        Notification::assertSentTo($fan, WishlistProductUpdate::class, fn ($n) => $n->kind === WishlistProductUpdate::BACK_IN_STOCK);
        Notification::assertNotSentTo($other, WishlistProductUpdate::class);

        // Archived products stay quiet.
        $lamp->update(['status' => 'archived', 'stock' => 0]);
        $lamp->update(['stock' => 3]);
        Notification::assertSentToTimes($fan, WishlistProductUpdate::class, 2);
    }
}
