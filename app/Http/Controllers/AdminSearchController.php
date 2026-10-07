<?php

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminSearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);
        $term = trim($validated['q']);
        $like = '%'.$term.'%';
        $viewer = $request->user();

        $users = $viewer->can(Permission::USERS_VIEW)
            ? User::query()
                ->whereIn('role', ['buyer', 'seller', 'courier', 'logistics'])
                ->where(fn ($query) => $query
                    ->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('business_name', 'like', $like))
                ->latest('id')->limit(10)->get()
            : collect();

        $products = $viewer->can(Permission::PRODUCTS_VIEW)
            ? Product::query()->with('seller')
                ->where(fn ($query) => $query->where('name', 'like', $like)
                    ->orWhere('category', 'like', $like)
                    ->orWhereHas('seller', fn ($seller) => $seller->where('business_name', 'like', $like)))
                ->latest('id')->limit(10)->get()
            : collect();

        $orders = $viewer->can(Permission::ORDERS_VIEW)
            ? Order::query()->with(['buyer', 'seller'])
                ->where(fn ($query) => $query
                    ->where('order_number', 'like', $like)
                    ->orWhere('waybill_number', 'like', $like)
                    ->orWhere('product_name', 'like', $like)
                    ->when(ctype_digit($term), fn ($idQuery) => $idQuery->orWhere('id', (int) $term)))
                ->latest('id')->limit(10)->get()
            : collect();

        return view('admin.search', compact('term', 'users', 'products', 'orders'));
    }
}
