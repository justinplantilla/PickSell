<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => app(\App\Services\DeliveryOfferExpiryService::class)->expireDue())
    ->everyMinute()
    ->name('expire-delivery-offers')
    ->withoutOverlapping();

Artisan::command('notifications:prune-admin {--days=90}', function () {
    $deleted = app(\App\Services\Admin\AdminNotificationInbox::class)->prune((int) $this->option('days'));
    $this->info("Deleted {$deleted} read admin notifications.");
})->purpose('Delete read admin notifications older than the retention window');

Schedule::command('notifications:prune-admin')
    ->daily()
    ->name('prune-admin-notifications')
    ->withoutOverlapping();
