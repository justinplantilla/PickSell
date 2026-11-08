<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('notifications:prune-admin {--days=90}', function () {
    $deleted = app(\App\Services\Admin\AdminNotificationInbox::class)->prune((int) $this->option('days'));
    $this->info("Deleted {$deleted} read admin notifications.");
})->purpose('Delete read admin notifications older than the retention window');

Schedule::command('notifications:prune-admin')
    ->daily()
    ->name('prune-admin-notifications')
    ->withoutOverlapping();

// Sellers count as back once vacation_ends_at passes (User::isOnVacation); this clears the flag and tells them.
Artisan::command('sellers:end-vacations', function () {
    $ended = 0;
    \App\Models\User::where('role', 'seller')->where('vacation_mode', true)
        ->whereNotNull('vacation_ends_at')->where('vacation_ends_at', '<=', now())
        ->each(function (\App\Models\User $seller) use (&$ended): void {
            app(\App\Services\SellerVacations::class)->end($seller);
            $seller->notifications()->create([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'type' => 'App\\Notifications\\SellerVacationEnded',
                'data' => json_encode([
                    'type' => 'vacation',
                    'title' => 'Vacation mode ended',
                    'message' => 'Welcome back! Your vacation ended, and buyers can order your products again.',
                ]),
            ]);
            $ended++;
        });
    $this->info("Ended {$ended} seller vacations.");
})->purpose('Turn off vacation mode for sellers whose return date has passed');

Schedule::command('sellers:end-vacations')
    ->hourly()
    ->name('end-seller-vacations')
    ->withoutOverlapping();

// A delivered parcel credits its shipment's delivery-fee split when the scan syncs; if that sync
// failed, this credits it now. Safe to repeat: each shipment is credited once.
Artisan::command('shipments:settle-delivered', function () {
    $settled = 0;
    \App\Models\OrderShipment::whereNull('earnings_posted_at')->whereNotIn('status', \App\Models\OrderShipment::VOID)
        ->each(function (\App\Models\OrderShipment $shipment) use (&$settled): void {
            $parcel = \App\Pickrush\Models\Parcel::withoutGlobalScopes()->where('source', \App\Pickrush\Bridge\PicksellBridge::SOURCE)
                ->where('external_ref', $shipment->reference)->where('current_status', 'delivered')->first();
            if ($parcel && app(\App\Services\Finance\ShipmentSettlement::class)->delivered($shipment, $parcel->delivered_at)) {
                $settled++;
            }
        });
    $this->info("Credited {$settled} delivered shipments.");
})->purpose('Credit the delivery-fee split of delivered shipments the parcel sync missed');

Schedule::command('shipments:settle-delivered')
    ->hourly()
    ->name('settle-delivered-shipments')
    ->withoutOverlapping();

Artisan::command('riders:expire-idle', function () {
    $count = app(\App\Pickrush\Services\Rider\RiderPresence::class)->expireIdle();
    $this->info("Set {$count} idle riders offline.");
})->purpose('Switch off duty the on-shift riders whose app stopped checking in');

Schedule::command('riders:expire-idle')
    ->everyMinute()
    ->name('expire-idle-riders')
    ->withoutOverlapping();

// Delivered orders the buyer didn't confirm ("Order Received") within 5 days are confirmed for them:
// the order completes, the seller's earnings are posted and the buyer can rate it.
Artisan::command('orders:auto-confirm-received', function () {
    $confirmed = app(\App\Services\Orders\OrderReceipt::class)->autoConfirmDue();
    $this->info("Confirmed {$confirmed} delivered orders.");
})->purpose('Confirm receipt of orders delivered '.\App\Services\Orders\OrderReceipt::AUTO_CONFIRM_DAYS.' days ago that the buyer did not confirm');

Schedule::command('orders:auto-confirm-received')
    ->hourly()
    ->name('auto-confirm-received-orders')
    ->withoutOverlapping();
