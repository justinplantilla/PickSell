<?php

namespace App\Http\Controllers\BuyerApi;

use App\Http\Controllers\Controller;
use App\Models\BuyerAddress;
use App\Services\BuyerAddressBook;
use App\Support\BuyerApi\BuyerPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The buyer's address book, as on the website's account page (BuyerAddressBook): up to 10 extra
 * delivery addresses to choose at checkout; the profile address is the default. Every action
 * answers with the whole book so the app can redraw it.
 */
class AddressController extends Controller
{
    public function __construct(private BuyerAddressBook $book) {}

    /** GET /buyer/addresses */
    public function index(Request $request): JsonResponse
    {
        return $this->book($request);
    }

    /** POST /buyer/addresses {label?, recipient_name, contact_no, province, municipality, barangay, street?, house_no?, lat?, lng?} */
    public function store(Request $request): JsonResponse
    {
        $this->book->add($request->user(), $request);

        return $this->book($request, 201);
    }

    /** PATCH /buyer/addresses/{id} (same body as store) */
    public function update(Request $request, int $address): JsonResponse
    {
        $this->book->update($this->own($request, $address), $request);

        return $this->book($request);
    }

    /** DELETE /buyer/addresses/{id} */
    public function destroy(Request $request, int $address): JsonResponse
    {
        $this->book->remove($this->own($request, $address));

        return $this->book($request);
    }

    /** POST /buyer/addresses/{id}/default — the entry becomes the profile address; the old default stays in the book. */
    public function makeDefault(Request $request, int $address): JsonResponse
    {
        $this->book->makeDefault($request->user(), $this->own($request, $address));

        return $this->book($request);
    }

    /** Another buyer's address is a 404, as on the website. */
    private function own(Request $request, int $id): BuyerAddress
    {
        return BuyerAddress::where('user_id', $request->user()->id)->findOrFail($id);
    }

    private function book(Request $request, int $status = 200): JsonResponse
    {
        $buyer = $request->user()->fresh();

        return response()->json([
            'default'   => BuyerPresenter::deliveryAddress($buyer),
            'addresses' => $buyer->addresses()->orderBy('id')->get()->map(fn (BuyerAddress $a) => BuyerPresenter::addressEntry($a))->values(),
            'max'       => BuyerAddressBook::MAX,
        ], $status);
    }
}
