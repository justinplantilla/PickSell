<?php

namespace App\Pickrush\Services\Shipping;

/**
 * The price of one seller's parcel, or why it has none. Weights and money are decimal strings
 * (never floats).
 */
final class ShippingQuote
{
    /** Priced from rows for the exact (seller region → buyer region) route. */
    public const ORIGIN_DESTINATION = 'ORIGIN_DESTINATION';

    /** Priced from the generic destination rows: the route has none, or the seller's region is unknown. */
    public const GENERIC_FALLBACK = 'GENERIC_FALLBACK';

    private function __construct(
        public readonly ?QuoteFailure $failure,
        public readonly ?string $originRegion = null,
        public readonly ?string $destinationRegion = null,
        public readonly ?string $priceSource = null,
        public readonly ?string $chargeableWeightKg = null,
        public readonly ?string $weightTierKg = null,
        public readonly ?string $parcelClass = null,
        public readonly ?string $deliveryFee = null,
        public readonly ?string $riderPay = null,
        public readonly ?string $ratesVersion = null,
    ) {}

    public static function priced(?string $originRegion, string $destinationRegion, string $priceSource, string $chargeableWeightKg,
        string $weightTierKg, string $parcelClass, string $deliveryFee, string $riderPay, string $ratesVersion): self
    {
        return new self(null, $originRegion, $destinationRegion, $priceSource, $chargeableWeightKg, $weightTierKg, $parcelClass,
            $deliveryFee, $riderPay, $ratesVersion);
    }

    /** False when the seller's province could not be matched to a region (generic prices were used). */
    public function originResolved(): bool
    {
        return $this->originRegion !== null;
    }

    public static function failed(QuoteFailure $failure, ?string $chargeableWeightKg = null, ?string $destinationRegion = null): self
    {
        return new self($failure, destinationRegion: $destinationRegion, chargeableWeightKg: $chargeableWeightKg);
    }

    public function ok(): bool
    {
        return $this->failure === null;
    }

    /** The shape in docs/shipping-contract.md: money and weights as 2-decimal strings. */
    public function toArray(): array
    {
        if (! $this->ok()) {
            return ['ok' => false, 'reason' => $this->failure->value, 'message' => $this->failure->message()];
        }

        return [
            'ok' => true,
            'origin_region' => $this->originRegion,
            'origin_resolved' => $this->originResolved(),
            'destination_region' => $this->destinationRegion,
            'price_source' => $this->priceSource,
            'chargeable_weight_kg' => $this->chargeableWeightKg,
            'weight_tier_kg' => $this->weightTierKg,
            'parcel_class' => $this->parcelClass,
            'delivery_fee' => $this->deliveryFee,
            'rider_pay' => $this->riderPay,
            'rates_version' => $this->ratesVersion,
        ];
    }
}
