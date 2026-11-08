<?php

namespace App\Pickrush\Services\Registration;

use App\Models\Geo\Municipality;
use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\AuditLog;
use App\Pickrush\Models\Company;
use App\Pickrush\Models\CompanyDocument;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Support\AccountContacts;
use App\Pickrush\Support\Actor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Files a logistics company's registration: the company (pending approval), the applicant as its
 * first super admin, and the two documents on the private disk. After a rejection the company
 * corrects it and resubmits; replaced documents are kept as new versions. The administrator's
 * decision comes by email.
 */
class CompanyRegistration
{
    /** @param  array<string, mixed>  $data  validated RegisterCompanyRequest fields */
    public function register(array $data, UploadedFile $validId, UploadedFile $permit): Company
    {
        return $this->withStoredFiles(function (array &$stored) use ($data, $validId, $permit) {
            $company = Company::create($this->companyFields($data) + [
                'slug' => $this->slug($data['business_name']),
                'status' => 'pending',
            ]);

            $owner = Staff::create($this->ownerFields($data) + [
                'company_id' => $company->id,
                'email' => $data['email'],
                'email_verified_at' => now(),
                'password' => $data['password'],
                'role' => StaffRole::SuperAdmin,
                'hub_id' => null,
                'is_active' => true,
            ]);
            $company->forceFill(['owner_staff_id' => $owner->id])->save();

            $this->storeDocuments($company, ['valid_id' => $validId, 'dti_permit' => $permit], $stored);
            AuditLog::record($company, 'registered', [], ['name' => $company->name, 'owner' => $owner->email], Actor::system());

            return $company;
        });
    }

    /**
     * A rejected company's corrected registration goes back to the review queue.
     *
     * @param  array<string, mixed>  $data  validated ResubmitApplicationRequest fields
     * @param  array<string, UploadedFile|null>  $documents  replacements, by type
     */
    public function resubmit(Company $company, Staff $owner, array $data, array $documents): Company
    {
        return $this->withStoredFiles(function (array &$stored) use ($company, $owner, $data, $documents) {
            $company = Company::lockForUpdate()->findOrFail($company->id);
            if (! $company->isRejected()) {
                throw new OperationRefused('Only a rejected registration can be resubmitted.');
            }

            $company->update($this->companyFields($data) + ['status' => 'pending', 'rejection_reason' => null]);
            $owner->update($this->ownerFields($data));
            $this->storeDocuments($company, array_filter($documents), $stored);
            $company->reviews()->create(['decision' => 'resubmitted']);

            return $company;
        });
    }

    private function companyFields(array $data): array
    {
        $municipality = Municipality::findOrFail($data['municipality']);

        return [
            'name' => $data['business_name'],
            'province_code' => $municipality->province_code,
            'municipality_code' => $municipality->code,
            'barangay_code' => $data['barangay'],
            'street_address' => $data['street_address'],
            'submitted_at' => now(),
            'terms_accepted_at' => now(),
            'terms_version' => config('pickrush.registration.terms_version'),
        ];
    }

    private function ownerFields(array $data): array
    {
        $middle = $data['middle_initial'] ? rtrim(strtoupper($data['middle_initial']), '.').'.' : null;

        return [
            'full_name' => collect([$data['first_name'], $middle, $data['last_name']])->filter()->join(' '),
            'first_name' => $data['first_name'],
            'middle_initial' => $middle,
            'last_name' => $data['last_name'],
            'sex' => $data['sex'],
            'birthday' => $data['birthday'],
            'position' => $data['position'],
            'mobile_no' => AccountContacts::normalizeMobile($data['contact_no']),
        ];
    }

    /** @param  array<string, UploadedFile>  $files  by document type */
    private function storeDocuments(Company $company, array $files, array &$stored): void
    {
        foreach ($files as $type => $file) {
            $path = $file->store("logistics-applications/{$company->id}", CompanyDocument::DISK);
            $stored[] = $path;
            $company->documents()->create([
                'type' => $type,
                'path' => $path,
                'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'version' => (int) $company->documents()->where('type', $type)->max('version') + 1,
            ]);
        }
    }

    /** Runs $work in a transaction; files it stored are deleted again if it fails. */
    private function withStoredFiles(callable $work): Company
    {
        $stored = [];
        try {
            return DB::transaction(function () use ($work, &$stored) {
                return $work($stored);
            });
        } catch (\Throwable $e) {
            Storage::disk(CompanyDocument::DISK)->delete($stored);
            throw $e;
        }
    }

    private function slug(string $name): string
    {
        $base = Str::slug($name) ?: 'company';
        $slug = $base;
        for ($n = 2; Company::where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }
}
