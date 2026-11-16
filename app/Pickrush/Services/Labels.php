<?php

namespace App\Pickrush\Services;

use App\Pickrush\Bridge\PicksellBridge;
use App\Pickrush\Models\Manifest;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\Route;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/** Printable PDFs: the 4×6 parcel label (barcode + QR), the trip manifest and the driver's route sheet. */
class Labels
{
    public function parcel(Parcel $parcel): Response
    {
        $parcel->loadMissing('originHub', 'destHub');
        // A shared parcel lists every order it carries.
        $orderNumber = PicksellBridge::orderNumbers([$parcel])[$parcel->id] ?? null;

        return Pdf::loadView('pickrush.labels.parcel', ['parcel' => $parcel, 'orderNumber' => $orderNumber])
            ->setPaper([0, 0, 288, 432])        // 4 × 6 in
            ->stream("pickrush-label-{$parcel->tracking_no}.pdf");
    }

    /**
     * Several parcels' labels in one PDF, one 4×6 page each, for a seller printing a batch. Each
     * label is the same template as a single print.
     *
     * @param  iterable<Parcel>  $parcels
     */
    public function parcels(iterable $parcels): Response
    {
        $parcels = collect($parcels)->each->loadMissing('originHub', 'destHub');
        $orderNumbers = PicksellBridge::orderNumbers($parcels->all());
        $style = '';
        $pages = $parcels->map(function (Parcel $parcel) use ($orderNumbers, &$style) {
            $html = view('pickrush.labels.parcel', ['parcel' => $parcel, 'orderNumber' => $orderNumbers[$parcel->id] ?? null])->render();
            preg_match('#<style>(.*?)</style>#s', $html, $css);
            $style = $css[1] ?? $style;
            preg_match('#<body[^>]*>(.*)</body>#s', $html, $body);

            return $body[1] ?? '';
        });
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'.$style.' .page-break { page-break-after: always; }</style></head><body>'
            .$pages->implode('<div class="page-break"></div>').'</body></html>';

        return Pdf::loadHTML($html)->setPaper([0, 0, 288, 432])->stream('pickrush-labels-'.now()->format('Ymd-His').'.pdf');
    }

    public function manifest(Manifest $manifest): Response
    {
        $manifest->loadMissing('fromHub', 'toHub', 'items.parcel.originHub', 'items.parcel.destHub', 'route.stops.toHub');

        return Pdf::loadView('pickrush.labels.manifest', ['manifest' => $manifest])
            ->setPaper('a4')
            ->stream("pickrush-manifest-{$manifest->manifest_no}.pdf");
    }

    /** The driver's route sheet: every stop in order, with its trip barcode for the station to scan. */
    public function route(Route $route): Response
    {
        $route->loadMissing('hub', 'stops.toHub');

        return Pdf::loadView('pickrush.labels.route', ['route' => $route, 'legs' => RouteService::legs($route)])
            ->setPaper('a4')
            ->stream("pickrush-route-{$route->route_no}.pdf");
    }
}
