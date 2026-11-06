<?php

namespace App\Support;

use App\Models\BuyerAddress;
use App\Models\User;

/**
 * Where one checkout ships: the buyer's profile (default) address or one from their address book.
 * Shipping is priced on it and each order shipment keeps a copy (shipmentColumns) for the parcel.
 */
final class DeliveryAddress
{
    public function __construct(
        public readonly ?int $buyerAddressId,
        public readonly ?string $label,
        public readonly ?string $recipientName,
        public readonly ?string $phone,
        public readonly ?string $province,
        public readonly ?string $municipality,
        public readonly ?string $barangay,
        public readonly ?string $municipalityCode,
        public readonly ?string $barangayCode,
        public readonly ?string $street,
        public readonly ?string $houseNo,
        public readonly ?string $lat = null,
        public readonly ?string $lng = null,
    ) {}

    public static function fromProfile(User $buyer): self
    {
        return new self(null, 'Default', $buyer->full_name, $buyer->contact_no, $buyer->province, $buyer->municipality, $buyer->barangay,
            $buyer->municipality_code, $buyer->barangay_code, $buyer->street, $buyer->house_no, $buyer->delivery_lat, $buyer->delivery_lng);
    }

    public static function fromBook(BuyerAddress $address): self
    {
        return new self($address->id, $address->label, $address->recipient_name, $address->contact_no, $address->province, $address->municipality,
            $address->barangay, $address->municipality_code, $address->barangay_code, $address->street, $address->house_no,
            $address->delivery_lat, $address->delivery_lng);
    }

    /** The buyer's address book entry [$id], or their default address when null. */
    public static function forBuyer(User $buyer, ?int $id): self
    {
        return $id ? self::fromBook(BuyerAddress::where('user_id', $buyer->id)->findOrFail($id)) : self::fromProfile($buyer);
    }

    public function isComplete(): bool
    {
        return filled($this->province) && filled($this->municipality) && filled($this->barangay);
    }

    public function line(): string
    {
        return collect([$this->houseNo, $this->street, $this->barangay, $this->municipality, $this->province])->filter(fn ($part) => filled($part))->join(', ');
    }

    public function shipmentColumns(): array
    {
        return [
            'buyer_address_id' => $this->buyerAddressId,
            'recipient_name' => $this->recipientName,
            'recipient_phone' => $this->phone,
            'delivery_address' => $this->line() ?: null,
            'delivery_province' => $this->province,
            'delivery_municipality' => $this->municipality,
            'delivery_barangay' => $this->barangay,
            'delivery_municipality_code' => $this->municipalityCode,
            'delivery_barangay_code' => $this->barangayCode,
            'delivery_lat' => $this->lat,
            'delivery_lng' => $this->lng,
        ];
    }
}
