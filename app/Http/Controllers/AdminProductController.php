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
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class AdminProductController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $query = $this->filteredProducts($filters);

        return view('admin.products.index', [
            'products' => $query->paginate(15)->withQueryString(),
            ...$filters,
            'matchingCount' => (clone $query)->count(),
            'sellers' => User::where('role', 'seller')->whereHas('products')->orderBy('business_name')->get(['id', 'first_name', 'last_name', 'business_name']),
            'categories' => Product::whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    public function bulkModerate(Request $request, ProductModerationService $moderation)
    {
        $validated = $request->validate([
            'to_status' => ['required', 'in:active,archived'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'confirm' => ['accepted'],
            'matching_count' => ['required', 'integer', 'min:1'],
        ]);
        $query = $this->filteredProducts($this->filters($request));
        $currentCount = (clone $query)->count();
        if ($currentCount !== (int) $validated['matching_count']) {
            return back()->with('warning', 'The matching product count changed. Review the refreshed results before applying this bulk action.');
        }

        $processed = 0;
        $skipped = [];
        $query->reorder()->orderBy('id')->chunkById(100, function ($products) use ($validated, $moderation, &$processed, &$skipped): void {
            foreach ($products as $product) {
                $decision = Gate::inspect('moderate', [$product, $validated['to_status']]);
                if (! $decision->allowed()) {
                    $skipped[] = $product->name.': '.$decision->message();

                    continue;
                }

                try {
                    $moderation->setStatus($product, request()->user(), $validated['to_status'], $validated['reason']);
                    $processed++;
                } catch (HttpExceptionInterface $exception) {
                    if (! in_array($exception->getStatusCode(), [403, 404, 409], true)) {
                        throw $exception;
                    }
                    $skipped[] = $product->name.': '.$exception->getMessage();
                }
            }
        });

        return back()->with('success', "{$processed} product(s) updated.")
            ->with('bulkSkipped', array_slice($skipped, 0, 20))
            ->with('bulkSkippedCount', count($skipped));
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

    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->input('search', '')),
            'seller' => is_numeric($request->input('seller')) ? (int) $request->input('seller') : null,
            'category' => is_string($request->input('category')) && $request->input('category') !== '' ? $request->input('category') : null,
            'status' => in_array($request->input('filter_status', $request->input('status')), ['active', 'archived', 'admin_hold'], true) ? $request->input('filter_status', $request->input('status')) : 'all',
            'featured' => in_array($request->input('featured'), ['yes', 'no'], true) ? $request->input('featured') : 'all',
        ];
    }

    private function filteredProducts(array $filters)
    {
        $query = Product::with(['seller', 'latestStatusModeration'])->latest();
        if ($filters['search'] !== '') {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }
        if ($filters['seller']) {
            $query->where('seller_id', $filters['seller']);
        }
        if ($filters['category']) {
            $query->where('category', $filters['category']);
        }
        if ($filters['status'] === 'admin_hold') {
            $query->where('status', 'archived')->whereHas('latestStatusModeration', fn ($q) => $q->where('action', 'archived'));
        } elseif ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }
        if ($filters['featured'] !== 'all') {
            $query->where('is_featured', $filters['featured'] === 'yes');
        }

        return $query;
    }
}
