<?php

namespace App\Pickrush\Http\Controllers\Hq;

use App\Pickrush\Http\Controllers\PortalController;
use App\Pickrush\Models\ParcelClass;
use App\Pickrush\Services\Hq\PolicySettings;
use Illuminate\Http\Request;

/** Rates and policies (super admin edits; finance and support can read). */
class SettingsController extends PortalController
{
    public function index()
    {
        return view('pickrush.hq.settings', [
            'groups' => collect(PolicySettings::FIELDS)->map(fn ($f, $key) => (object) [
                'key' => $key, 'group' => $f[0], 'label' => $f[1], 'help' => $f[3], 'value' => PolicySettings::value($key),
                'select' => str_starts_with($f[2], 'required|in:') ? explode(',', substr($f[2], strlen('required|in:'))) : null,
            ])->groupBy('group'),
            'canEdit' => $this->staff()->role->value === 'super_admin',
        ]);
    }

    public function update(Request $request, PolicySettings $settings)
    {
        abort_unless($this->staff()->role->value === 'super_admin', 403, 'Only a super admin changes rates and policies.');
        // Field names use "__" for dots so they survive the request parser.
        $rules = collect(PolicySettings::FIELDS)->mapWithKeys(fn ($f, $key) => [str_replace('.', '__', $key) => $f[2]])->all();
        $values = collect($request->validate($rules))->mapWithKeys(fn ($v, $key) => [str_replace('__', '.', $key) => $v])->all();

        // Raising the floor above a class's pay would leave that class unpayable: fix the class first.
        $floor = (float) ($values['shipping.min_rider_pay'] ?? config('pickrush.shipping.min_rider_pay'));
        $below = ParcelClass::where('is_active', true)->where('rider_pay', '<', $floor)->pluck('code');
        if ($below->isNotEmpty()) {
            return back()->withInput()->withErrors(['shipping__min_rider_pay' => 'Raise the rider pay of '.$below->join(', ').' to at least ₱'.number_format($floor, 2).' first (Marketplace shipping).']);
        }

        $changed = $settings->save($values, $this->staff());

        return back()->with('success', $changed ? "{$changed} setting(s) changed." : 'Nothing changed.');
    }
}
