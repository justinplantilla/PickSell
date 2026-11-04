<?php

namespace App\Services;

use DomainException;

/**
 * Splits a parcel's delivery fee, in this order:
 *  1. the rider's pay (from the parcel class);
 *  2. balance = fee − rider pay;
 *  3. PickSell's delivery commission = balance × rate, rounded half up to the centavo;
 *  4. the logistics company keeps the remainder, so the parts always add up to the fee exactly.
 * Whole centavos throughout, never floats.
 */
class DeliveryFeeSplit
{
    public function __construct(private CommissionService $money) {}

    public function rate(): string
    {
        return number_format((float) config('app.delivery_commission_rate', 20), 2, '.', '');
    }

    /**
     * @return array{delivery_fee: string, rider_pay: string, balance: string, platform_commission: string, logistics_share: string, rate: string}
     *
     * @throws DomainException when nothing is left after the rider's pay
     */
    public function split(string $deliveryFee, string $riderPay, ?string $ratePercent = null): array
    {
        $ratePercent ??= $this->rate();
        $feeCents = $this->money->toCents($deliveryFee);
        $riderCents = $this->money->toCents($riderPay);
        $basisPoints = $this->money->toCents($ratePercent);      // 20.00% → 2000
        if ($riderCents < 0 || $basisPoints < 0 || $basisPoints > 10000) {
            throw new DomainException('Rider pay and the delivery commission rate must be valid amounts.');
        }

        $balanceCents = $feeCents - $riderCents;
        if ($balanceCents <= 0) {
            throw new DomainException("A delivery fee of ₱{$this->money->fromCents($feeCents)} does not cover the rider's ₱{$this->money->fromCents($riderCents)}.");
        }

        $commissionCents = intdiv($balanceCents * $basisPoints + 5000, 10000);

        return [
            'delivery_fee' => $this->money->fromCents($feeCents),
            'rider_pay' => $this->money->fromCents($riderCents),
            'balance' => $this->money->fromCents($balanceCents),
            'platform_commission' => $this->money->fromCents($commissionCents),
            'logistics_share' => $this->money->fromCents($balanceCents - $commissionCents),
            'rate' => $this->money->fromCents($basisPoints),
        ];
    }
}
