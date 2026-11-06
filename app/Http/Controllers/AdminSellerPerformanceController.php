<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SellerPerformance;
use Illuminate\Http\Request;

/** Every approved seller's track record, sellers needing a warning first, then Preferred Sellers. */
class AdminSellerPerformanceController extends Controller
{
    public function __invoke(Request $request, SellerPerformance $performance)
    {
        $filter = in_array($request->query('filter'), ['warnings', 'preferred'], true) ? $request->query('filter') : 'all';
        $rows = User::where('role', 'seller')->where('status', 'approved')->orderBy('business_name')->get()
            ->map(fn (User $seller) => ['seller' => $seller] + $performance->for($seller))
            ->filter(fn ($row) => match ($filter) {
                'warnings' => $row['warnings'] !== [],
                'preferred' => $row['preferred'],
                default => true,
            })
            ->sortByDesc(fn ($row) => [count($row['warnings']), $row['preferred'], $row['completed']])->values();

        return view('admin.seller-performance', compact('rows', 'filter'));
    }
}
