<?php

namespace App\Pickrush\Support;

use Illuminate\Http\Request;

final class PickrushHost
{
    public static function matches(Request $request): bool
    {
        return strcasecmp($request->getHost(), (string) config('pickrush.domain')) === 0;
    }
}
