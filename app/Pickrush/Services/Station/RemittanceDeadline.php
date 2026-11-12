<?php

namespace App\Pickrush\Services\Station;

use App\Pickrush\Models\CodRemittance;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\HubRemittance;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * When a station's cash is due at HQ. Every count the rider confirmed and the station has not sent
 * on (reconciled, or disputed and waiting for the hub manager) counts, so a variance left open
 * cannot hold cash back. The oldest such count sets the deadline: the cutoff the day it was
 * counted (or the next day when counted after the cutoff), plus 1 or 6 days for the longer cycles.
 *
 * A deposit HQ disputed and the station has not answered (no top-up or contest waiting for HQ)
 * is due from the moment HQ disputed it, for its shortfall.
 *
 * Past the deadline the station is overdue; after the grace hours it cannot dispatch COD
 * deliveries until it remits or answers the dispute.
 */
class RemittanceDeadline
{
    /**
     * @return array{state: 'none'|'ok'|'overdue'|'blocked', amount: float, open_variances: int, disputes: int, since: ?Carbon, due_at: ?Carbon, block_at: ?Carbon}
     */
    public function for(Hub $station): array
    {
        $held = CodRemittance::withoutGlobalScope('hub')->where('station_hub_id', $station->id)
            ->whereIn('status', ['reconciled', 'disputed'])->whereNull('hub_remittance_id');
        $oldest = (clone $held)->min('created_at');
        $disputes = self::unansweredDisputes($station);

        $dues = collect();
        if ($oldest !== null) {
            $dues->push(self::dueFor(Carbon::parse($oldest)));
        }
        foreach ($disputes as $disputed) {
            $dues->push(($disputed->confirmed_at ?? $disputed->updated_at)->copy());
        }
        if ($dues->isEmpty()) {
            return ['state' => 'none', 'amount' => 0.0, 'open_variances' => 0, 'disputes' => 0, 'since' => null, 'due_at' => null, 'block_at' => null];
        }

        $due = $dues->sort()->first();
        $block = $due->copy()->addHours((int) config('pickrush.policies.remittance_grace_hours'));

        return [
            'state' => now()->gte($block) ? 'blocked' : (now()->gte($due) ? 'overdue' : 'ok'),
            'amount' => round((float) (clone $held)->sum('received_amount') + $disputes->sum(fn (HubRemittance $d) => $d->shortfall()), 2),
            'open_variances' => (clone $held)->where('status', 'disputed')->count(),
            'disputes' => $disputes->count(),
            'since' => $oldest !== null ? Carbon::parse($oldest) : null,
            'due_at' => $due,
            'block_at' => $block,
        ];
    }

    /**
     * Deposits HQ disputed that wait for the station: not top-ups themselves, and with no top-up
     * waiting for HQ (a contested one is back to "submitted", so it is not here).
     *
     * @return Collection<int, HubRemittance>
     */
    public static function unansweredDisputes(Hub $station): Collection
    {
        return HubRemittance::withoutGlobalScopes()->where('station_hub_id', $station->id)->where('status', 'disputed')->whereNull('settles_id')
            ->whereDoesntHave('topUps', fn ($q) => $q->where('status', 'submitted'))->oldest('confirmed_at')->get();
    }

    /** The deadline for cash counted at $counted. */
    public static function dueFor(CarbonInterface $counted): Carbon
    {
        [$hour, $minute] = array_map('intval', explode(':', (string) config('pickrush.policies.remittance_cutoff')) + [1 => 0]);
        $due = Carbon::instance($counted)->setTime($hour, $minute);
        if ($due->lte($counted)) {
            $due->addDay();
        }

        return $due->addDays(match (config('pickrush.policies.remittance_cycle')) {
            'every_two_days' => 1,
            'weekly' => 6,
            default => 0,
        });
    }
}
