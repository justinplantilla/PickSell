<?php

namespace App\Services;

class PlatformCommission
{
    public function rate(): float
    {
        return (float) config('app.platform_commission_rate', 10);
    }

    public function deduction(float $grossAmount): float
    {
        return round($grossAmount * $this->rate() / 100, 2);
    }
}
