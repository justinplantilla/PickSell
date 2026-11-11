<?php

namespace App\Services\Logistics;

use App\Models\LogisticsClaim;
use App\Models\LogisticsIssue;
use App\Models\Order;
use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Company;
use Illuminate\Support\Facades\DB;

/**
 * How well a logistics company carried PickSell parcels over the last N days: delivered on time,
 * failed, lost and damaged rates, buyers' delivery ratings, issues and claims, with warnings when
 * a threshold is crossed. Warnings inform the admin; suspending stays a manual decision.
 */
class LogisticsScorecard
{
    /** A parcel delivered within this many days of booking counts as on time. */
    public const ON_TIME_DAYS = 5;

    public const THRESHOLDS = [
        'lost_rate' => 1.0,       // % of finished parcels
        'failed_rate' => 10.0,    // %
        'on_time_rate' => 85.0,   // % minimum
        'rating' => 3.5,          // average minimum (with at least 5 ratings)
    ];

    /** @return array<string, mixed> */
    public function for(Company $company, int $days = 30): array
    {
        $from = now()->subDays($days);
        $parcels = DB::table('pr_parcels')->where('source', 'picksell')->where('company_id', $company->id)->where('updated_at', '>=', $from);

        $delivered = (clone $parcels)->where('current_status', ParcelStatus::Delivered->value)->count();
        $onTime = (clone $parcels)->where('current_status', ParcelStatus::Delivered->value)->whereNotNull('delivered_at')->get(['created_at', 'delivered_at'])
            ->filter(fn ($p) => strtotime($p->delivered_at) - strtotime($p->created_at) <= self::ON_TIME_DAYS * 86400)->count();
        $failed = (clone $parcels)->whereIn('current_status', [ParcelStatus::ReturningToSender->value, ParcelStatus::ReturnedToSender->value])->count();
        $lost = (clone $parcels)->where('current_status', ParcelStatus::Lost->value)->count();
        $finished = $delivered + $failed + $lost;

        $damaged = LogisticsIssue::where('company_id', $company->id)->whereIn('type', ['damaged', 'damaged_item'])->where('created_at', '>=', $from)->count();
        $ratings = Order::query()->join('order_shipments', 'order_shipments.id', '=', 'orders.order_shipment_id')
            ->where('order_shipments.logistics_company_id', $company->id)->whereNotNull('orders.delivery_rating')->where('orders.updated_at', '>=', $from)
            ->selectRaw('count(*) as n, avg(orders.delivery_rating) as avg')->first();

        $pct = fn (int $part, int $whole) => $whole > 0 ? round($part / $whole * 100, 1) : null;
        $card = [
            'days' => $days,
            'finished' => $finished,
            'delivered' => $delivered,
            'on_time_rate' => $pct($onTime, $delivered),
            'failed_rate' => $pct($failed, $finished),
            'lost_rate' => $pct($lost, $finished),
            'lost' => $lost,
            'damaged' => $damaged,
            'rating' => $ratings && $ratings->n > 0 ? round((float) $ratings->avg, 2) : null,
            'ratings' => (int) ($ratings->n ?? 0),
            'open_issues' => LogisticsIssue::where('company_id', $company->id)->unresolved()->count(),
            'overdue_issues' => LogisticsIssue::where('company_id', $company->id)->overdue()->count(),
            'carrier_fault' => LogisticsIssue::where('company_id', $company->id)->where('fault', 'carrier')->where('resolved_at', '>=', $from)->count(),
            'claims' => (float) LogisticsClaim::where('company_id', $company->id)->where('status', 'charged')->where('created_at', '>=', $from)->sum('amount'),
        ];
        $card['warnings'] = $this->warnings($card);

        return $card;
    }

    /** @return list<string> */
    private function warnings(array $card): array
    {
        $t = self::THRESHOLDS;
        $warnings = [];
        if ($card['lost_rate'] !== null && $card['lost_rate'] > $t['lost_rate']) {
            $warnings[] = "Lost rate {$card['lost_rate']}% is above {$t['lost_rate']}%.";
        }
        if ($card['failed_rate'] !== null && $card['failed_rate'] > $t['failed_rate']) {
            $warnings[] = "Failed / returned rate {$card['failed_rate']}% is above {$t['failed_rate']}%.";
        }
        if ($card['on_time_rate'] !== null && $card['delivered'] >= 5 && $card['on_time_rate'] < $t['on_time_rate']) {
            $warnings[] = "Only {$card['on_time_rate']}% delivered within ".self::ON_TIME_DAYS." days (target {$t['on_time_rate']}%).";
        }
        if ($card['rating'] !== null && $card['ratings'] >= 5 && $card['rating'] < $t['rating']) {
            $warnings[] = "Buyers rate deliveries {$card['rating']} / 5 on average (below {$t['rating']}).";
        }
        if ($card['overdue_issues'] > 0) {
            $warnings[] = "{$card['overdue_issues']} issue(s) unresolved for more than ".LogisticsIssue::OVERDUE_HOURS.' hours.';
        }

        return $warnings;
    }
}
