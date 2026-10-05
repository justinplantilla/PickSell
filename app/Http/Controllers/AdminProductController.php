<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\ModerateProductRequest;
use App\Http\Requests\Admin\ToggleFeaturedRequest;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Admin\ProductModerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminProductController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $seller = is_numeric($request->query('seller')) ? (int) $request->query('seller') : null;
        $category = is_string($request->query('category')) && $request->query('category') !== '' ? $request->query('category') : null;
        $status = in_array($request->query('status'), ['active', 'archived', 'admin_hold'], true) ? $request->query('status') : 'all';
        $featured = in_array($request->query('featured'), ['yes', 'no'], true) ? $request->query('featured') : 'all';

        $query = Product::with(['seller', 'latestStatusModeration'])->latest();
        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }
        if ($seller) {
            $query->where('seller_id', $seller);
        }
        if ($category) {
            $query->where('category', $category);
        }
        if ($status === 'admin_hold') {
            $query->where('status', 'archived')->whereHas('latestStatusModeration', fn ($q) => $q->where('action', 'archived'));
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }
        if ($featured !== 'all') {
            $query->where('is_featured', $featured === 'yes');
        }

        return view('admin.products.index', [
            'products' => $query->paginate(15)->withQueryString(),
            'search' => $search,
            'seller' => $seller,
            'category' => $category,
            'status' => $status,
            'featured' => $featured,
            'sellers' => User::where('role', 'seller')->whereHas('products')->orderBy('business_name')->get(['id', 'first_name', 'last_name', 'business_name']),
            'categories' => Product::whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    public function show(Product $product)
    {
        Gate::authorize('view', $product);
        $product->load(['seller', 'latestStatusModeration']);

        return view('admin.products.show', [
            'product' => $product,
            'history' => $product->moderationLogs()->with('admin')->get(),
            'lowRatedReviews' => $product->reviews()->with('buyer')->where('rating', '<=', 2)->latest()->take(5)->get(),
            'reviewStats' => ['count' => $product->reviews()->count(), 'average' => round((float) $product->reviews()->avg('rating'), 1)],
            'openOrders' => Order::where('product_id', $product->id)
                ->whereNotIn('status', ['completed', 'cancelled', 'returned', 'delivery_failed'])->count(),
            'auditHistory' => AuditLog::with('actor')->where('subject_type', $product->getMorphClass())
                ->where('subject_id', $product->id)->latest('id')->take(30)->get(),
            'categoryMatches' => strcasecmp((string) $product->category, (string) $product->seller?->line_of_business) === 0,
        ]);
    }

    public function moderate(ModerateProductRequest $request, Product $product, ProductModerationService $moderation)
    {
        $status = $request->validated('status');
        $moderation->setStatus($product, $request->user(), $status, $request->validated('reason'));

        return back()->with('success', $status === 'archived'
            ? "{$product->name} archived. It is hidden from buyers and the seller has been notified."
            : "{$product->name} restored. The seller has been notified.");
    }

    public function toggleFeatured(ToggleFeaturedRequest $request, Product $product, ProductModerationService $moderation)
    {
        $moderation->toggleFeatured($product, $request->user(), $request->validated('reason'));

        return back()->with('success', $product->fresh()->is_featured
            ? "{$product->name} added to Featured Products."
            : "{$product->name} removed from Featured Products.");
    }
}
