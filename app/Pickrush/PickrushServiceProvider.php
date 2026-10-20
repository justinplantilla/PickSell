<?php

namespace App\Pickrush;

use App\Pickrush\Bridge\SyncOrderWithParcel;
use App\Pickrush\Services\ParcelStatusChanged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class PickrushServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(ParcelStatusChanged::class, SyncOrderWithParcel::class);
    }
}
