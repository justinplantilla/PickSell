<?php

namespace App\Pickrush\Services;

use App\Pickrush\Bridge\PicksellBridge;
use App\Pickrush\Models\Manifest;
use App\Pickrush\Models\Parcel;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/** Printable PDFs: the 4×6 parcel label (barcode + QR) and the trip manifest. */
class Labels
{
    public function parcel(Parcel $parcel): Response
    {
        $parcel->loadMissing('originHub', 'destHub');
        $order = PicksellBridge::orderFor($parcel);

        return Pdf::loadView('pickrush.labels.parcel', ['parcel' => $parcel, 'orderNumber' => $order?->order_number])
            ->setPaper([0, 0, 288, 432])        // 4 × 6 in
            ->stream("pickrush-label-{$parcel->tracking_no}.pdf");
    }

    public function manifest(Manifest $manifest): Response
    {
        $manifest->loadMissing('fromHub', 'toHub', 'items.parcel.originHub', 'items.parcel.destHub');

        return Pdf::loadView('pickrush.labels.manifest', ['manifest' => $manifest])
            ->setPaper('a4')
            ->stream("pickrush-manifest-{$manifest->manifest_no}.pdf");
    }
}
