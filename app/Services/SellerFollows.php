<?php

namespace App\Services;

use App\Models\User;

/**
 * Buyers following stores (seller_follows). Only approved sellers can be followed. Used by the
 * website's store pages and Following list, and the buyer app.
 */
class SellerFollows
{
    /** Follow or unfollow; also used for the website's toggle. @return array{following: bool, followers: int} */
    public function set(User $buyer, User $seller, bool $follow): array
    {
        self::assertFollowable($seller);
        $follow
            ? $buyer->followedSellers()->syncWithoutDetaching([$seller->id])
            : $buyer->followedSellers()->detach($seller->id);

        return ['following' => $follow, 'followers' => $seller->followers()->count()];
    }

    /** @return array{following: bool, followers: int} */
    public function toggle(User $buyer, User $seller): array
    {
        self::assertFollowable($seller);

        return $this->set($buyer, $seller, ! $this->follows($buyer, $seller));
    }

    public function follows(?User $buyer, User $seller): bool
    {
        return $buyer !== null && $buyer->followedSellers()->whereKey($seller->id)->exists();
    }

    /** The stores a buyer follows, newest follow first, with how many products each has on sale. */
    public function followedBy(User $buyer, int $perPage = 20)
    {
        return $buyer->followedSellers()
            ->withCount(['products as active_products_count' => fn ($q) => $q->where('status', 'active')])
            ->orderByPivot('created_at', 'desc')->paginate($perPage);
    }

    public static function assertFollowable(User $seller): void
    {
        abort_unless($seller->role === 'seller' && $seller->status === 'approved', 404, 'Store not found.');
    }
}
