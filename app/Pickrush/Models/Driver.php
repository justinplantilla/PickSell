<?php

namespace App\Pickrush\Models;

use App\Pickrush\Support\Audited;
use App\Pickrush\Support\CompanyOwned;
use App\Pickrush\Support\HubOwned;
use Illuminate\Database\Eloquent\Model;

/** A truck driver Pickrush hired, registered to the hub that dispatches them. */
class Driver extends Model
{
    use Audited, CompanyOwned, HubOwned;

    /** LTO driver's license restriction codes, as offered on the form. */
    public const LICENSE_CODES = ['A', 'A1', 'B', 'B1', 'B2', 'BE', 'C', 'CE', 'D'];

    protected $table = 'pr_drivers';

    protected array $companyThrough = ['hub_id' => 'pr_hubs'];

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean', 'license_expiry' => 'date', 'birthdate' => 'date'];

    protected static function booted(): void
    {
        // The rest of Pickrush shows full_name; keep it in step with the name given (as for riders).
        static::saving(function (Driver $driver) {
            if ($driver->first_name !== null || $driver->last_name !== null) {
                $driver->full_name = trim("{$driver->first_name} {$driver->last_name}");
            }
        });
    }

    public function hub()
    {
        return $this->belongsTo(Hub::class);
    }

    public function isLicenseExpired(): bool
    {
        return $this->license_expiry->lt(today());
    }

    /** Active with a valid license: the only drivers a trip can leave with. */
    public function canDrive(): bool
    {
        return $this->is_active && ! $this->isLicenseExpired();
    }

    /** @return list<string> */
    public function licenseCodes(): array
    {
        return array_values(array_filter(explode(',', (string) $this->license_codes)));
    }
}
