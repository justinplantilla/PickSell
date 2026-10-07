<?php

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Http\Requests\CreateAnnouncementRequest;
use App\Http\Requests\UpdatePlatformSettingsRequest;
use App\Models\Announcement;
use App\Models\PlatformSetting;
use App\Models\SettingChangeLog;
use App\Services\Admin\PlatformSettingsService;
use App\Services\CommissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AdminSettingsController extends Controller
{
    public function index(): View
    {
        Gate::authorize('view', PlatformSetting::class);

        $announcements = Announcement::query()->latest()->get();
        $settingHistoryAvailable = Schema::hasTable('setting_change_logs');
        $settingChanges = $settingHistoryAvailable
            ? SettingChangeLog::query()->with('changedBy')->latest()->limit(30)->get()
            : collect();

        return view('admin.settings', [
            'announcements' => $announcements,
            'settingChanges' => $settingChanges,
            'settingHistoryAvailable' => $settingHistoryAvailable,
            'tos' => PlatformSetting::get('terms_of_service'),
            'privacy' => PlatformSetting::get('privacy_policy'),
            'platformName' => PlatformSetting::get('platform_name', 'PickSell'),
            'supportEmail' => PlatformSetting::get('support_email', 'support@picksell.ph'),
            'commissionRate' => PlatformSetting::get('commission_rate', '10'),
            'maxFileUploadMb' => PlatformSetting::get('max_file_upload_mb', '5'),
        ]);
    }

    public function update(
        UpdatePlatformSettingsRequest $request,
        PlatformSettingsService $settings,
        CommissionService $commission,
    ): RedirectResponse {
        $values = $request->validated();
        if (! Schema::hasTable('setting_change_logs')) {
            return back()->with('warning', 'Settings are read-only until the setting history migration is applied. Please contact the platform administrator.');
        }

        $actorId = $request->user()->id;
        $saved = [];

        $general = array_intersect_key($values, array_flip(['platform_name', 'support_email']));
        if ($general !== []) {
            $settings->save($general, 'settings.general_updated', Permission::SETTINGS_MANAGE, $actorId);
            $saved[] = 'General settings';
        }

        if (isset($values['commission_rate'])) {
            Gate::authorize('manageFinancial', PlatformSetting::class);
            $rate = number_format((float) $values['commission_rate'], 2, '.', '');
            if ((float) $rate !== $commission->rate()) {
                $settings->save(
                    ['commission_rate' => $rate],
                    'commission.rate_changed',
                    Permission::COMMISSION_MANAGE,
                    $actorId,
                );
            }
            $saved[] = 'Financial settings';
        }

        if (isset($values['max_file_upload_mb'])) {
            $settings->save(
                ['max_file_upload_mb' => (string) $values['max_file_upload_mb']],
                'settings.uploads_updated',
                Permission::SETTINGS_MANAGE,
                $actorId,
            );
            $saved[] = 'Upload settings';
        }

        $policies = [];
        if (array_key_exists('policy', $values)) {
            $policies['terms_of_service'] = $values['policy'] ?? '';
        }
        if (array_key_exists('privacy', $values)) {
            $policies['privacy_policy'] = $values['privacy'] ?? '';
        }
        if ($policies !== []) {
            $settings->save($policies, 'settings.policies_updated', Permission::SETTINGS_MANAGE, $actorId);
            $saved[] = 'Policies';
        }

        if ($saved === []) {
            return back()->withErrors(['settings' => 'Choose at least one setting to update.']);
        }

        return back()->with('success', implode(', ', $saved).' saved successfully.');
    }

    public function createAnnouncement(
        CreateAnnouncementRequest $request,
        PlatformSettingsService $settings,
    ): RedirectResponse {
        $settings->postAnnouncement($request->validated());

        return back()->with('success', 'Announcement posted successfully.');
    }

    public function toggleAnnouncement(Announcement $announcement, PlatformSettingsService $settings): RedirectResponse
    {
        Gate::authorize('update', PlatformSetting::class);
        $settings->toggleAnnouncement($announcement);

        return back()->with('success', 'Announcement updated.');
    }

    public function deleteAnnouncement(Announcement $announcement, PlatformSettingsService $settings): RedirectResponse
    {
        Gate::authorize('update', PlatformSetting::class);
        $settings->deleteAnnouncement($announcement);

        return back()->with('success', 'Announcement deleted.');
    }
}
