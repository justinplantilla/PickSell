<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

class AdminVouchersTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    public function test_admins_run_pickSell_vouchers_and_campaigns(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.vouchers.store'), [
            'code' => 'freeship-weekend', 'title' => 'Free shipping weekend', 'type' => 'free_shipping', 'max_discount' => 150,
            'per_buyer_limit' => 2, 'starts_at' => '2026-11-07 00:00', 'ends_at' => '2026-11-08 23:59', 'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $voucher = Voucher::sole();
        $this->assertNull($voucher->seller_id);
        $this->assertSame(['FREESHIP-WEEKEND', 'free_shipping', '0.00'], [$voucher->code, $voucher->type, $voucher->amount]);
        $this->assertSame(1, AuditLog::where('action', 'voucher.created')->count());

        $this->actingAs($admin)->get(route('admin.vouchers'))->assertOk()
            ->assertSee('FREESHIP-WEEKEND')->assertSee('Free shipping up to ₱150')->assertSee('Scheduled');

        // A store voucher is the seller's, not editable here.
        $store = Voucher::create(['seller_id' => $this->seller()->id, 'code' => 'MINE', 'title' => 'Mine', 'type' => 'fixed', 'amount' => 10, 'per_buyer_limit' => 1]);
        $this->actingAs($admin)->delete(route('admin.vouchers.destroy', $store))->assertNotFound();

        $this->actingAs($admin)->delete(route('admin.vouchers.destroy', $voucher))->assertRedirect();
        $this->assertModelMissing($voucher);
    }

    public function test_only_admins_reach_the_vouchers_page(): void
    {
        $this->actingAs($this->buyer())->get(route('admin.vouchers'))->assertRedirect();
        $this->actingAs($this->seller())->post(route('admin.vouchers.store'), ['code' => 'X'])->assertRedirect();
        $this->assertSame(0, Voucher::count());
    }
}
