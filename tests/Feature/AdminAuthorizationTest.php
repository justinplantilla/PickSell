<?php

namespace Tests\Feature;

use App\Auth\Permission;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, ?string $email = null): User
    {
        return User::create([
            'first_name' => ucfirst($role),
            'last_name' => 'User',
            'email' => $email ?? "{$role}@example.com",
            'password' => bcrypt('password'),
            'role' => $role,
            'status' => 'approved',
            'sex' => 'Male',
            'contact_no' => '09171234567',
            'birthday' => '1990-01-01',
            'age' => 35,
            'province' => 'Metro Manila',
            'municipality' => 'Quezon City',
            'barangay' => 'Bahay',
        ]);
    }

    /** Simulate a future, narrower admin role by remapping the admin role's permissions. */
    private function limitAdminTo(array $permissions): void
    {
        config(['permissions.roles.admin' => $permissions]);
    }

    public function test_super_admin_holds_every_permission_and_other_roles_hold_none(): void
    {
        $admin = $this->user('admin');
        $this->assertTrue($admin->isSuperAdmin());

        foreach (Permission::ALL as $permission) {
            $this->assertTrue(Gate::has($permission), "Gate [{$permission}] is not defined.");
            $this->assertTrue(Gate::forUser($admin)->allows($permission), "Super Admin lacks [{$permission}].");
        }

        foreach (['buyer', 'seller', 'courier', 'logistics'] as $role) {
            $user = $this->user($role);
            $this->assertFalse($user->isSuperAdmin());
            foreach (Permission::ALL as $permission) {
                $this->assertTrue(Gate::forUser($user)->denies($permission), "{$role} unexpectedly has [{$permission}].");
            }
        }
    }

    public function test_permission_vocabulary_matches_the_specification(): void
    {
        $this->assertSame([
            'dashboard.view',
            'registrations.view', 'registrations.manage',
            'users.view', 'users.manage',
            'products.view', 'products.moderate',
            'seller-compliance.view', 'seller-compliance.manage',
            'orders.view', 'orders.manage', 'orders.override-status',
            'logistics.view', 'logistics.manage', 'logistics.scan', 'logistics.assign-rider', 'logistics.resolve-exception',
            'complaints.view', 'complaints.manage',
            'returns.view', 'returns.manage',
            'refunds.view', 'refunds.manage', 'refunds.approve',
            'commission.view', 'commission.manage', 'commission.override',
            'reports.view', 'reports.export',
            'settings.view', 'settings.manage',
            'messaging.view', 'messaging.manage',
            'audit.view', 'audit.export',
            'account.view', 'account.manage',
        ], Permission::ALL);
    }

    /** isSuperAdmin()/role may identify the role, but must never be the final check for an operation. */
    public function test_operations_are_never_authorized_by_role_checks(): void
    {
        $offenders = [];
        $paths = ['app/Http/Controllers', 'app/Http/Middleware', 'app/Policies', 'app/Services', 'routes'];
        foreach ($paths as $path) {
            $files = is_dir(base_path($path))
                ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($path)))
                : [];
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $code = file_get_contents($file->getPathname());
                if (preg_match('/isSuperAdmin\(|isAdmin\(|role\s*[!=]==?\s*[\'"]admin[\'"]/', $code)) {
                    $offenders[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
                }
            }
        }

        $this->assertSame([], $offenders, 'Authorize operations with a Permission gate or Policy, not the admin role.');
    }

    public function test_every_admin_route_declares_an_explicit_permission(): void
    {
        $unguarded = [];
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'admin/')) {
                continue;
            }
            $permissions = collect($route->gatherMiddleware())
                ->filter(fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'can:'))
                ->map(fn ($middleware) => substr($middleware, 4));

            if ($permissions->isEmpty() || $permissions->diff(Permission::ALL)->isNotEmpty()) {
                $unguarded[] = $route->methods()[0] . ' ' . $route->uri();
            }
        }

        $this->assertSame([], $unguarded, 'Admin routes without an explicit Permission gate.');
    }

    public function test_non_admins_are_redirected_out_of_the_portal(): void
    {
        $this->actingAs($this->user('seller'))->get('/admin/dashboard')->assertRedirect('/dashboard');
    }

    public function test_a_role_without_any_admin_permission_cannot_enter_the_portal(): void
    {
        $this->limitAdminTo([]);

        $this->actingAs($this->user('admin'))->get('/admin/dashboard')->assertRedirect('/dashboard');
    }

    public function test_entering_the_portal_does_not_grant_the_dashboard(): void
    {
        $this->limitAdminTo([Permission::COMPLAINTS_VIEW]);

        $this->actingAs($this->user('admin'))->get('/admin/dashboard')->assertForbidden();
        $this->actingAs($this->user('admin', 'admin2@example.com'))->get('/admin/complaints')->assertOk();
    }

    public function test_view_permission_does_not_imply_manage_permission(): void
    {
        $this->limitAdminTo([Permission::DASHBOARD_VIEW, Permission::USERS_VIEW]);
        $admin = $this->user('admin');
        $buyer = $this->user('buyer');

        $page = $this->actingAs($admin)->get('/admin/users')->assertOk()->getContent();
        $this->assertStringNotContainsString('>Suspend</button>', $page);
        $this->actingAs($admin)->patch("/admin/users/{$buyer->id}/status", ['status' => 'suspended'])->assertForbidden();
        $this->assertSame('approved', $buyer->fresh()->status);

        $this->limitAdminTo([Permission::DASHBOARD_VIEW, Permission::REPORTS_VIEW]);
        $this->actingAs($admin)->get('/admin/reports')->assertOk()->assertDontSee('/admin/reports/export', false);
        $this->actingAs($admin)->get('/admin/reports/export')->assertForbidden();
    }

    public function test_a_narrower_role_only_reaches_its_permitted_modules(): void
    {
        $this->limitAdminTo([Permission::DASHBOARD_VIEW, Permission::COMPLAINTS_VIEW, Permission::COMPLAINTS_MANAGE]);
        $admin = $this->user('admin');

        $this->actingAs($admin)->get('/admin/complaints')->assertOk();
        foreach (['/admin/users', '/admin/registrations', '/admin/products', '/admin/commission', '/admin/reports', '/admin/settings', '/admin/chat', '/admin/returns', '/admin/compliance'] as $uri) {
            $this->actingAs($admin)->get($uri)->assertForbidden();
        }
        $this->actingAs($admin)->patch("/admin/users/{$admin->id}/status", ['status' => 'suspended'])->assertForbidden();
        $this->assertSame('approved', $admin->fresh()->status);

        $sidebar = $this->actingAs($admin)->get('/admin/dashboard')->assertOk()->getContent();
        $this->assertStringContainsString('href="' . route('admin.complaints') . '"', $sidebar);
        $this->assertStringNotContainsString('href="' . route('admin.users') . '"', $sidebar);
        $this->assertStringNotContainsString('href="' . route('admin.commission') . '"', $sidebar);
        $this->assertStringNotContainsString('Pending Applications', $sidebar);
    }

    public function test_changing_commission_rate_from_settings_requires_commission_manage(): void
    {
        PlatformSetting::set('commission_rate', '10');
        config(['app.platform_commission_rate' => 10.0]);
        $this->limitAdminTo([Permission::SETTINGS_VIEW, Permission::SETTINGS_MANAGE]);
        $admin = $this->user('admin');

        $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertSee('Requires commission management permission.');

        $this->actingAs($admin)->post('/admin/settings', ['platform_name' => 'PickSell', 'commission_rate' => '15'])->assertForbidden();
        $this->assertSame('10', PlatformSetting::get('commission_rate'));

        // Disabled field is not submitted: other settings save and the rate is kept.
        $this->actingAs($admin)->post('/admin/settings', ['platform_name' => 'PickSell PH'])->assertRedirect();
        $this->assertSame('PickSell PH', PlatformSetting::get('platform_name'));
        $this->assertSame('10', PlatformSetting::get('commission_rate'));

        $this->limitAdminTo(Permission::ALL);
        $this->actingAs($admin)->post('/admin/settings', ['platform_name' => 'PickSell PH', 'commission_rate' => '15'])->assertRedirect();
        $this->assertSame('15', PlatformSetting::get('commission_rate'));
    }
}
