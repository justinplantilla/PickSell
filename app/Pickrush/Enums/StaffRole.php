<?php

namespace App\Pickrush\Enums;

enum StaffRole: string
{
    case SuperAdmin = 'super_admin';
    case Finance = 'finance';
    case Support = 'support';
    case HubManager = 'hub_manager';
    case Dispatcher = 'dispatcher';
    case Sorter = 'sorter';

    /** HQ roles see the whole network and have no hub. */
    public function isCompany(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Finance, self::Support], true);
    }

    /** The hub types a role may be posted to (empty for HQ roles). */
    public function hubTypes(): array
    {
        return match ($this) {
            self::HubManager => [HubType::Province, HubType::Station],
            self::Dispatcher => [HubType::Station],
            self::Sorter => [HubType::Province],
            default => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super admin',
            self::Finance => 'Finance',
            self::Support => 'Support',
            self::HubManager => 'Hub manager',
            self::Dispatcher => 'Dispatcher',
            self::Sorter => 'Sorter',
        };
    }
}
