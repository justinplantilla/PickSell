<?php

namespace App\Pickrush\Http\Controllers\Api;

use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\Staff;

/** The rider as the rider app sees them: status first, then what they applied with. */
final class RiderProfile
{
    public static function of(Rider $rider): array
    {
        $rider->loadMissing('station', 'documents', 'zone');
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
            // For the Account page (read-only: the station keeps these).
            'zone' => $rider->zone?->name,
            'cod_limit' => (float) $rider->cod_limit,
            'max_parcels' => (int) $rider->max_parcels,
            'payout' => $rider->payout_method ? [
                'method' => $rider->payout_method,
                'account' => self::mask((string) $rider->payout_account),
            ] : null,
            'emergency_contact' => $rider->emergency_contact_name
                ? ['name' => $rider->emergency_contact_name, 'mobile' => $rider->emergency_contact_no]
                : null,
            'documents' => $rider->documents->sortBy(['doc_type', 'side'])->map(fn ($doc) => [
                'type' => $doc->doc_type,
                'label' => $doc->label(),
                'side' => $doc->side,
                'expiry' => $doc->expiry_date?->toDateString(),
            ])->values()->all(),
            'station_contact' => self::stationContact($rider),
        ];
    }

    /** "0917 *** 4567": enough to recognise the account, not to copy it. */
    private static function mask(string $account): ?string
    {
        $account = trim($account);
        if ($account === '') {
            return null;
        }

        return strlen($account) <= 4 ? $account : str_repeat('•', max(0, strlen($account) - 4)).substr($account, -4);
    }

    /** Who the rider calls at their station: the hub manager. */
    private static function stationContact(Rider $rider): ?array
    {
        $manager = Staff::where('hub_id', $rider->station_hub_id)->where('role', StaffRole::HubManager)
            ->where('is_active', true)->where('is_sample', false)->orderBy('id')->first();

        return $manager ? ['name' => $manager->full_name, 'mobile' => $manager->mobile_no] : null;
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
