<?php

namespace App\Pickrush\Services\Rider;

use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Company;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\RiderDocument;
use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Support\Actor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * A rider's application from the rider app. The rider picks one logistics company, then one of
 * its stations; the application lands there as pending and nothing is unlocked until that
 * station's hub manager approves it (RiderOnboarding::approve). A rejected rider can fix the
 * application and resubmit, to the same company or another one, which puts it back to pending.
 */
class RiderApplication
{
    /** PSGC has no province for Metro Manila; the app sends this and the region is used instead. */
    public const METRO_MANILA = 'NCR';

    private const NCR_REGION_CODE = '130000000';

    /**
     * Active stations with an active hub manager to review applications, of an approved company
     * (one company when given).
     */
    public static function acceptingStations(?int $companyId = null): Builder
    {
        // Any approved company: a rejected rider resubmitting may move to another company.
        return Hub::withoutGlobalScope('company')->stations()->where('is_active', true)
            ->when($companyId, fn ($q, $id) => $q->where('pr_hubs.company_id', $id))
            ->whereIn('pr_hubs.company_id', Company::approved()->select('id'))
            ->whereHas('staff', fn ($q) => $q->where('role', StaffRole::HubManager)->where('is_active', true));
    }

    /**
     * Approved companies with a station accepting riders near the address (the city first, else
     * the province), the default company first. A rider works for one of them only.
     *
     * @return Collection<int, Company>
     */
    public function companiesNear(?string $cityCode, ?string $provinceCode): Collection
    {
        $ids = collect([$cityCode ? $this->stationsNear($cityCode, null)['stations'] : collect(),
            $this->stationsNear(null, $provinceCode)['stations']])->flatten()->pluck('company_id')->unique();

        return Company::approved()->whereIn('id', $ids)->orderByDesc('is_default')->orderBy('name')->get();
    }

    /**
     * Stations in the rider's city or municipality; when there are none, the rest of the province.
     *
     * @return array{scope: 'city'|'province'|'none', stations: Collection<int, Hub>}
     */
    public function stationsNear(?string $cityCode, ?string $provinceCode, ?int $companyId = null): array
    {
        if ($cityCode) {
            $inCity = self::acceptingStations($companyId)->where('psgc_code', $cityCode)->orderBy('name')->get();
            if ($inCity->isNotEmpty()) {
                return ['scope' => 'city', 'stations' => $inCity];
            }
        }
        if ($provinceCode) {
            $inProvince = self::acceptingStations($companyId)
                ->when($provinceCode === self::METRO_MANILA,
                    fn ($q) => $q->where('region_code', self::NCR_REGION_CODE),
                    fn ($q) => $q->whereHas('parent', fn ($p) => $p->where('psgc_code', $provinceCode)))
                ->orderBy('name')->get();
            if ($inProvince->isNotEmpty()) {
                return ['scope' => 'province', 'stations' => $inProvince];
            }
        }

        return ['scope' => 'none', 'stations' => collect()];
    }

    /**
     * @param  array  $data  validated application fields (RiderApplicationRequest names)
     * @param  array{license_front: UploadedFile, license_back: UploadedFile, orcr: UploadedFile[]}  $files
     */
    public function apply(array $data, array $files): Rider
    {
        $limits = config('pickrush.policies.rider_limits.probation');

        return Actor::actingAs(new Actor('applicant'), fn () => DB::transaction(function () use ($data, $files, $limits) {
            $rider = Rider::create($this->attributes($data) + [
                'email' => EmailVerification::normalize($data['email']),
                'password' => $data['password'],
                'status' => 'pending',
                'shift_status' => 'off_duty',
                'cod_limit' => $limits['cod_limit'],
                'max_parcels' => $limits['max_parcels'],
                'on_probation' => true,
                'created_by' => null,
            ]);
            $this->storeDocuments($rider, $data, $files);

            return $rider;
        }));
    }

    /** A rejected rider sends a corrected application; the old documents are replaced. */
    public function resubmit(Rider $rider, array $data, array $files): Rider
    {
        if ($rider->status !== 'rejected') {
            throw new OperationRefused('Only a rejected application can be resubmitted.');
        }

        $oldFiles = $rider->documents()->pluck('file_path')->all();
        DB::transaction(function () use ($rider, $data, $files) {
            $rider->documents()->get()->each->delete();
            $rider->update($this->attributes($data) + ['status' => 'pending', 'status_reason' => null]);
            $this->storeDocuments($rider, $data, $files);
        });
        Storage::disk('local')->delete($oldFiles);

        return $rider->fresh();
    }

    private function attributes(array $data): array
    {
        $plate = strtoupper(trim($data['plate_number']));

        return [
            'station_hub_id' => (int) $data['hub_id'],
            // The station decides the company: a rider works for one company only.
            'company_id' => Hub::withoutGlobalScopes()->whereKey($data['hub_id'])->value('company_id'),
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'birthdate' => $data['birth_date'],
            'mobile_no' => self::mobileNo($data['mobile']),
            'address' => implode(', ', [trim($data['street']), $data['barangay_name'], $data['city_name'], $data['province_name']]),
            'province_code' => $data['province_code'],
            'province_name' => $data['province_name'],
            'city_code' => $data['city_code'],
            'city_name' => $data['city_name'],
            'barangay_code' => $data['barangay_code'],
            'barangay_name' => $data['barangay_name'],
            'street' => trim($data['street']),
            'vehicle_type' => $data['vehicle_type'],
            'plate_no' => $plate,
            'license_no' => strtoupper(trim($data['license_no'])),
            'applied_at' => now(),
        ];
    }

    private function storeDocuments(Rider $rider, array $data, array $files): void
    {
        $store = fn (string $type, UploadedFile $file, ?string $side = null, ?string $expiry = null) => RiderDocument::create([
            'rider_id' => $rider->id,
            'doc_type' => $type,
            'side' => $side,
            'file_path' => $file->store("pickrush/riders/{$rider->id}", 'local'),   // private disk
            'expiry_date' => $expiry,
            'uploaded_by' => null,
        ]);

        // The driver's license is the rider's government ID.
        $store('drivers_license', $files['license_front'], 'front', $data['license_expiry']);
        $store('drivers_license', $files['license_back'], 'back', $data['license_expiry']);
        foreach ($files['orcr'] as $file) {
            $store('or_cr', $file);
        }
    }

    /** The app sends the 10 digits after +63; Pickrush stores 09XXXXXXXXX. */
    public static function mobileNo(string $digits): string
    {
        return '0'.$digits;
    }
}
