<?php

namespace App\Pickrush;

use App\Pickrush\Bridge\SyncOrderWithParcel;
use App\Pickrush\Services\Hq\PolicySettings;
use App\Pickrush\Services\ParcelStatusChanged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class PickrushServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        PolicySettings::load();
        Event::listen(ParcelStatusChanged::class, SyncOrderWithParcel::class);
    }
}
