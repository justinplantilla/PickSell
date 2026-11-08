<?php

namespace App\Http\Controllers;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Company;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\ParcelException;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\Staff;
use App\Services\Finance\LogisticsSettlement;
use Illuminate\Http\Request;

/**
 * Admin overview of the logistics companies. Each one runs its hubs, riders and exceptions from the
 * logistics portal; this page reports on them, on the money with each company, and links to the
 * registration queue.
 */
class AdminLogisticsController extends Controller
{
    public function index(Request $request, LogisticsSettlement $settlement)
    {
        $period = $request->query('period') === 'all' ? 'all' : 'month';
        $parcels = Parcel::query()->where('source', 'picksell');
        $finished = [ParcelStatus::Delivered, ParcelStatus::ReturnedToSender, ParcelStatus::Cancelled, ParcelStatus::Lost];

        $companies = Company::whereIn('status', ['approved', 'suspended'])->orderByDesc('is_default')->orderBy('name')->get()
            ->map(fn (Company $company) => [
                'model' => $company,
                'province_hubs' => Hub::ofCompany($company->id)->provinces()->where('is_active', true)->count(),
                'station_hubs' => Hub::ofCompany($company->id)->stations()->where('is_active', true)->count(),
                'staff' => Staff::ofCompany($company->id)->where('is_active', true)->where('is_sample', false)->count(),
                'riders' => Rider::ofCompany($company->id)->where('status', 'active')->count(),
            ]);

        $parcelCounts = [
            'in_transit' => (clone $parcels)->whereNotIn('current_status', array_map(fn ($s) => $s->value, $finished))->count(),
            'delivered' => (clone $parcels)->where('current_status', ParcelStatus::Delivered)->count(),
            'failed' => (clone $parcels)->whereIn('current_status', [ParcelStatus::DeliveryFailed, ParcelStatus::ReturningToSender, ParcelStatus::ReturnedToSender])->count(),
            'lost' => (clone $parcels)->where('current_status', ParcelStatus::Lost)->count(),
        ];

        $openExceptions = ParcelException::query()
            ->where('status', 'open')
            ->with(['parcel', 'hub'])
            ->latest()
            ->take(10)
            ->get();

        return view('admin.logistics.overview', [
            'companies' => $companies,
            'period' => $period,
            'money' => $settlement->byCompany($period === 'month' ? now()->startOfMonth() : null),
            'portalUrl' => 'https://'.config('pickrush.domain'),
            'registrationCounts' => Company::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'pendingCompanies' => Company::where('status', 'pending')->with('owner')->orderBy('submitted_at')->take(5)->get(),
            'parcelCounts' => $parcelCounts,
            'openExceptions' => $openExceptions,
            'openExceptionCount' => ParcelException::where('status', 'open')->count(),
        ]);
    }
}
