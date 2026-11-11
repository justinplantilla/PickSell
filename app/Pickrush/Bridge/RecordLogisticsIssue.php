<?php

namespace App\Pickrush\Bridge;

use App\Pickrush\Models\ParcelException;
use App\Pickrush\Services\ParcelStatusChanged;
use App\Services\Logistics\LogisticsIssueDesk;
use Throwable;

/**
 * Feeds the admin logistics issues desk: a lost or returned PickSell parcel, and every exception
 * a company's hub raises or resolves. Never blocks the scan or the exception itself.
 */
class RecordLogisticsIssue
{
    public function __construct(private LogisticsIssueDesk $desk) {}

    public function handle(ParcelStatusChanged $changed): void
    {
        $this->safely(fn () => $this->desk->fromParcelStatus($changed->parcel, $changed->event->status));
    }

    public function exceptionCreated(ParcelException $exception): void
    {
        $this->safely(fn () => $this->desk->fromException($exception));
    }

    public function exceptionUpdated(ParcelException $exception): void
    {
        if ($exception->status === 'resolved' && $exception->wasChanged('status')) {
            $this->safely(fn () => $this->desk->exceptionResolved($exception));
        }
    }

    private function safely(callable $work): void
    {
        try {
            $work();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
