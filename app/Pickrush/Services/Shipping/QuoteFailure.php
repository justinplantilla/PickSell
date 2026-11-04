<?php

namespace App\Pickrush\Services\Shipping;

/** Why a parcel could not be priced. Checkout shows each one differently and never charges a guessed fee. */
enum QuoteFailure: string
{
    case UnknownProvince = 'UNKNOWN_PROVINCE';  // the buyer must fix the address
    case RequiresQuote = 'REQUIRES_QUOTE';      // too heavy to price automatically
    case NoRate = 'NO_RATE';                    // no rate in force for the region
    case InvalidWeight = 'INVALID_WEIGHT';      // a missing, zero or malformed weight or quantity
    case Unavailable = 'SERVICE_UNAVAILABLE';   // pricing could not run (e.g. database error): retry

    public function message(): string
    {
        return match ($this) {
            self::UnknownProvince => 'We could not find your province. Please check your delivery address.',
            self::RequiresQuote => 'Heavy parcel: shipping will be quoted by PickSell.',
            self::NoRate => 'Shipping to this address is not available yet. Please contact support.',
            self::InvalidWeight => 'This item cannot be shipped yet because its weight is missing.',
            self::Unavailable => 'We could not calculate shipping right now. Please try again in a moment.',
        };
    }
}
