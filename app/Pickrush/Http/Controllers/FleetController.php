<?php

namespace App\Pickrush\Http\Controllers;

use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\AuditLog;
use App\Pickrush\Models\Driver;
use App\Pickrush\Models\FleetRenewal;
use App\Pickrush\Models\Manifest;
use App\Pickrush\Models\Vehicle;
use App\Pickrush\Services\OperationRefused;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * The hub's company trucks and the drivers Pickrush hired for them. A station keeps one truck, a
 * province hub up to three; each truck has a default driver. Trips are dispatched from this list.
 * The hub manager keeps each truck's OR/CR and each driver's license current; a renewal keeps a
 * record of what it replaced.
 */
class FleetController extends PortalController
{
    private const PHOTO = 'file|mimes:jpg,jpeg,png,heic,webp|max:8192';

    private const PAPER = 'file|mimes:jpg,jpeg,png,heic,webp,pdf|max:8192';

    public function index()
    {
        $hub = $this->hub();
        $out = Manifest::where('from_hub_id', $hub->id)->where('status', 'dispatched')->get(['vehicle_id', 'driver_id']);

        return view('pickrush.fleet.index', [
            'vehicles' => Vehicle::with('defaultDriver', 'renewals.renewer')->orderBy('plate_no')->get(),
            'drivers' => Driver::with('renewals.renewer')->orderBy('full_name')->get(),
            'vehiclesOut' => $out->pluck('vehicle_id')->flip(),
            'driversOut' => $out->pluck('driver_id')->flip(),
            'limit' => Vehicle::limitFor($hub),
            'company' => $this->staff()->company?->name,
            'licenseCodes' => Driver::LICENSE_CODES,
            'canManage' => $this->staff()->hasRole(StaffRole::HubManager),
        ]);
    }

    // Trucks ----------------------------------------------------------------------------------

    public function store(Request $request)
    {
        $this->managerOnly();
        $data = $this->validatedVehicle($request) + $this->validatedPapers($request);

        return $this->attempt(function () use ($request, $data) {
            DB::transaction(function () use ($request, $data) {
                $this->checkLimit();
                $vehicle = Vehicle::create($data + ['hub_id' => $this->hub()->id, 'registered_owner' => $this->staff()->company?->name, 'is_active' => true]);
                $vehicle->update($this->storePapers($request, $vehicle));
            });
        }, "{$data['plate_no']} added to the fleet.");
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $this->managerOnly();
        $data = $this->validatedVehicle($request, $vehicle) + ['is_active' => $request->boolean('is_active')];

        return $this->attempt(function () use ($vehicle, $data) {
            DB::transaction(function () use ($vehicle, $data) {
                if ($data['is_active'] && ! $vehicle->is_active) {
                    $this->checkLimit();
                }
                $vehicle->update($data);
            });
        }, "{$vehicle->plate_no} saved.");
    }

    /** A new OR (and the CR, if it changed): the old numbers, expiry and photos stay on record. */
    public function renewPapers(Request $request, Vehicle $vehicle)
    {
        $this->managerOnly();
        $data = $this->validatedPapers($request, $vehicle);

        return $this->attempt(function () use ($request, $vehicle, $data) {
            DB::transaction(function () use ($request, $vehicle, $data) {
                $fields = ['cr_no', 'mv_file_no', 'engine_no', 'chassis_no', 'or_no', 'registration_expiry', 'or_photo_path', 'cr_photo_path'];
                $previous = array_intersect_key($vehicle->getAttributes(), array_flip($fields));
                $vehicle->fill($data + $this->storePapers($request, $vehicle));
                if ($vehicle->isDirty($fields)) {
                    FleetRenewal::create(['vehicle_id' => $vehicle->id, 'document' => 'or_cr', 'previous' => $previous, 'renewed_by' => $this->staff()->id]);
                }
                $vehicle->save();
            });
        }, "{$vehicle->plate_no}'s OR/CR updated. Registered until ".\Illuminate\Support\Carbon::parse($data['registration_expiry'])->format('M d, Y').'.');
    }

    /** The OR or CR photo, for the hub's own staff (another hub's truck is a 404 by the hub scope). */
    public function paper(Vehicle $vehicle, string $document)
    {
        $path = match ($document) {
            'or' => $vehicle->or_photo_path,
            'cr' => $vehicle->cr_photo_path,
            default => null,
        };
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        AuditLog::record($vehicle, 'viewed');

        return Storage::disk('local')->response($path);
    }

    // Drivers ---------------------------------------------------------------------------------

    public function storeDriver(Request $request)
    {
        $this->managerOnly();
        // One validation, so every mistake on the form shows at once.
        $request->validate(self::driverRules() + $this->licenseRules($request), self::DRIVER_MESSAGES + self::LICENSE_MESSAGES);
        $data = $this->validatedDriver($request) + $this->validatedLicense($request);

        return $this->attempt(function () use ($request, $data) {
            $driver = Driver::create($data + ['hub_id' => $this->hub()->id, 'is_active' => true]);
            $driver->update($this->storeLicense($request, $driver));
        }, "{$data['first_name']} {$data['last_name']} is registered as a driver.");
    }

    public function updateDriver(Request $request, Driver $driver)
    {
        $this->managerOnly();
        $data = $this->validatedDriver($request, $driver) + ['is_active' => $request->boolean('is_active')];

        return $this->attempt(fn () => $driver->update($data), "{$driver->full_name} saved.");
    }

    /** A renewed license: the old number, codes, expiry and photos stay on record. */
    public function renewLicense(Request $request, Driver $driver)
    {
        $this->managerOnly();
        $data = $this->validatedLicense($request, $driver);

        return $this->attempt(function () use ($request, $driver, $data) {
            DB::transaction(function () use ($request, $driver, $data) {
                $fields = ['license_no', 'license_codes', 'license_expiry', 'license_front_path', 'license_back_path'];
                $previous = array_intersect_key($driver->getAttributes(), array_flip($fields));
                $driver->fill($data + $this->storeLicense($request, $driver));
                if ($driver->isDirty($fields)) {
                    FleetRenewal::create(['driver_id' => $driver->id, 'document' => 'license', 'previous' => $previous, 'renewed_by' => $this->staff()->id]);
                }
                $driver->save();
            });
        }, "{$driver->full_name}'s license updated. Valid until ".\Illuminate\Support\Carbon::parse($data['license_expiry'])->format('M d, Y').'.');
    }

    /** The license photo, for the hub's own staff (another hub's driver is a 404 by the hub scope). */
    public function license(Driver $driver, string $side)
    {
        $path = match ($side) {
            'front' => $driver->license_front_path,
            'back' => $driver->license_back_path,
            default => null,
        };
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        AuditLog::record($driver, 'viewed');

        return Storage::disk('local')->response($path);
    }

    // ------------------------------------------------------------------------------------------

    private function validatedVehicle(Request $request, ?Vehicle $vehicle = null): array
    {
        $data = $request->validate([
            'plate_no' => ['required', 'string', 'max:20', Rule::unique('pr_vehicles', 'plate_no')->ignore($vehicle?->id)],
            'vehicle_type' => 'required|in:truck,van',
            'capacity_parcels' => 'nullable|integer|min:1|max:100000',
            'default_driver_id' => ['nullable', 'integer', Rule::exists('pr_drivers', 'id')->where('hub_id', $this->hub()->id)],
        ]);
        $data['plate_no'] = strtoupper(trim($data['plate_no']));

        return $data;
    }

    /**
     * The LTO papers: CR no., MV file no., engine and chassis no. (from the CR) and the OR no. with
     * the registration's expiry. Photos are required for a new truck, or when none is on file yet.
     */
    private function validatedPapers(Request $request, ?Vehicle $vehicle = null): array
    {
        $upper = fn (string $key) => strtoupper(preg_replace('/\s+/', '', (string) $request->input($key)));
        $mvFile = preg_replace('/\D/', '', (string) $request->input('mv_file_no'));
        $request->merge([
            'cr_no' => $upper('cr_no'), 'or_no' => $upper('or_no'), 'engine_no' => $upper('engine_no'), 'chassis_no' => $upper('chassis_no'),
            'mv_file_no' => strlen($mvFile) > 4 ? substr($mvFile, 0, 4).'-'.substr($mvFile, 4) : $mvFile,
        ]);
        $data = $request->validate([
            'cr_no' => ['required', 'regex:/^\d{7,12}$/', Rule::unique('pr_vehicles', 'cr_no')->ignore($vehicle?->id)],
            'mv_file_no' => ['required', 'regex:/^\d{4}-\d{7,11}$/', Rule::unique('pr_vehicles', 'mv_file_no')->ignore($vehicle?->id)],
            'engine_no' => ['required', 'regex:/^[A-Z0-9-]{5,20}$/'],
            'chassis_no' => ['required', 'regex:/^[A-Z0-9-]{8,20}$/', Rule::unique('pr_vehicles', 'chassis_no')->ignore($vehicle?->id)],
            'or_no' => ['required', 'regex:/^\d{7,12}$/'],
            'registration_expiry' => 'required|date|after:today|before_or_equal:+3 years',
            'or_photo' => ($vehicle?->or_photo_path ? 'nullable|' : 'required|').self::PAPER,
            'cr_photo' => ($vehicle?->cr_photo_path ? 'nullable|' : 'required|').self::PAPER,
        ], [
            'cr_no.regex' => 'The CR no. is 7 to 12 digits.',
            'cr_no.unique' => 'This CR no. is already registered to another truck.',
            'mv_file_no.regex' => 'Format must be 0000-00000000000.',
            'mv_file_no.unique' => 'This MV file no. is already registered to another truck.',
            'engine_no.regex' => 'Letters, digits and dashes only (5 to 20).',
            'chassis_no.regex' => 'Letters, digits and dashes only (8 to 20).',
            'chassis_no.unique' => 'This chassis no. is already registered to another truck.',
            'or_no.regex' => 'The OR no. is 7 to 12 digits.',
            'registration_expiry.after' => 'The registration is expired. Renew it with LTO first.',
            'registration_expiry.before_or_equal' => 'A registration runs 3 years at most.',
        ]);
        unset($data['or_photo'], $data['cr_photo']);

        return $data;
    }

    /** @return array<string, string> the OR/CR photo columns that were uploaded */
    private function storePapers(Request $request, Vehicle $vehicle): array
    {
        $paths = [];
        foreach (['or', 'cr'] as $document) {
            if ($file = $request->file("{$document}_photo")) {
                $paths["{$document}_photo_path"] = $file->store("pickrush/vehicles/{$vehicle->id}", 'local');   // private disk
            }
        }

        return $paths;
    }

    /** The same formats the rider application asks for (name, +63 mobile). */
    private const DRIVER_MESSAGES = [
        'birthdate.before_or_equal' => 'The driver must be at least 18 years old.',
        'mobile.regex' => 'Enter a valid 10-digit number.',
    ];

    private const LICENSE_MESSAGES = [
        'license_no.regex' => 'Format must be A00-00-000000.',
        'license_no.unique' => 'This license no. is already registered.',
        'license_expiry.after' => 'The license is expired. Please use a valid license.',
    ];

    private static function driverRules(?Driver $driver = null): array
    {
        return [
            'first_name' => 'required|string|min:2|max:60',
            'last_name' => 'required|string|min:2|max:60',
            'birthdate' => 'required|date|before_or_equal:-18 years',
            // The 10 digits after +63; stored as 09XXXXXXXXX.
            'mobile' => ['required', 'regex:/^9\d{9}$/', function ($attribute, $value, $fail) use ($driver) {
                $taken = Driver::withoutGlobalScopes()->where('mobile_no', '0'.$value)
                    ->when($driver, fn ($q) => $q->whereKeyNot($driver->id))->exists();
                if ($taken) {
                    $fail('This mobile number is already registered.');
                }
            }],
        ];
    }

    private function validatedDriver(Request $request, ?Driver $driver = null): array
    {
        $data = $request->validate(self::driverRules($driver), self::DRIVER_MESSAGES);

        return [
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'birthdate' => $data['birthdate'],
            'mobile_no' => '0'.$data['mobile'],
        ];
    }

    /**
     * The license, as the rider application asks for it (no., codes, expiry, photos). Both photos
     * are required when registering; on a renewal they replace the old ones only if uploaded.
     */
    private function licenseRules(Request $request, ?Driver $driver = null): array
    {
        $request->merge(['license_no' => strtoupper(trim((string) $request->input('license_no')))]);

        return [
            'license_no' => ['required', 'regex:/^[A-Z]\d{2}-\d{2}-\d{6}$/', Rule::unique('pr_drivers', 'license_no')->ignore($driver?->id)],
            'license_codes' => 'required|array|min:1',
            'license_codes.*' => ['string', Rule::in(Driver::LICENSE_CODES)],
            'license_expiry' => 'required|date|after:today',
            'license_front' => ($driver?->license_front_path ? 'nullable|' : 'required|').self::PHOTO,
            'license_back' => ($driver?->license_back_path ? 'nullable|' : 'required|').self::PHOTO,
        ];
    }

    private function validatedLicense(Request $request, ?Driver $driver = null): array
    {
        $data = $request->validate($this->licenseRules($request, $driver), self::LICENSE_MESSAGES);

        return [
            'license_no' => $data['license_no'],
            'license_codes' => implode(',', array_values(array_intersect(Driver::LICENSE_CODES, $data['license_codes']))),
            'license_expiry' => $data['license_expiry'],
        ];
    }

    /** @return array<string, string> the license photo columns that were uploaded */
    private function storeLicense(Request $request, Driver $driver): array
    {
        $paths = [];
        foreach (['front', 'back'] as $side) {
            if ($file = $request->file("license_{$side}")) {
                $paths["license_{$side}_path"] = $file->store("pickrush/drivers/{$driver->id}", 'local');   // private disk
            }
        }

        return $paths;
    }

    private function checkLimit(): void
    {
        $hub = $this->hub();
        $limit = Vehicle::limitFor($hub);
        if (Vehicle::where('hub_id', $hub->id)->where('is_active', true)->lockForUpdate()->count() >= $limit) {
            $kind = $hub->isStation() ? 'A station hub' : 'A province hub';
            throw new OperationRefused("{$kind} keeps at most {$limit} active ".($limit === 1 ? 'truck' : 'trucks').'. Deactivate one first.');
        }
    }

    private function managerOnly(): void
    {
        abort_unless($this->staff()->hasRole(StaffRole::HubManager), 403, 'Only the hub manager manages the fleet.');
    }
}
