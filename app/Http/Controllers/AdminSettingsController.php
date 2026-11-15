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
            'platformName' => PlatformSetting::get('platform_name', 'PickSell'),
            'supportEmail' => PlatformSetting::get('support_email', 'support@picksell.ph'),
            'commissionRate' => PlatformSetting::get('commission_rate', '10'),
            'deliveryCommissionRate' => PlatformSetting::get('delivery_commission_rate', (string) config('app.delivery_commission_rate')),
            'maxFileUploadMb' => PlatformSetting::get('max_file_upload_mb', '5'),
            'logisticsClaimCap' => \App\Models\LogisticsClaim::cap(),
            'offPlatformKeywords' => PlatformSetting::get(\App\Services\OffPlatform\OffPlatformDetector::KEYWORDS_SETTING),
        ]);
    }

    public function update(
        UpdatePlatformSettingsRequest $request,
        PlatformSettingsService $settings,
        CommissionService $commission,
    ): RedirectResponse {
        $values = $request->validated();
        $returnUrl = route('admin.settings.index', ['tab' => $request->settingsTab()]);
        if (! Schema::hasTable('setting_change_logs')) {
            return redirect($returnUrl)->with('warning', 'Settings are read-only until the setting history migration is applied. Please contact the platform administrator.');
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

        // PickSell's cut of each delivery fee after the rider's pay; applies to orders placed from now on.
        if (isset($values['delivery_commission_rate'])) {
            Gate::authorize('manageFinancial', PlatformSetting::class);
            $rate = number_format((float) $values['delivery_commission_rate'], 2, '.', '');
            if ((float) $rate !== (float) config('app.delivery_commission_rate')) {
                $settings->save(['delivery_commission_rate' => $rate], 'commission.delivery_rate_changed', Permission::COMMISSION_MANAGE, $actorId);
            }
            if (! in_array('Financial settings', $saved, true)) {
                $saved[] = 'Financial settings';
            }
        }

        if (isset($values['logistics_claim_cap'])) {
            Gate::authorize('manageFinancial', PlatformSetting::class);
            $cap = number_format((float) $values['logistics_claim_cap'], 2, '.', '');
            if ((float) $cap !== \App\Models\LogisticsClaim::cap()) {
                $settings->save([\App\Models\LogisticsClaim::CAP_SETTING => $cap], 'settings.logistics_claim_cap_changed', Permission::COMMISSION_MANAGE, $actorId);
            }
            if (! in_array('Financial settings', $saved, true)) {
                $saved[] = 'Financial settings';
            }
        }

        if (array_key_exists('off_platform_keywords', $values)) {
            $settings->save([\App\Services\OffPlatform\OffPlatformDetector::KEYWORDS_SETTING => trim((string) $values['off_platform_keywords'])],
                'settings.off_platform_keywords_updated', Permission::SETTINGS_MANAGE, $actorId);
            $saved[] = 'Watched keywords';
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
            return redirect($returnUrl)->withInput()->withErrors(['settings' => 'Choose at least one setting to update.']);
        }

        return redirect($returnUrl)->with('success', implode(', ', $saved).' saved successfully.');
    }

    public function createAnnouncement(
        CreateAnnouncementRequest $request,
        PlatformSettingsService $settings,
    ): RedirectResponse {
        $settings->postAnnouncement($request->validated());

        return redirect()->route('admin.settings.index', ['tab' => 'announcements'])->with('success', 'Announcement posted successfully.');
    }

    public function toggleAnnouncement(Announcement $announcement, PlatformSettingsService $settings): RedirectResponse
    {
        Gate::authorize('update', PlatformSetting::class);
        $settings->toggleAnnouncement($announcement);

        return redirect()->route('admin.settings.index', ['tab' => 'announcements'])->with('success', 'Announcement updated.');
    }

    public function deleteAnnouncement(Announcement $announcement, PlatformSettingsService $settings): RedirectResponse
    {
        Gate::authorize('update', PlatformSetting::class);
        $settings->deleteAnnouncement($announcement);

        return redirect()->route('admin.settings.index', ['tab' => 'announcements'])->with('success', 'Announcement deleted.');
    }
}
