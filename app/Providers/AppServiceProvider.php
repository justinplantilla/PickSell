<?php

namespace App\Providers;

use App\Auth\Permission;
use App\Models\Complaint;
use App\Models\ComplianceCase;
use App\Models\Message;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\Refund;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Policies\ComplaintPolicy;
use App\Policies\LogisticsPolicy;
use App\Policies\MessagePolicy;
use App\Policies\OrderPolicy;
use App\Policies\PlatformSettingsPolicy;
use App\Policies\ProductPolicy;
use App\Policies\RefundPolicy;
use App\Policies\RegistrationPolicy;
use App\Policies\ReturnRequestPolicy;
use App\Policies\SellerCompliancePolicy;
use App\Policies\UserPolicy;
use App\Services\Admin\AdminDashboardService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        // One Gate per explicit permission; roles map to permissions in config/permissions.php.
        foreach (Permission::ALL as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }

        // Resource + business-rule authorization for admin operations (permission checked inside each).
        Gate::policy(User::class, UserPolicy::class);
        Gate::define('viewAdminAccount', [\App\Policies\AdminAccountPolicy::class, 'view']);
        Gate::define('updateAdminAccount', [\App\Policies\AdminAccountPolicy::class, 'update']);
        Gate::define('changeAdminPassword', [\App\Policies\AdminAccountPolicy::class, 'changePassword']);
        Gate::define('deleteAdminAccount', [\App\Policies\AdminAccountPolicy::class, 'delete']);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::define('scanParcel', [LogisticsPolicy::class, 'scanParcel']);
        Gate::define('assignCourier', [LogisticsPolicy::class, 'assignCourier']);
        Gate::define('openException', [LogisticsPolicy::class, 'openException']);
        Gate::define('resolveParcelException', [LogisticsPolicy::class, 'resolveParcelException']);
        Gate::define('resolveLogisticsException', [LogisticsPolicy::class, 'resolveLogisticsException']);
        Gate::policy(Complaint::class, ComplaintPolicy::class);
        Gate::policy(ReturnRequest::class, ReturnRequestPolicy::class);
        Gate::policy(Refund::class, RefundPolicy::class);
        Gate::policy(PlatformSetting::class, PlatformSettingsPolicy::class);
        Gate::policy(Message::class, MessagePolicy::class);
        Gate::define('viewRegistration', [RegistrationPolicy::class, 'view']);
        Gate::define('approveRegistration', [RegistrationPolicy::class, 'approve']);
        Gate::define('disapproveRegistration', [RegistrationPolicy::class, 'disapprove']);
        Gate::policy(ComplianceCase::class, SellerCompliancePolicy::class);
        Gate::define('viewSellerCompliance', [SellerCompliancePolicy::class, 'viewSeller']);
        Gate::define('openComplianceCase', [SellerCompliancePolicy::class, 'openCase']);
        Gate::define('warnSeller', [SellerCompliancePolicy::class, 'warn']);
        Gate::define('suspendSeller', [SellerCompliancePolicy::class, 'suspend']);
        Gate::define('reinstateSeller', [SellerCompliancePolicy::class, 'reinstate']);

        if (Schema::hasTable('platform_settings')) {
            $commissionRate = PlatformSetting::where('key', 'commission_rate')->value('value');
            if ($commissionRate !== null) {
                config(['app.platform_commission_rate' => (float) $commissionRate]);
            }
        }

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        View::composer('admin.layout', function ($view): void {
            $viewer = auth()->user();
            $view->with('pendingApprovalCount', $viewer?->hasPermission(Permission::REGISTRATIONS_VIEW)
                ? app(AdminDashboardService::class)->pendingApprovals()
                : 0);
        });

        View::composer('seller.layout', function ($view): void {
            $seller = auth()->user();
            $pendingSellerReturnCount = $seller && $seller->role === 'seller' && Schema::hasTable('return_requests')
                ? ReturnRequest::where('seller_id', $seller->id)->where('status', 'requested')->count()
                : 0;

            $view->with('pendingSellerReturnCount', $pendingSellerReturnCount);
        });
    }
}
