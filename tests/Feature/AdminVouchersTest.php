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
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-06 12:00:00'));
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.vouchers.store'), [
            'code' => 'weekend10', 'title' => 'Weekend sale', 'type' => 'percent', 'amount' => 10, 'max_discount' => 150,
            'per_buyer_limit' => 2, 'starts_at' => '2026-11-07 00:00', 'ends_at' => '2026-11-08 23:59', 'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $voucher = Voucher::sole();
        $this->assertNull($voucher->seller_id);
        $this->assertSame(['WEEKEND10', 'percent', '10.00'], [$voucher->code, $voucher->type, $voucher->amount]);
        $this->assertSame(1, AuditLog::where('action', 'voucher.created')->count());

        $this->actingAs($admin)->get(route('admin.vouchers'))->assertOk()
            ->assertSee('WEEKEND10')->assertSee('Starts Nov 7')->assertDontSee('value="free_shipping"', false);

        // Free shipping is not a kind admins can pick any more.
        $this->actingAs($admin)->post(route('admin.vouchers.store'), ['code' => 'FREESHIP', 'title' => 'Free', 'type' => 'free_shipping', 'per_buyer_limit' => 1])
            ->assertSessionHasErrors('type');

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

    public function test_the_voucher_list_shows_how_much_of_the_usage_limit_is_used(): void
    {
        $admin = $this->admin();
        $limited = Voucher::create(['code' => 'HALF', 'title' => 'Half used', 'type' => 'fixed', 'amount' => 50, 'per_buyer_limit' => 1, 'usage_limit' => 20, 'is_active' => true]);
        Voucher::create(['code' => 'OPEN', 'title' => 'No limit', 'type' => 'fixed', 'amount' => 50, 'per_buyer_limit' => 1, 'is_active' => true]);
        $buyer = \App\Models\User::create(['role' => 'buyer', 'status' => 'approved', 'first_name' => 'B', 'last_name' => 'U', 'email' => 'vb@test.test', 'password' => bcrypt('x'), 'contact_no' => '09170000000', 'sex' => 'Female', 'birthday' => '1995-01-01', 'age' => 30, 'province' => 'Batangas', 'municipality' => 'City of Tanauan', 'barangay' => 'Altura Bata', 'house_no' => '1', 'street' => 'Rizal St']);
        for ($i = 0; $i < 4; $i++) {
            \App\Models\VoucherRedemption::forceCreate(['voucher_id' => $limited->id, 'buyer_id' => $buyer->id, 'amount' => 50]);
        }

        $this->actingAs($admin)->get(route('admin.vouchers'))->assertOk()
            ->assertSee('20% used')->assertSee('aria-valuenow="20"', false)
            ->assertSee('class="voucher-usage-bar"', false)->assertDontSee('Live')->assertDontSee('used of');
    }

    public function test_a_peso_off_voucher_never_keeps_a_maximum_discount(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.vouchers.store'), [
            'code' => 'FLAT50', 'title' => 'Flat', 'type' => 'fixed', 'amount' => 50, 'max_discount' => 20, 'per_buyer_limit' => 1, 'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertNull(Voucher::sole()->max_discount);
        $this->actingAs($admin)->get(route('admin.vouchers'))->assertOk()->assertSee('data-voucher-cap', false)->assertSee('data-voucher-kind', false);
    }
}
