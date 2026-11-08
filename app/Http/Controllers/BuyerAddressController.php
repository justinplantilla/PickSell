<?php

namespace App\Http\Controllers;

use App\Models\BuyerAddress;
use App\Services\BuyerAddressBook;
use Illuminate\Http\Request;

/**
 * The buyer's address book: extra delivery addresses to choose at checkout. The profile address
 * stays the default and is edited on the account page. The rules live in BuyerAddressBook, which
 * the buyer app's address endpoints share.
 */
class BuyerAddressController extends Controller
{
    public const MAX_ADDRESSES = BuyerAddressBook::MAX;

    public function __construct(private BuyerAddressBook $book) {}

    public function store(Request $request)
    {
        abort_if($request->user()->addresses()->count() >= self::MAX_ADDRESSES, 422, 'You can save up to '.self::MAX_ADDRESSES.' addresses.');
        $address = $this->book->add($request->user(), $request);

        return $this->back($request, $address->id)->with('success', 'Address saved.');
    }

    public function update(Request $request, BuyerAddress $address)
    {
        abort_if($address->user_id !== $request->user()->id, 404);
        $this->book->update($address, $request);

        return $this->back($request, $address->id)->with('success', 'Address updated.');
    }

    /** Make an address-book entry the default; the old default is kept in the book. */
    public function makeDefault(Request $request, BuyerAddress $address)
    {
        abort_if($address->user_id !== $request->user()->id, 404);
        $this->book->makeDefault($request->user(), $address);

        return $this->back($request, null)->with('success', 'Default address changed. Your old default is saved under Other Addresses.');
    }

    public function destroy(Request $request, BuyerAddress $address)
    {
        abort_if($address->user_id !== $request->user()->id, 404);
        $this->book->remove($address);

        return $this->back($request, null)->with('success', 'Address removed.');
    }

    /** Back to checkout with the same items, showing the address just saved (or the default). */
    private function back(Request $request, ?int $addressId)
    {
        $items = array_filter((array) $request->input('item_ids', []), 'is_numeric');
        if ($items) {
            return redirect()->route('buyer.checkout.start', array_filter(['item_ids' => $items, 'address_id' => $addressId]));
        }

        return redirect()->to(route('buyer.account').'#addresses');
    }
}
