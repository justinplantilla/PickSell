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
                    ->whereLike('first_name', $like)
                    ->orWhereLike('last_name', $like)
                    ->orWhereLike('email', $like)
                    ->orWhereLike('business_name', $like))
                ->latest('id')->limit(10)->get()
            : collect();

        $products = $viewer->can(Permission::PRODUCTS_VIEW)
            ? Product::query()->with('seller')
                ->where(fn ($query) => $query->whereLike('name', $like)
                    ->orWhereLike('category', $like)
                    ->orWhereHas('seller', fn ($seller) => $seller->whereLike('business_name', $like)))
                ->latest('id')->limit(10)->get()
            : collect();

        $orders = $viewer->can(Permission::ORDERS_VIEW)
            ? Order::query()->with(['buyer', 'seller'])
                ->where(fn ($query) => $query
                    ->whereLike('order_number', $like)
                    ->orWhereLike('waybill_number', $like)
                    ->orWhereLike('product_name', $like)
                    ->when(ctype_digit($term), fn ($idQuery) => $idQuery->orWhere('id', (int) $term)))
                ->latest('id')->limit(10)->get()
            : collect();

        return view('admin.search', compact('term', 'users', 'products', 'orders'));
    }
}
