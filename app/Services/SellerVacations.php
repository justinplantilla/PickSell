<?php

namespace App\Services;

use App\Models\SellerVacation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Seller vacations, limited to User::MAX_VACATION_DAYS_PER_MONTH days per calendar month. Each day a
 * vacation covers counts against the month it falls in, so one that crosses into the next month uses
 * that month's days too.
 */
class SellerVacations
{
    /** The vacation in progress, if any (users.vacation_* mirrors it). */
    public function current(User $seller): ?SellerVacation
    {
        return $seller->isOnVacation()
            ? SellerVacation::where('seller_id', $seller->id)->whereNull('ended_at')->latest('starts_at')->first()
            : null;
    }

    public function daysUsed(User $seller, CarbonInterface $month, ?SellerVacation $except = null): int
    {
        return SellerVacation::where('seller_id', $seller->id)
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->where('starts_at', '<=', $month->copy()->endOfMonth())
            ->where('ends_at', '>', $month->copy()->startOfMonth())
            ->get()
            ->sum(fn (SellerVacation $vacation) => $vacation->daysIn($month));
    }

    public function daysLeft(User $seller, CarbonInterface $month): int
    {
        return max(0, User::MAX_VACATION_DAYS_PER_MONTH - $this->daysUsed($seller, $month));
    }

    /**
     * The latest return date a vacation starting at $start may have: it walks forward a day at a time while
     * each day still fits its month's allowance. Null when not even one day is left.
     */
    public function latestReturn(User $seller, CarbonInterface $start, ?SellerVacation $except = null): ?CarbonInterface
    {
        $day = $start->copy()->startOfDay();
        $used = [];
        while (true) {
            $key = $day->format('Y-m');
            $used[$key] ??= $this->daysUsed($seller, $day, $except);
            if ($used[$key] >= User::MAX_VACATION_DAYS_PER_MONTH) {
                break;
            }
            $used[$key]++;
            $day->addDay();
        }

        return $day->isSameDay($start) ? null : $day;
    }

    public function start(User $seller, CarbonInterface $returnDate, ?string $message): void
    {
        DB::transaction(function () use ($seller, $returnDate, $message): void {
            $this->closeOpen($seller); // a vacation that ran out but was not cleared yet
            SellerVacation::create(['seller_id' => $seller->id, 'starts_at' => now(), 'ends_at' => $returnDate]);
            $seller->update(['vacation_mode' => true, 'vacation_started_at' => now(), 'vacation_ends_at' => $returnDate, 'vacation_message' => $message]);
        });
    }

    /** A new return date or message mid-vacation; the start stays, so buyers already answered are not answered again. */
    public function change(User $seller, SellerVacation $vacation, CarbonInterface $returnDate, ?string $message): void
    {
        DB::transaction(function () use ($seller, $vacation, $returnDate, $message): void {
            $vacation->update(['ends_at' => $returnDate]);
            $seller->update(['vacation_ends_at' => $returnDate, 'vacation_message' => $message]);
        });
    }

    /** Back early (or on the return date, from the clean-up command): only the days actually away stay used. */
    public function end(User $seller): void
    {
        DB::transaction(function () use ($seller): void {
            $this->closeOpen($seller);
            $seller->update(['vacation_mode' => false, 'vacation_started_at' => null, 'vacation_ends_at' => null]);
        });
    }

    private function closeOpen(User $seller): void
    {
        SellerVacation::where('seller_id', $seller->id)->whereNull('ended_at')->get()
            ->each(fn (SellerVacation $vacation) => $vacation->update(['ended_at' => $vacation->ends_at->isFuture() ? now() : $vacation->ends_at]));
    }
}
