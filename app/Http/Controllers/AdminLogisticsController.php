<?php

namespace App\Http\Controllers;

use App\Models\LogisticsException;
use App\Models\User;

/**
 * Read-only admin overview of logistics companies and their exceptions.
 */
class AdminLogisticsController extends Controller
{
    public function index()
    {
        $companies = User::query()
            ->where('role', 'logistics')
            ->where('provider_type', 'company')
            ->withCount('logisticsBranches as branches_count')
            ->orderBy('business_name')
            ->orderBy('first_name')
            ->get();

        $openExceptions = LogisticsException::query()
            ->where('status', 'open')
            ->with(['order.returnRequest', 'order.delivery', 'order.logistics', 'order.destinationBranch'])
            ->latest()
            ->take(10)
            ->get();

        return view('admin.logistics.overview', [
            'companies' => $companies,
            'openExceptions' => $openExceptions,
            'openExceptionCount' => LogisticsException::where('status', 'open')->count(),
        ]);
    }
}
