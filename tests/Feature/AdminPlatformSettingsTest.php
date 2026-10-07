<?php

namespace Tests\Feature;

use App\Auth\Permission;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\PlatformSetting;
use App\Models\SettingChangeLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminPlatformSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makeAdmin();
    }

    private function makeAdmin(): User
    {
        return User::create([
            'first_name' => 'Settings',
            'last_name' => 'Admin',
            'email' => 'settings.'.Str::random(10).'@test.local',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'approved',
            'sex' => 'Male',
            'contact_no' => '09171234567',
            'birthday' => '1990-01-01',
            'age' => 36,
            'province' => 'Metro Manila',
            'municipality' => 'Pasig',
            'barangay' => 'San Miguel',
        ]);
    }

    public function test_settings_are_grouped_and_setting_changes_record_old_and_new_values(): void
    {
        PlatformSetting::set('platform_name', 'PickSell');

        $this->actingAs($this->admin)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('General Settings')
            ->assertSee('Financial Settings')
            ->assertSee('Upload Settings')
            ->assertSee('Platform Policies')
            ->assertSee('Announcements')
            ->assertSee('Setting History');

        $this->actingAs($this->admin)->post(route('admin.settings.save'), [
            'section' => 'general',
            'platform_name' => 'PickSell Marketplace',
            'support_email' => 'help@picksell.test',
        ])->assertRedirect();

        $this->assertSame('PickSell Marketplace', PlatformSetting::get('platform_name'));
        $this->assertSame('help@picksell.test', PlatformSetting::get('support_email'));
        $this->assertDatabaseHas('setting_change_logs', [
            'setting_key' => 'platform_name',
            'old_value' => 'PickSell',
            'new_value' => 'PickSell Marketplace',
            'changed_by' => $this->admin->id,
        ]);
        $this->assertSame(2, SettingChangeLog::count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'settings.general_updated',
            'actor_id' => $this->admin->id,
            'permission' => Permission::SETTINGS_MANAGE,
        ]);
    }

    public function test_policy_changes_are_recorded_in_history_and_audit_without_copying_policy_text_to_audit(): void
    {
        $this->actingAs($this->admin)->post(route('admin.settings.save'), [
            'section' => 'policies',
            'policy' => 'Terms version one.',
            'privacy' => 'Privacy version one.',
        ])->assertRedirect();
        $this->actingAs($this->admin)->post(route('admin.settings.save'), [
            'section' => 'policies',
            'policy' => 'Terms version two.',
            'privacy' => 'Privacy version two.',
        ])->assertRedirect();

        $this->assertDatabaseHas('setting_change_logs', [
            'setting_key' => 'terms_of_service',
            'old_value' => 'Terms version one.',
            'new_value' => 'Terms version two.',
            'changed_by' => $this->admin->id,
        ]);
        $audit = AuditLog::where('action', 'settings.policies_updated')->latest('id')->firstOrFail();
        $this->assertSame('18 chars', $audit->changes['terms_of_service']['from']);
        $this->assertStringNotContainsString('Terms version one.', json_encode($audit->changes));
    }

    public function test_commission_rate_changes_are_logged_under_the_commission_module(): void
    {
        PlatformSetting::set('commission_rate', '10.00');

        $this->actingAs($this->admin)->post(route('admin.settings.save'), [
            'section' => 'financial',
            'commission_rate' => '12.5',
        ])->assertRedirect();

        $audit = AuditLog::where('action', 'commission.rate_changed')->sole();
        $this->assertSame('commission', $audit->module);
        $this->assertSame(Permission::COMMISSION_MANAGE, $audit->permission);
        $this->assertSame(['from' => '10.00', 'to' => '12.50'], $audit->changes['commission_rate']);
    }

    public function test_upload_setting_rejects_invalid_values_without_persisting_history(): void
    {
        $this->actingAs($this->admin)->post(route('admin.settings.save'), [
            'section' => 'uploads',
            'max_file_upload_mb' => 0,
        ])->assertSessionHasErrors('max_file_upload_mb');

        $this->assertSame('', PlatformSetting::get('max_file_upload_mb'));
        $this->assertDatabaseCount('setting_change_logs', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_announcement_create_toggle_and_delete_are_audited(): void
    {
        $this->actingAs($this->admin)->post(route('admin.announcements.store'), [
            'title' => 'Maintenance window',
            'message' => 'The marketplace will be unavailable briefly.',
            'audience' => 'all',
        ])->assertRedirect();

        $announcement = Announcement::sole();
        $this->assertTrue($announcement->active);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'announcement.posted',
            'subject_id' => $announcement->id,
            'actor_id' => $this->admin->id,
        ]);

        $this->patch(route('admin.announcements.toggle', $announcement))->assertRedirect();
        $this->assertFalse($announcement->fresh()->active);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'announcement.toggled',
            'subject_id' => $announcement->id,
        ]);

        $this->delete(route('admin.announcements.delete', $announcement))->assertRedirect();
        $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'announcement.deleted',
            'subject_id' => $announcement->id,
        ]);
    }

    public function test_invalid_announcement_is_rejected_without_audit_or_creation(): void
    {
        $this->actingAs($this->admin)->post(route('admin.announcements.store'), [
            'title' => str_repeat('x', 256),
            'message' => '',
            'audience' => 'administrator',
        ])->assertSessionHasErrors(['title', 'message', 'audience']);

        $this->assertDatabaseCount('announcements', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_policy_denies_settings_management_and_keeps_announcement_routes_protected(): void
    {
        config(['permissions.roles.admin' => [Permission::SETTINGS_VIEW]]);

        $this->actingAs($this->admin)->get(route('admin.settings.index'))->assertOk();
        $this->post(route('admin.settings.save'), [
            'section' => 'general',
            'platform_name' => 'Unauthorized',
        ])->assertForbidden();
        $this->post(route('admin.announcements.store'), [
            'title' => 'Unauthorized',
            'message' => 'Not allowed.',
            'audience' => 'all',
        ])->assertForbidden();

        $this->assertSame('', PlatformSetting::get('platform_name'));
        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_settings_page_remains_available_when_history_migration_is_missing(): void
    {
        Schema::shouldReceive('hasTable')
            ->once()
            ->with('setting_change_logs')
            ->andReturnFalse();

        $this->actingAs($this->admin)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Settings are temporarily read-only')
            ->assertSee('Announcements remain available')
            ->assertSee('disabled', false);
    }

    public function test_settings_update_does_not_write_when_history_migration_is_missing(): void
    {
        Schema::shouldReceive('hasTable')
            ->once()
            ->with('setting_change_logs')
            ->andReturnFalse();

        $this->actingAs($this->admin)->post(route('admin.settings.save'), [
            'section' => 'general',
            'platform_name' => 'Should not be saved',
        ])->assertRedirect()
            ->assertSessionHas('warning');

        $this->assertSame('', PlatformSetting::get('platform_name'));
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
