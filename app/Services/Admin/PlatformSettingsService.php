<?php

namespace App\Services\Admin;

use App\Auth\Permission;
use App\Models\Announcement;
use App\Models\PlatformSetting;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/** Platform settings and announcements. Callers authorize first (settings.manage / commission.manage). */
class PlatformSettingsService
{
    public function __construct(private AuditLogger $audit) {}

    /** @param array<string, string> $settings key => new value */
    public function save(array $settings, string $action, string $permission = Permission::SETTINGS_MANAGE): void
    {
        DB::transaction(function () use ($settings, $action, $permission) {
            $changes = [];
            foreach ($settings as $key => $value) {
                $current = PlatformSetting::get($key, '');
                if ($current !== $value) {
                    // Policy texts can be long; record that they changed, not their full content.
                    $changes[$key] = in_array($key, ['terms_of_service', 'privacy_policy'], true)
                        ? ['from' => mb_strlen($current) . ' chars', 'to' => mb_strlen($value) . ' chars']
                        : ['from' => $current, 'to' => $value];
                }
                PlatformSetting::set($key, $value);
            }
            if (array_key_exists('commission_rate', $settings)) {
                Config::set('app.platform_commission_rate', (float) $settings['commission_rate']);
            }
            if ($changes) {
                $this->audit->record($action, null, $changes, [], $permission);
            }
        });
    }

    public function postAnnouncement(array $attributes): Announcement
    {
        return DB::transaction(function () use ($attributes) {
            $announcement = Announcement::create($attributes + ['active' => true]);
            $this->audit->record('announcement.posted', $announcement, [], ['title' => $announcement->title, 'audience' => $announcement->audience], Permission::SETTINGS_MANAGE);

            return $announcement;
        });
    }

    public function toggleAnnouncement(Announcement $announcement): void
    {
        DB::transaction(function () use ($announcement) {
            $values = ['active' => ! $announcement->active];
            $changes = AuditLogger::diff($announcement, $values, ['active']);
            $announcement->update($values);
            $this->audit->record('announcement.toggled', $announcement, $changes, [], Permission::SETTINGS_MANAGE);
        });
    }

    public function deleteAnnouncement(Announcement $announcement): void
    {
        DB::transaction(function () use ($announcement) {
            $this->audit->record('announcement.deleted', $announcement, [], ['title' => $announcement->title], Permission::SETTINGS_MANAGE);
            $announcement->delete();
        });
    }
}
