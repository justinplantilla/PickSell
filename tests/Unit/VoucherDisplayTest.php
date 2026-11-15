<?php

namespace Tests\Unit;

use App\Models\Voucher;
use PHPUnit\Framework\TestCase;

class VoucherDisplayTest extends TestCase
{
    public function test_percentage_label_uses_actual_amount_and_cap(): void
    {
        $voucher = new Voucher([
            'title' => 'Everything free', 'type' => Voucher::PERCENT,
            'amount' => 12.25, 'max_discount' => 99.75, 'min_spend' => 500.25,
            'per_buyer_limit' => 2, 'usage_limit' => 20,
        ]);

        $this->assertSame('12.25% off up to ₱99.75', $voucher->discount_label);
        $this->assertSame('12.25% off up to ₱99.75 · min. spend ₱500.25', $voucher->summary);
        $this->assertSame('Minimum spend ₱500.25 · 2 uses per buyer · 20 total uses', $voucher->buyer_conditions);
    }

    public function test_shipping_label_includes_actual_cap_and_unlimited_usage_has_no_false_limit(): void
    {
        $voucher = new Voucher([
            'type' => Voucher::FREE_SHIPPING, 'max_discount' => 50.25,
            'min_spend' => 0, 'per_buyer_limit' => 1,
        ]);

        $this->assertSame('Free shipping up to ₱50.25', $voucher->discount_label);
        $this->assertSame('No minimum spend · Once per buyer', $voucher->buyer_conditions);
    }
}
