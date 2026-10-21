<?php

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Http\Requests\Admin\ModerateProductRequest;
use App\Http\Requests\Admin\ToggleFeaturedRequest;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Admin\ProductModerationService;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class AdminProductController extends Controller
{
    public const TABS = [
        'all' => 'All',
        'attention' => 'Needs attention',
        'active' => 'Active',
        'low_stock' => 'Low stock',
        'out_of_stock' => 'Out of stock',
        'archived' => 'Archived',
        'featured' => 'Featured',
    ];

    /** Products listed this recently land in "Needs attention" for a first look. */
    public const NEW_LISTING_DAYS = 3;

    public const STATUS_FILTERS = ['active' => 'Active', 'archived' => 'Archived (any)', 'admin_hold' => 'Archived by Admin'];

    public const FEATURED_FILTERS = ['yes' => 'Featured', 'no' => 'Not featured'];

    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $query = $this->filteredProducts($filters);
        $sellers = User::where('role', 'seller')->whereHas('products')->orderBy('business_name')->get(['id', 'first_name', 'last_name', 'business_name']);

        return view('admin.products.index', [
            'products' => $query->paginate(15)->withQueryString(),
            ...$filters,
            'tabs' => self::TABS,
            'tabCounts' => collect(array_keys(self::TABS))->mapWithKeys(fn ($tab) => [$tab => $this->applyTab(Product::query(), $tab)->count()]),
            'matchingCount' => (clone $query)->count(),
            'sellers' => $sellers,
            'categories' => Product::whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'chips' => $this->activeChips($request, $filters, $sellers),
            'moreFiltersCount' => count(array_filter([$filters['seller'], $filters['status'] !== 'all', $filters['featured'] !== 'all'])),
        ]);
    }

    /** Side-panel summary with quick moderation actions, loaded into the list page. */
    public function preview(Product $product)
    {
        Gate::authorize('view', $product);
        $product->load(['seller', 'images', 'latestStatusModeration']);

        return view('admin.products._preview', [
            'product' => $product,
            'history' => $product->moderationLogs()->with('admin')->take(5)->get(),
            'attention' => self::attentionReasons($product),
        ]);
    }

    /** CSV of the products matching the current filters, or of the selected `product_ids`. */
    public function export(Request $request, AuditLogger $audit): StreamedResponse
    {
        $selected = $this->selectedIds($request);
        $filters = $this->filters($request);
        $query = $selected ? Product::with('seller')->whereKey($selected) : $this->filteredProducts($filters);
        $audit->record('products.exported', null, [], array_filter([
            'product_ids' => $selected ?: null,
            'filters' => $selected ? null : array_filter([
                'tab' => $filters['tab'] !== 'all' ? $filters['tab'] : null,
                'search' => $filters['search'] ?: null,
                'seller' => $filters['seller'],
                'category' => $filters['category'],
                'status' => $filters['status'] !== 'all' ? $filters['status'] : null,
                'featured' => $filters['featured'] !== 'all' ? $filters['featured'] : null,
            ]),
        ]), Permission::REPORTS_EXPORT);

        $cell = fn (?string $value) => $value !== null && preg_match('/^[\s]*[=+\-@]/', $value) ? "'".$value : $value;

        return response()->streamDownload(function () use ($query, $cell) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'name', 'seller', 'category', 'price', 'discount_percent', 'effective_price', 'stock', 'status', 'featured', 'listed_at']);
            $query->reorder()->orderBy('id')->chunk(500, function ($products) use ($out, $cell) {
                foreach ($products as $product) {
                    fputcsv($out, [
                        $product->id,
                        $cell($product->name),
                        $cell($product->seller?->business_name ?? $product->seller?->full_name),
                        $cell($product->category),
                        number_format((float) $product->price, 2, '.', ''),
                        $product->discount,
                        number_format($product->effective_price, 2, '.', ''),
                        $product->stock,
                        $product->status,
                        $product->is_featured ? 'yes' : 'no',
                        $product->created_at?->toIso8601String(),
                    ]);
                }
            });
            fclose($out);
        }, 'products-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function bulkModerate(Request $request, ProductModerationService $moderation)
    {
        $validated = $request->validate([
            'to_status' => ['required', 'in:active,archived'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'confirm' => ['accepted'],
            'matching_count' => ['required', 'integer', 'min:1'],
        ]);

        return $this->runBulk($request, (int) $validated['matching_count'], function (Product $product) use ($validated, $moderation) {
            $decision = Gate::inspect('moderate', [$product, $validated['to_status']]);
            if (! $decision->allowed()) {
                return $decision->message();
            }
            $moderation->setStatus($product, request()->user(), $validated['to_status'], $validated['reason']);

            return null;
        });
    }

    public function bulkFeature(Request $request, ProductModerationService $moderation)
    {
        $validated = $request->validate([
            'featured' => ['required', 'in:1,0'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'confirm' => ['accepted'],
            'matching_count' => ['required', 'integer', 'min:1'],
        ]);
        $feature = $validated['featured'] === '1';
        $reason = trim((string) ($validated['reason'] ?? '')) ?: null;

        return $this->runBulk($request, (int) $validated['matching_count'], function (Product $product) use ($feature, $reason, $moderation) {
            if ((bool) $product->is_featured === $feature) {
                return $feature ? 'already featured' : 'not featured';
            }
            $decision = Gate::inspect('toggleFeatured', $product);
            if (! $decision->allowed()) {
                return $decision->message();
            }
            $moderation->toggleFeatured($product, request()->user(), $reason);

            return null;
        });
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

    /**
     * Why a product needs an admin's eye, for the row flags and the "Needs attention" tab (kept in step with applyTab()).
     *
     * @return list<string>
     */
    public static function attentionReasons(Product $product): array
    {
        if ($product->status !== 'active') {
            return [];
        }

        return array_values(array_filter([
            $product->images->isEmpty() ? 'No photo' : null,
            $product->stock <= 0 ? 'Listed with no stock' : null,
            $product->seller && $product->seller->status !== 'approved' ? 'Seller not approved' : null,
            $product->created_at?->gte(now()->subDays(self::NEW_LISTING_DAYS)) ? 'New listing' : null,
        ]));
    }

    /** Applies one row action to the selected `product_ids`, or to everything matching the filters. */
    private function runBulk(Request $request, int $expectedCount, callable $apply)
    {
        $selected = $this->selectedIds($request);
        $query = $selected ? Product::query()->whereKey($selected) : $this->filteredProducts($this->filters($request));
        if ((clone $query)->count() !== $expectedCount) {
            return back()->with('warning', 'The matching product count changed. Review the refreshed results before applying this bulk action.');
        }

        $processed = 0;
        $skipped = [];
        $query->reorder()->orderBy('id')->chunkById(100, function ($products) use ($apply, &$processed, &$skipped): void {
            foreach ($products as $product) {
                try {
                    $problem = $apply($product);
                } catch (HttpExceptionInterface $exception) {
                    if (! in_array($exception->getStatusCode(), [403, 404, 409], true)) {
                        throw $exception;
                    }
                    $problem = $exception->getMessage();
                }
                if ($problem === null) {
                    $processed++;
                } else {
                    $skipped[] = $product->name.': '.$problem;
                }
            }
        });

        return back()->with('success', "{$processed} product(s) updated.")
            ->with('bulkSkipped', array_slice($skipped, 0, 20))
            ->with('bulkSkippedCount', count($skipped));
    }

    /** Up to 500 explicitly selected product ids from the list checkboxes; empty means "all matching". */
    private function selectedIds(Request $request): array
    {
        $ids = $request->input('product_ids', []);
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }

        return collect(is_array($ids) ? $ids : [])
            ->filter(fn ($id) => is_scalar($id) && ctype_digit((string) $id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()->take(500)->values()->all();
    }

    /** @return list<array{label: string, url: string}> */
    private function activeChips(Request $request, array $filters, $sellers): array
    {
        $without = fn (string ...$keys) => route('admin.products', $request->except([...$keys, 'page']));
        $chips = [];
        if ($filters['search'] !== '') {
            $chips[] = ['label' => "Search: \u{201C}{$filters['search']}\u{201D}", 'url' => $without('search')];
        }
        if ($filters['category']) {
            $chips[] = ['label' => 'Category: '.$filters['category'], 'url' => $without('category')];
        }
        if ($filters['seller']) {
            $seller = $sellers->firstWhere('id', $filters['seller']);
            $chips[] = ['label' => 'Seller: '.($seller ? ($seller->business_name ?? $seller->full_name) : "#{$filters['seller']}"), 'url' => $without('seller')];
        }
        if ($filters['status'] !== 'all') {
            $chips[] = ['label' => 'Status: '.self::STATUS_FILTERS[$filters['status']], 'url' => $without('status', 'filter_status')];
        }
        if ($filters['featured'] !== 'all') {
            $chips[] = ['label' => self::FEATURED_FILTERS[$filters['featured']], 'url' => $without('featured')];
        }

        return $chips;
    }

    private function filters(Request $request): array
    {
        // Bulk forms carry the list filters as filter_status / featured_filter, since status and featured are their own fields there.
        $status = $request->input('filter_status', $request->input('status'));
        $featured = $request->input('featured_filter', $request->input('featured'));

        return [
            'tab' => array_key_exists((string) $request->input('tab'), self::TABS) ? $request->input('tab') : 'all',
            'search' => trim((string) $request->input('search', '')),
            'seller' => is_numeric($request->input('seller')) ? (int) $request->input('seller') : null,
            'category' => is_string($request->input('category')) && $request->input('category') !== '' ? $request->input('category') : null,
            'status' => array_key_exists((string) $status, self::STATUS_FILTERS) ? $status : 'all',
            'featured' => array_key_exists((string) $featured, self::FEATURED_FILTERS) ? $featured : 'all',
        ];
    }

    private function applyTab(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            'active' => $query->where('status', 'active'),
            'low_stock' => $query->where('status', 'active')->where('stock', '>', 0)->where('stock', '<=', Product::LOW_STOCK_THRESHOLD),
            'out_of_stock' => $query->where('status', 'active')->where('stock', '<=', 0),
            'archived' => $query->where('status', 'archived'),
            'featured' => $query->where('is_featured', true),
            'attention' => $query->where('status', 'active')->where(fn ($q) => $q
                ->doesntHave('images')
                ->orWhere('stock', '<=', 0)
                ->orWhereHas('seller', fn ($seller) => $seller->where('status', '!=', 'approved'))
                ->orWhere('created_at', '>=', now()->subDays(self::NEW_LISTING_DAYS))),
            default => $query,
        };
    }

    private function filteredProducts(array $filters): Builder
    {
        $query = $this->applyTab(Product::with(['seller', 'images', 'latestStatusModeration'])->latest(), $filters['tab']);
        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(fn ($q) => $q->whereLike('name', "%{$search}%")
                ->orWhereHas('seller', fn ($seller) => $seller->where(fn ($name) => $name
                    ->whereLike('business_name', "%{$search}%")
                    ->orWhereLike('first_name', "%{$search}%")
                    ->orWhereLike('last_name', "%{$search}%"))));
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
