<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use App\Support\VoucherForm;
use Illuminate\Http\Request;

/** A seller's store vouchers. The seller pays for them: orders are saved at the discounted amount. */
class SellerVoucherController extends Controller
{
    public function index(Request $request)
    {
        $vouchers = Voucher::where('seller_id', $request->user()->id)->withCount('redemptions')->withSum('redemptions', 'amount')->latest()->get();

        return view('seller.vouchers', ['vouchers' => $vouchers, 'types' => Voucher::STORE_TYPES]);
    }

    public function store(Request $request)
    {
        Voucher::create(VoucherForm::validated($request, $request->user()->id, null, Voucher::STORE_TYPES));

        return back()->with('success', 'Voucher created. Buyers see it at checkout.');
    }

    public function update(Request $request, Voucher $voucher)
    {
        abort_if($voucher->seller_id !== $request->user()->id, 404);
        $voucher->update(VoucherForm::validated($request, $request->user()->id, $voucher, Voucher::STORE_TYPES));

        return back()->with('success', 'Voucher updated.');
    }

    /** A voucher nobody used is deleted; a used one is switched off so its history stays. */
    public function destroy(Request $request, Voucher $voucher)
    {
        abort_if($voucher->seller_id !== $request->user()->id, 404);
        $voucher->redemptions()->exists() ? $voucher->update(['is_active' => false]) : $voucher->delete();

        return back()->with('success', 'Voucher removed.');
    }
}
