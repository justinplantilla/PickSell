<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\Orders\OrderLifecycleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Deleting a buyer or seller account, as the Privacy Policy describes it.
 *
 * The account row is never removed: orders, returns, payouts and commissions belong to the other
 * party too, and are needed for disputes, refunds, tax and audits (deleting the row would cascade
 * them away). Instead the person's details are erased: name, email, phone, birthday, address,
 * pins, IDs and business documents (files included), store branding, cart, wishlist, saved
 * addresses, follows and app sign-ins. The row is marked anonymized_at and deactivated, so it can
 * never sign in again. Order rows keep the product name and price they always had.
 *
 * Refused while the account still has an order or a return in progress, so no buyer, seller or
 * rider is left with a parcel nobody answers for.
 */
class AccountDeletion
{
    private const OPEN_RETURN_STATUSES = ['requested', 'awaiting_item', 'received', 'inspected', 'approved_for_refund', 'refund_due'];

    private const FILE_FIELDS = ['id_upload', 'business_permit', 'dti_certificate', 'sec_certificate', 'bir_certificate', 'store_logo', 'store_banner'];

    /** Why the account cannot be deleted yet, or null when it can. */
    public function blocker(User $user): ?string
    {
        $openOrders = Order::where(fn ($q) => $q->where('buyer_id', $user->id)->orWhere('seller_id', $user->id))
            ->whereNotIn('status', OrderLifecycleService::TERMINAL)->count();
        if ($openOrders > 0) {
            return "You still have {$openOrders} ".($openOrders === 1 ? 'order' : 'orders').' in progress. Finish or cancel '.($openOrders === 1 ? 'it' : 'them').' before deleting your account.';
        }

        $openReturns = ReturnRequest::where(fn ($q) => $q->where('buyer_id', $user->id)->orWhere('seller_id', $user->id))
            ->whereIn('status', self::OPEN_RETURN_STATUSES)->count();
        if ($openReturns > 0) {
            return "You still have {$openReturns} return ".($openReturns === 1 ? 'request' : 'requests').' in progress. Wait until '.($openReturns === 1 ? 'it is' : 'they are').' resolved before deleting your account.';
        }

        return null;
    }

    public function delete(User $user): void
    {
        $files = array_filter(array_map(fn (string $field) => $user->getRawOriginal($field), self::FILE_FIELDS));

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->addresses()->delete();
            $user->wishlistItems()->delete();
            $user->recentlyViewedProducts()->delete();
            $user->followedSellers()->detach();
            $user->followers()->detach();
            Cart::where('buyer_id', $user->id)->get()->each->delete();
            $user->notifications()->delete();
            if ($user->role === 'seller') {
                $user->products()->where('status', '!=', 'archived')->update(['status' => 'archived']);
            }

            $user->forceFill([
                'first_name' => 'Deleted',
                'last_name' => 'User',
                'middle_initial' => null,
                'email' => "deleted-{$user->id}@deleted.invalid",
                'password' => Hash::make(Str::random(64)),
                'remember_token' => null,
                'contact_no' => "deleted-{$user->id}",
                'birthday' => '1900-01-01',
                'age' => 0,
                'province' => '-',
                'municipality' => '-',
                'barangay' => '-',
                'street' => null,
                'house_no' => null,
                'postal_code' => null,
                'pickup_lat' => null,
                'pickup_lng' => null,
                'delivery_lat' => null,
                'delivery_lng' => null,
                'tin' => null,
                'business_name' => $user->role === 'seller' ? "Closed shop #{$user->id}" : null,
                'store_description' => null,
                'vacation_message' => null,
                'status' => 'deactivated',
                'anonymized_at' => now(),
            ] + array_fill_keys(self::FILE_FIELDS, null))->save();
        });

        Storage::disk('public')->delete($files);
    }
}
