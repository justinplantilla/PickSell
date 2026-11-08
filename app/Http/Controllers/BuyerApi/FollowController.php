<?php

namespace App\Http\Controllers\BuyerApi;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SellerFollows;
use App\Support\BuyerApi\BuyerPresenter;
use Illuminate\Http\Request;

/**
 * Following stores, as on the website (SellerFollows): follow, unfollow and the Following list.
 * Follow and unfollow are separate so a retried or double tap can't flip it back.
 */
class FollowController extends Controller
{
    public function __construct(private SellerFollows $follows) {}

    /** GET /buyer/following?page= */
    public function index(Request $request)
    {
        $page = $this->follows->followedBy($request->user());

        return response()->json([
            'stores' => collect($page->items())->map(fn (User $seller) => BuyerPresenter::sellerBrief($seller) + [
                'logo'            => $seller->store_logo_url,
                'active_products' => (int) $seller->active_products_count,
                'followed_at'     => BuyerPresenter::date($seller->pivot->created_at),
            ])->values(),
            'meta'   => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    /** POST /buyer/sellers/{seller}/follow -> {following: true, followers} */
    public function store(Request $request, User $seller)
    {
        return response()->json($this->follows->set($request->user(), $seller, true));
    }

    /** DELETE /buyer/sellers/{seller}/follow -> {following: false, followers} */
    public function destroy(Request $request, User $seller)
    {
        return response()->json($this->follows->set($request->user(), $seller, false));
    }
}
