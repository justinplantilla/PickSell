<?php

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Models\Voucher;
use App\Services\AuditLogger;
use App\Support\VoucherForm;
use Illuminate\Http\Request;

/**
 * PickSell's own vouchers and campaigns (e.g. "Free shipping weekend"). PickSell pays for them:
 * sellers keep their full sale and the rider collects less from the buyer.
 */
class AdminVoucherController extends Controller
{
    public function index()
    {
        $vouchers = Voucher::whereNull('seller_id')->withCount('redemptions')->withSum('redemptions', 'amount')->latest()->get();
        $storeVouchers = Voucher::whereNotNull('seller_id')->live()->count();

        return view('admin.vouchers', ['vouchers' => $vouchers, 'types' => Voucher::PLATFORM_TYPES, 'storeVouchers' => $storeVouchers]);
    }

    public function store(Request $request)
    {
        $voucher = Voucher::create(VoucherForm::validated($request, null, null, Voucher::PLATFORM_TYPES));
        $this->audit($request, 'voucher.created', $voucher);

        return back()->with('success', "Voucher {$voucher->code} created.");
    }

    public function update(Request $request, Voucher $voucher)
    {
        abort_unless($voucher->isPlatform(), 404);
        $voucher->update(VoucherForm::validated($request, null, $voucher, Voucher::PLATFORM_TYPES));
        $this->audit($request, 'voucher.updated', $voucher);

        return back()->with('success', "Voucher {$voucher->code} updated.");
    }

    /** A voucher nobody used is deleted; a used one is switched off so its history stays. */
    public function destroy(Request $request, Voucher $voucher)
    {
        abort_unless($voucher->isPlatform(), 404);
        $this->audit($request, 'voucher.removed', $voucher);
        $voucher->redemptions()->exists() ? $voucher->update(['is_active' => false]) : $voucher->delete();

        return back()->with('success', "Voucher {$voucher->code} removed.");
    }

    private function audit(Request $request, string $action, Voucher $voucher): void
    {
        app(AuditLogger::class)->record($action, $voucher, metadata: [
            'code' => $voucher->code, 'type' => $voucher->type, 'amount' => (string) $voucher->amount, 'active' => $voucher->is_active,
        ], permission: Permission::SETTINGS_MANAGE);
    }
}
