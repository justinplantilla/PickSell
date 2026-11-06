<?php

namespace App\Http\Controllers;

use App\Models\BuyerAddress;
use App\Models\OrderShipment;
use App\Pickrush\Bridge\PicksellBridge;
use App\Services\Geo\AddressResolver;
use App\Support\DeliveryAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The buyer's address book: extra delivery addresses to choose at checkout. The profile address
 * stays the default and is edited on the account page. Barangay and city codes are looked up
 * here from the names, never taken from the browser, since Pickrush routes parcels by them.
 */
class BuyerAddressController extends Controller
{
    public const MAX_ADDRESSES = 10;

    public function __construct(private AddressResolver $resolver) {}

    public function store(Request $request)
    {
        abort_if($request->user()->addresses()->count() >= self::MAX_ADDRESSES, 422, 'You can save up to '.self::MAX_ADDRESSES.' addresses.');
        $address = $request->user()->addresses()->create($this->validated($request));

        return $this->back($request, $address->id)->with('success', 'Address saved.');
    }

    public function update(Request $request, BuyerAddress $address)
    {
        abort_if($address->user_id !== $request->user()->id, 404);
        $address->update($this->validated($request));
        if ($address->wasChanged(['delivery_lat', 'delivery_lng'])) {
            PicksellBridge::addressPinned($address);
        }

        return $this->back($request, $address->id)->with('success', 'Address updated.');
    }

    /**
     * Make an address-book entry the default: its place (and pin) moves onto the profile, and the
     * old default is kept in the book so nothing is lost. Orders already placed keep their address.
     */
    public function makeDefault(Request $request, BuyerAddress $address)
    {
        $buyer = $request->user();
        abort_if($address->user_id !== $buyer->id, 404);
        $place = ['province', 'municipality', 'barangay', 'municipality_code', 'barangay_code', 'street', 'house_no', 'delivery_lat', 'delivery_lng'];

        DB::transaction(function () use ($buyer, $address, $place) {
            $previous = $buyer->addresses()->create(['label' => 'Previous default', 'recipient_name' => $buyer->full_name, 'contact_no' => $buyer->contact_no]
                + $buyer->only($place));
            // Orders to the old default stay there: older shipments without a copy of the address get one,
            // and all of them point at its book entry so the new default's pin never moves them.
            OrderShipment::where('buyer_id', $buyer->id)->whereNull('buyer_address_id')->whereNull('delivery_province')
                ->update(DeliveryAddress::fromBook($previous)->shipmentColumns());
            OrderShipment::where('buyer_id', $buyer->id)->whereNull('buyer_address_id')
                ->update(['buyer_address_id' => $previous->id]);
            OrderShipment::where('buyer_address_id', $address->id)->update(['buyer_address_id' => null]);
            $buyer->forceFill($address->only($place))->save();
            $address->delete();
        });

        return $this->back($request, null)->with('success', 'Default address changed. Your old default is saved under Other Addresses.');
    }

    public function destroy(Request $request, BuyerAddress $address)
    {
        abort_if($address->user_id !== $request->user()->id, 404);
        $address->delete();

        return $this->back($request, null)->with('success', 'Address removed.');
    }

    private function validated(Request $request): array
    {
        // "+63 917 123 4567" and "0917 123 4567" are saved as 09171234567, as on registration.
        $mobile = preg_replace('/\s+/', '', (string) $request->input('contact_no'));
        $request->merge(['contact_no' => preg_match('/^\+?63(9\d{9})$/', $mobile, $m) ? '0'.$m[1] : $mobile]);
        $data = $request->validate([
            'label' => 'nullable|string|max:40',
            'recipient_name' => 'required|string|max:120',
            'contact_no' => ['required', 'regex:/^09\d{9}$/'],
            'province' => 'required|string|max:255',
            'municipality' => 'required|string|max:255',
            'barangay' => 'required|string|max:255',
            'street' => 'nullable|string|max:255',
            'house_no' => 'nullable|string|max:60',
            // The door pin is optional; the Philippines only, as for the default address.
            'lat' => 'nullable|required_with:lng|numeric|between:4,22',
            'lng' => 'nullable|required_with:lat|numeric|between:116,127',
        ], [
            'contact_no.regex' => 'Enter an 11-digit mobile number starting with 09, like 09171234567.',
            'lat.between' => 'Pin a spot inside the Philippines.', 'lng.between' => 'Pin a spot inside the Philippines.',
        ]);

        $codes = $this->resolver->resolve($data['province'], $data['municipality'], $data['barangay']);

        return collect($data)->except(['lat', 'lng'])->all() + [
            'municipality_code' => $codes['municipality_code'], 'barangay_code' => $codes['barangay_code'],
            'delivery_lat' => $data['lat'] ?? null, 'delivery_lng' => $data['lng'] ?? null,
        ];
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
