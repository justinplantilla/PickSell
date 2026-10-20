<?php

namespace App\Pickrush\Http\Controllers\Hq;

use App\Http\Controllers\Controller;
use App\Pickrush\Models\Hub;
use App\Pickrush\Services\Hq\Alerts;
use App\Pickrush\Services\Hq\NetworkMetrics;
use Illuminate\Http\Request;

/** HQ executive dashboard: volume, outcomes, speed, cash, and the hub ranking. */
class DashboardController extends Controller
{
    public const PERIODS = ['today' => 'Today', 'week' => 'Last 7 days', 'month' => 'Last 30 days'];

    public function __invoke(Request $request, NetworkMetrics $metrics, Alerts $alerts)
    {
        $period = array_key_exists($request->query('period'), self::PERIODS) ? $request->query('period') : 'week';
        [$from, $to] = self::range($period);

        return view('pickrush.hq.dashboard', [
            'period' => $period,
            'periods' => self::PERIODS,
            'summary' => $metrics->summary($from, $to),
            'ranking' => $metrics->hubRanking($from, $to),
            'cash' => $metrics->cashOutstanding(),
            'sla' => (float) config('pickrush.policies.sla_success_rate'),
            'alertCount' => collect($alerts->all())->sum(fn ($a) => $a['items']->count()),
            'network' => ['provinces' => Hub::provinces()->count(), 'stations' => Hub::stations()->count()],
        ]);
    }

    public static function range(string $period): array
    {
        return match ($period) {
            'today' => [today(), now()],
            'month' => [now()->subDays(30), now()],
            default => [now()->subDays(7), now()],
        };
    }
}
