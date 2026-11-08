<?php

namespace App\Pickrush;

use App\Pickrush\Bridge\SyncOrderWithParcel;
use App\Pickrush\Services\Hq\PolicySettings;
use App\Pickrush\Support\Actor;
use App\Pickrush\Services\ParcelStatusChanged;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class PickrushServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        PolicySettings::load();
        Event::listen(ParcelStatusChanged::class, SyncOrderWithParcel::class);

        // Rider app API: email codes and passwords are guessable, so those are the tightest.
        RateLimiter::for('pickrush-rider-auth', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('pickrush-rider-apply', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('pickrush-rider-read', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        // Portal pages carry the signed-in staff member's company name, not one brand for everyone.
        // (Emails are left out: they name the company they are about.)
        View::composer('pickrush.*', function ($view) {
            if (str_starts_with($view->name(), 'pickrush.emails.')) {
                return;
            }
            $company = Actor::staff()?->company;
            $view->with('portalCompany', $company)->with('portalCompanyName', $company?->name ?? config('pickrush.portal_name'));
        });
    }
}
