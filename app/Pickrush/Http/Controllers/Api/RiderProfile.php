<?php

namespace App\Pickrush\Http\Controllers\Api;

use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Rider;

/** The rider as the rider app sees them: status first, then what they applied with. */
final class RiderProfile
{
    public static function of(Rider $rider): array
    {
        $rider->loadMissing('station', 'documents');
        $license = $rider->documents->firstWhere('doc_type', 'drivers_license');

        return [
            'id' => $rider->id,
            'status' => $rider->status,
            'status_reason' => $rider->status_reason,
            'shift_status' => $rider->shift_status,
            'first_name' => $rider->first_name,
            'last_name' => $rider->last_name,
            'full_name' => $rider->full_name,
            'email' => $rider->email,
            'mobile' => substr((string) $rider->mobile_no, 1),             // 10 digits after +63
            'birth_date' => $rider->birthdate?->toDateString(),
            'province' => self::place($rider->province_code, $rider->province_name),
            'city' => self::place($rider->city_code, $rider->city_name),
            'barangay' => self::place($rider->barangay_code, $rider->barangay_name),
            'street' => $rider->street,
            'address' => $rider->address,
            'vehicle_type' => $rider->vehicle_type,
            'plate_number' => $rider->plate_no,
            'license_no' => $rider->license_no,
            'license_expiry' => $license?->expiry_date?->toDateString(),
            'hub' => $rider->station ? self::hub($rider->station) : null,
            'on_probation' => $rider->on_probation,
            'applied_at' => $rider->applied_at?->toIso8601String(),
            'approved_at' => $rider->approved_at?->toIso8601String(),
        ];
    }

    public static function hub(Hub $hub): array
    {
        return [
            'id' => (string) $hub->id,
            'name' => $hub->name,
            'province' => $hub->province_name,
            'city' => $hub->municipality_name,
            'address' => $hub->address,
        ];
    }

    private static function place(?string $code, ?string $name): ?array
    {
        return $code && $name ? ['code' => $code, 'name' => $name] : null;
    }
}
