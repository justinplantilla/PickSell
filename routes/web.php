<?php

use App\Auth\Permission;
use App\Http\Controllers\AdminAccountController;
use App\Http\Controllers\ProvinceHubController;
use App\Http\Controllers\StationHubController;
use App\Http\Controllers\AdminAnalyticsController;
use App\Http\Controllers\AdminAuditLogController;
use App\Http\Controllers\AdminChatController;
use App\Http\Controllers\AdminComplaintController;
use App\Http\Controllers\AdminComplianceController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminLogisticsController;
use App\Http\Controllers\AdminOrderController;
use App\Http\Controllers\AdminProductController;
use App\Http\Controllers\AdminRefundController;
use App\Http\Controllers\AdminRegistrationController;
use App\Http\Controllers\AdminReportController;
use App\Http\Controllers\AdminReturnController;
use App\Http\Controllers\AdminReturnDisputeController;
use App\Http\Controllers\AdminSearchController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BuyerController;
use App\Http\Controllers\BuyerReturnRequestController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\SellerProductImageController;
use App\Http\Controllers\RegistrationResubmissionController;
use App\Http\Controllers\SellerReturnRequestController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\BuyerMiddleware;
use App\Http\Middleware\SellerMiddleware;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::get('/', function () {
    if (! Schema::hasTable('products')) {
        return view('welcome', ['featuredProducts' => collect()]);
    }

    $featuredProducts = Product::where('status', 'active')->where('stock', '>', 0)
        ->with('seller')->latest()->take(8)->get();

    return view('welcome', compact('featuredProducts'));
});
Route::get('/shop', function (Request $request) {
    if (! Schema::hasTable('products')) {
        return view('pages.shop', [
            'products' => collect(),
            'categories' => collect(),
            'sort' => $request->get('sort', 'featured'),
        ]);
    }

    $query = Product::where('status', 'active')->where('stock', '>', 0)->whereHas('images')->with('seller');
    $search = trim((string) $request->get('q', ''));
    if ($search) {
        $query->where(function ($builder) use ($search) {
            $builder->whereLike('name', "%{$search}%")
                ->orWhereLike('category', "%{$search}%");
        });
    }
    $category = strtolower(trim((string) $request->get('cat', '')));
    $category = $category === 'home' ? 'home & living' : $category;
    if ($category) {
        $query->whereRaw('LOWER(category) = ?', [$category]);
    }
    if ($request->filled('min')) {
        $query->where('price', '>=', $request->float('min'));
    }
    if ($request->filled('max')) {
        $query->where('price', '<=', $request->float('max'));
    }
    $sort = $request->get('sort', 'featured');
    match ($sort) {
        'price_asc' => $query->orderBy('price'),
        'price_desc' => $query->orderByDesc('price'),
        default => $query->latest(),
    };
    $products = $query->paginate(30)->withQueryString();
    $categoryCounts = Product::where('status', 'active')
        ->selectRaw('LOWER(category) as category, COUNT(*) as product_count')
        ->whereNotNull('category')
        ->groupByRaw('LOWER(category)')
        ->orderBy('category')
        ->pluck('product_count', 'category');
    $categoryNames = ['Electronics', 'Fashion', 'Home & Living', 'Sports', 'Beauty', 'Food & Grocery', 'Books', 'Toys'];
    $categories = collect($categoryNames)->mapWithKeys(fn ($category) => [$category => (int) ($categoryCounts[strtolower($category)] ?? 0)]);

    return view('pages.shop', compact('products', 'categories', 'sort'));
})->name('shop');
Route::get('/about', fn () => view('pages.about'))->name('about');
Route::get('/contact', fn () => view('pages.contact'))->name('contact');
Route::get('/terms', fn () => view('pages.terms'))->name('terms');
Route::get('/privacy', fn () => view('pages.privacy'))->name('privacy');
Route::post('/contact', fn () => back()->with('success', 'Message sent!'))->name('contact.send');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/register/email-code', [AuthController::class, 'sendRegistrationCode'])->middleware('throttle:6,1')->name('register.email-code');
    Route::post('/register/email-code/verify', [AuthController::class, 'verifyRegistrationCode'])->middleware('throttle:20,1')->name('register.email-code.verify');
    Route::get('/registration/resubmit', [RegistrationResubmissionController::class, 'show'])->name('registration.resubmit');
    Route::post('/registration/resubmit', [RegistrationResubmissionController::class, 'store'])->name('registration.resubmit.store');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::delete('/account', [AuthController::class, 'deleteAccount'])->name('account.delete');
    Route::get('/buyer/dashboard', fn () => view('dashboard'))->name('buyer.dashboard');
    Route::get('/dashboard', fn () => redirect(match (auth()->user()->role) {
        'seller' => '/seller/dashboard',
        'admin' => '/admin/dashboard',
        default => '/buyer/dashboard',
    }));
});

// Buyer Routes
Route::middleware(['auth', BuyerMiddleware::class])->prefix('buyer')->group(function () {
    Route::get('/dashboard', [BuyerController::class, 'home'])->name('buyer.dashboard');
    Route::get('/shop', [BuyerController::class, 'home'])->name('buyer.home');
    Route::get('/categories', [BuyerController::class, 'categories'])->name('buyer.categories');
    Route::get('/search', [BuyerController::class, 'search'])->name('buyer.search');
    Route::get('/deals', [BuyerController::class, 'home'])->name('buyer.deals');
    Route::get('/browse', [BuyerController::class, 'browse'])->name('buyer.browse');
    Route::get('/recommended', [BuyerController::class, 'recommended'])->name('buyer.recommended');
    Route::get('/seller/{seller}', [BuyerController::class, 'sellerStorefront'])->name('buyer.seller');
    Route::get('/product/{product}', [BuyerController::class, 'productDetail'])->name('buyer.product');
    Route::post('/product/{product}/reviews', [BuyerController::class, 'submitProductReview'])->name('buyer.product.review');

    // Cart
    Route::get('/cart', [BuyerController::class, 'cart'])->name('buyer.cart');
    Route::get('/checkout', [BuyerController::class, 'checkout'])->name('buyer.checkout.page');
    Route::get('/cart/checkout', [BuyerController::class, 'checkout'])->name('buyer.checkout.start');
    Route::post('/cart/checkout', [BuyerController::class, 'placeOrder'])->name('buyer.checkout');
    Route::post('/cart/{product}', [BuyerController::class, 'addToCart'])->name('buyer.cart.add');
    Route::patch('/cart/item/{item}', [BuyerController::class, 'updateCart'])->name('buyer.cart.update');
    Route::delete('/cart/item/{item}', [BuyerController::class, 'removeFromCart'])->name('buyer.cart.remove');

    // Orders
    Route::get('/orders', [BuyerController::class, 'orders'])->name('buyer.orders');
    Route::get('/orders/{order}/tracking', [BuyerController::class, 'trackOrder'])->name('buyer.orders.tracking');
    Route::patch('/orders/{order}/cancel', [BuyerController::class, 'cancelOrder'])->name('buyer.orders.cancel');
    Route::post('/orders/{order}/returns', [BuyerReturnRequestController::class, 'store'])->name('buyer.orders.returns.store');
    Route::post('/orders/{order}/feedback', [BuyerController::class, 'submitFeedback'])->name('buyer.feedback');
    Route::get('/notifications', [BuyerController::class, 'notifications'])->name('buyer.notifications');
    Route::post('/notifications/read', [BuyerController::class, 'markNotificationsRead'])->name('buyer.notifications.read');

    // Chat
    Route::get('/chat', [BuyerController::class, 'chat'])->name('buyer.chat');
    Route::post('/chat/send', [BuyerController::class, 'sendMessage'])->name('buyer.chat.send');

    // Account
    Route::get('/account', [BuyerController::class, 'account'])->name('buyer.account');
    Route::patch('/account', [BuyerController::class, 'updateAccount'])->name('buyer.account.update');
    Route::patch('/account/password', [BuyerController::class, 'updatePassword'])->name('buyer.account.password');
});

// Seller Routes
Route::middleware(['auth', SellerMiddleware::class])->prefix('seller')->group(function () {
    Route::get('/dashboard', [SellerController::class, 'dashboard'])->name('seller.dashboard');
    Route::get('/earnings', [SellerController::class, 'earnings'])->name('seller.earnings');
    Route::get('/earnings/csv', [SellerController::class, 'earningsCsv'])->name('seller.earnings.csv');
    Route::get('/earnings/pdf', [SellerController::class, 'earningsPdf'])->name('seller.earnings.pdf');

    // Inventory
    Route::get('/inventory', [SellerController::class, 'inventory'])->name('seller.inventory');
    Route::post('/inventory', [SellerController::class, 'storeProduct'])->name('seller.inventory.store');
    Route::patch('/inventory/{product}', [SellerController::class, 'updateProduct'])->name('seller.inventory.update');
    Route::patch('/inventory/{product}/archive', [SellerController::class, 'archiveProduct'])->name('seller.inventory.archive');
    Route::post('/inventory/images', [SellerProductImageController::class, 'store'])->name('seller.inventory.images.store');
    Route::delete('/inventory/images/{token}', [SellerProductImageController::class, 'destroy'])->name('seller.inventory.images.destroy');

    // Orders
    Route::get('/orders', [SellerController::class, 'orders'])->name('seller.orders');
    Route::get('/orders/{order}', [SellerController::class, 'showOrder'])->name('seller.orders.show');
    Route::get('/orders/{order}/waybill', [SellerController::class, 'showWaybill'])->name('seller.orders.waybill');
    Route::patch('/orders/{order}/cancel', [SellerController::class, 'cancelOrder'])->name('seller.orders.cancel');
    Route::patch('/orders/{order}/pack', [SellerController::class, 'packOrder'])->name('seller.orders.pack');
    Route::patch('/orders/{order}/handover', [SellerController::class, 'handoverOrder'])->name('seller.orders.handover');
    Route::patch('/orders/{order}/confirm-delivery', [SellerController::class, 'confirmDelivery'])->name('seller.orders.confirm-delivery');

    // Returns and refunds
    Route::get('/returns/count', [SellerReturnRequestController::class, 'pendingCount'])->name('seller.returns.count');
    Route::get('/returns', [SellerReturnRequestController::class, 'index'])->name('seller.returns');
    Route::get('/returns/{returnRequest}', [SellerReturnRequestController::class, 'show'])->name('seller.returns.show');
    Route::patch('/returns/{returnRequest}/approve', [SellerReturnRequestController::class, 'approve'])->name('seller.returns.approve');
    Route::patch('/returns/{returnRequest}/reject', [SellerReturnRequestController::class, 'reject'])->name('seller.returns.reject');
    Route::patch('/returns/{returnRequest}/receive', [SellerReturnRequestController::class, 'markReceived'])->name('seller.returns.receive');
    Route::patch('/returns/{returnRequest}/tracking', [SellerReturnRequestController::class, 'updateTracking'])->name('seller.returns.tracking');
    Route::patch('/returns/{returnRequest}/refund-due', [SellerReturnRequestController::class, 'markRefundDue'])->name('seller.returns.refund-due');
    Route::patch('/returns/{returnRequest}/complete', [SellerReturnRequestController::class, 'complete'])->name('seller.returns.complete');
    Route::get('/notifications', [SellerController::class, 'notifications'])->name('seller.notifications');
    Route::post('/notifications/read', [SellerController::class, 'markNotificationsRead'])->name('seller.notifications.read');

    // Reports
    Route::get('/reports', [SellerController::class, 'reports'])->name('seller.reports');
    Route::get('/reports/csv', [SellerController::class, 'reportCsv'])->name('seller.reports.csv');
    Route::get('/reports/pdf', [SellerController::class, 'reportPdf'])->name('seller.reports.pdf');

    // Chat
    Route::get('/chat', [SellerController::class, 'chat'])->name('seller.chat');
    Route::post('/chat/send', [SellerController::class, 'sendMessage'])->name('seller.chat.send');

    // Account
    Route::get('/account', [SellerController::class, 'account'])->name('seller.account');
    Route::patch('/account', [SellerController::class, 'updateAccount'])->name('seller.account.update');
    Route::patch('/account/password', [SellerController::class, 'updatePassword'])->name('seller.account.password');
    Route::patch('/account/pickup-location', [SellerController::class, 'updatePickupLocation'])->name('seller.account.pickup-location');
});

// Admin Routes: the single Super Admin holds every permission (config/permissions.php).
// Deny by default: every route declares an explicit permission (AdminMiddleware rejects any that
// don't); resource and business rules are enforced by Policies inside the controllers.
Route::middleware(['auth', AdminMiddleware::class])->prefix('admin')->group(function () {
    $can = fn (string $permission) => 'can:'.$permission;

    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->middleware($can(Permission::DASHBOARD_VIEW))->name('admin.dashboard');
    Route::get('/search', AdminSearchController::class)->middleware($can(Permission::ADMIN_SEARCH))->name('admin.search');

    // Own notifications and account
    Route::get('/notifications', [AdminController::class, 'notificationCenter'])->middleware($can(Permission::ACCOUNT_VIEW))->name('admin.notifications');
    Route::get('/notifications/feed', [AdminController::class, 'notifications'])->middleware($can(Permission::ACCOUNT_VIEW))->name('admin.notifications.feed');
    Route::post('/notifications/read', [AdminController::class, 'markNotificationsRead'])->middleware($can(Permission::ACCOUNT_MANAGE))->name('admin.notifications.read');
    Route::post('/notifications/{notification}/read', [AdminController::class, 'markNotificationRead'])->middleware($can(Permission::ACCOUNT_MANAGE))->whereUuid('notification')->name('admin.notifications.read-one');
    Route::get('/account', [AdminAccountController::class, 'show'])->middleware($can(Permission::ACCOUNT_VIEW))->name('admin.account');
    Route::patch('/account', [AdminAccountController::class, 'update'])->middleware($can(Permission::ACCOUNT_MANAGE))->name('admin.account.update');
    Route::patch('/account/password', [AdminAccountController::class, 'changePassword'])->middleware($can(Permission::ACCOUNT_MANAGE))->name('admin.account.password');
    Route::delete('/account', [AdminAccountController::class, 'delete'])->middleware($can(Permission::ACCOUNT_MANAGE))->name('admin.account.delete');

    Route::middleware($can(Permission::ORDERS_VIEW))->group(function () use ($can) {
        Route::get('/orders',[AdminOrderController::class, 'index'])->name('admin.orders');
        Route::get('/orders/export', [AdminOrderController::class, 'export'])->middleware($can(Permission::REPORTS_EXPORT))->name('admin.orders.export');
        Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('admin.orders.show');
        Route::get('/orders/{order}/preview', [AdminOrderController::class, 'preview'])->name('admin.orders.preview');
    });
    Route::post('/orders/bulk-status', [AdminOrderController::class, 'bulkOverrideStatus'])->middleware($can(Permission::ORDERS_OVERRIDE_STATUS))->name('admin.orders.bulk-status');
    Route::patch('/orders/{order}/status', [AdminOrderController::class, 'overrideStatus'])->middleware($can(Permission::ORDERS_OVERRIDE_STATUS))->name('admin.orders.status');
    Route::patch('/orders/{order}/resolve', [AdminOrderController::class, 'resolveException'])->middleware($can(Permission::ORDERS_MANAGE))->name('admin.orders.resolve');

    Route::middleware($can(Permission::PRODUCTS_VIEW))->group(function () use ($can) {
        Route::get('/products', [AdminProductController::class, 'index'])->name('admin.products');
        Route::get('/products/export', [AdminProductController::class, 'export'])->middleware($can(Permission::REPORTS_EXPORT))->name('admin.products.export');
        Route::get('/products/{product}', [AdminProductController::class, 'show'])->name('admin.products.show');
        Route::get('/products/{product}/preview', [AdminProductController::class, 'preview'])->name('admin.products.preview');
    });
    Route::middleware($can(Permission::PRODUCTS_MODERATE))->group(function () {
        Route::post('/products/bulk-status', [AdminProductController::class, 'bulkModerate'])->name('admin.products.bulk-status');
        Route::post('/products/bulk-featured', [AdminProductController::class, 'bulkFeature'])->name('admin.products.bulk-featured');
        Route::patch('/products/{product}/featured', [AdminProductController::class, 'toggleFeatured'])->name('admin.products.featured');
        Route::patch('/products/{product}/status', [AdminProductController::class, 'moderate'])->name('admin.products.status');
    });

    Route::middleware($can(Permission::REGISTRATIONS_VIEW))->group(function () use ($can) {
        Route::get('/registrations', [AdminRegistrationController::class, 'index'])->name('admin.registrations');
        Route::get('/registrations/export', [AdminRegistrationController::class, 'export'])->middleware($can(Permission::REPORTS_EXPORT))->name('admin.registrations.export');
        Route::get('/registrations/{user}', [AdminRegistrationController::class, 'show'])->name('admin.registrations.show');
        Route::get('/registrations/{user}/preview', [AdminRegistrationController::class, 'preview'])->name('admin.registrations.preview');
    });
    Route::middleware($can(Permission::REGISTRATIONS_MANAGE))->group(function () {
        Route::post('/registrations/bulk-disapprove', [AdminRegistrationController::class, 'bulkDisapprove'])->name('admin.registrations.bulk-disapprove');
        Route::patch('/registrations/{user}/approve', [AdminRegistrationController::class, 'approve'])->name('admin.registrations.approve');
        Route::patch('/registrations/{user}/disapprove', [AdminRegistrationController::class, 'disapprove'])->name('admin.registrations.disapprove');
    });

    Route::middleware($can(Permission::USERS_VIEW))->group(function () use ($can) {
        Route::get('/users', [AdminUserController::class, 'index'])->name('admin.users');
        Route::get('/users/export', [AdminUserController::class, 'export'])->middleware($can(Permission::REPORTS_EXPORT))->name('admin.users.export');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('admin.users.show');
        Route::get('/users/{user}/preview', [AdminUserController::class, 'preview'])->name('admin.users.preview');
    });
    Route::patch('/users/{user}/status', [AdminUserController::class, 'updateStatus'])->middleware($can(Permission::USERS_MANAGE))->name('admin.users.status');
    Route::post('/users/bulk-status', [AdminUserController::class, 'bulkStatus'])->middleware($can(Permission::USERS_MANAGE))->name('admin.users.bulk-status');

    Route::middleware($can(Permission::SELLER_COMPLIANCE_VIEW))->group(function () use ($can) {
        Route::get('/compliance', [AdminComplianceController::class, 'index'])->name('admin.compliance');
        Route::get('/compliance/export', [AdminComplianceController::class, 'export'])->middleware($can(Permission::REPORTS_EXPORT))->name('admin.compliance.export');
        Route::get('/compliance/cases', [AdminComplianceController::class, 'cases'])->name('admin.compliance.cases');
        Route::get('/compliance/cases/{case}', [AdminComplianceController::class, 'showCase'])->name('admin.compliance.cases.show');
        Route::get('/compliance/sellers/{user}', [AdminComplianceController::class, 'seller'])->name('admin.compliance.seller');
        Route::get('/compliance/sellers/{user}/preview', [AdminComplianceController::class, 'sellerPreview'])->name('admin.compliance.seller.preview');
        Route::get('/compliance/evidence/{action}/{index}', [AdminComplianceController::class, 'evidence'])->whereNumber('index')->name('admin.compliance.evidence');
    });
    Route::middleware($can(Permission::SELLER_COMPLIANCE_MANAGE))->group(function () {
        Route::post('/compliance/sellers/{user}/cases', [AdminComplianceController::class, 'openCase'])->name('admin.compliance.cases.store');
        Route::post('/compliance/cases/{case}/notes', [AdminComplianceController::class, 'addNote'])->name('admin.compliance.cases.notes');
        Route::patch('/compliance/cases/{case}/resolve', [AdminComplianceController::class, 'resolveCase'])->name('admin.compliance.cases.resolve');
        Route::patch('/compliance/{user}/warn', [AdminComplianceController::class, 'warn'])->name('admin.compliance.warn');
        Route::patch('/compliance/sellers/{user}/suspend', [AdminComplianceController::class, 'suspend'])->name('admin.compliance.suspend');
        Route::patch('/compliance/sellers/{user}/reinstate', [AdminComplianceController::class, 'suspend'])->name('admin.compliance.reinstate');
    });

    Route::middleware($can(Permission::RETURNS_VIEW))->group(function () {
        Route::get('/returns', [AdminReturnController::class, 'index'])->name('admin.returns');
        Route::get('/disputes', [AdminReturnDisputeController::class, 'index'])->name('admin.disputes');
        Route::get('/returns/{returnRequest}', [AdminReturnDisputeController::class, 'show'])->name('admin.returns.show');
    });
    Route::get('/refunds', [AdminRefundController::class, 'index'])->middleware($can(Permission::REFUNDS_VIEW))->name('admin.refunds');
    Route::patch('/refunds/{refund}/approve', [AdminRefundController::class, 'approve'])->middleware($can(Permission::REFUNDS_APPROVE))->name('admin.refunds.approve');
    Route::patch('/refunds/{refund}/reject', [AdminRefundController::class, 'reject'])->middleware($can(Permission::REFUNDS_MANAGE))->name('admin.refunds.reject');
    Route::patch('/returns/{returnRequest}/resolve', [AdminReturnDisputeController::class, 'resolve'])->middleware($can(Permission::RETURNS_MANAGE))->name('admin.returns.resolve');
    Route::patch('/returns/{returnRequest}/approve', [AdminReturnController::class, 'approve'])->middleware($can(Permission::RETURNS_MANAGE))->name('admin.returns.approve');
    Route::patch('/returns/{returnRequest}/reject', [AdminReturnController::class, 'reject'])->middleware($can(Permission::RETURNS_MANAGE))->name('admin.returns.reject');
    Route::patch('/returns/{returnRequest}/inspect', [AdminReturnController::class, 'inspect'])->middleware($can(Permission::RETURNS_MANAGE))->name('admin.returns.inspect');
    Route::patch('/returns/{returnRequest}/approve-refund', [AdminReturnController::class, 'approveRefund'])->middleware($can(Permission::RETURNS_MANAGE))->name('admin.returns.approve-refund');

    Route::get('/logistics', [AdminLogisticsController::class, 'index'])->middleware($can(Permission::LOGISTICS_VIEW))->name('admin.logistics.index');

    Route::middleware($can(Permission::COMPLAINTS_VIEW))->group(function () {
        Route::get('/complaints', [AdminComplaintController::class, 'index'])->name('admin.complaints');
        Route::get('/complaints/{complaint}', [AdminComplaintController::class, 'show'])->name('admin.complaints.show');
    });
    Route::patch('/complaints/{complaint}', [AdminComplaintController::class, 'update'])->middleware($can(Permission::COMPLAINTS_MANAGE))->name('admin.complaints.update');
    Route::patch('/complaints/{complaint}/resolve', [AdminComplaintController::class, 'resolve'])->middleware($can(Permission::COMPLAINTS_MANAGE))->name('admin.complaints.resolve');

    Route::get('/commission', [AdminController::class, 'commission'])->middleware($can(Permission::COMMISSION_VIEW))->name('admin.commission');

    Route::get('/reports', [AdminReportController::class, 'index'])->middleware($can(Permission::REPORTS_VIEW))->name('admin.reports');
    Route::get('/reports/export', [AdminReportController::class, 'exportPdf'])->middleware($can(Permission::REPORTS_EXPORT))->name('admin.reports.export');
    Route::get('/analytics', [AdminAnalyticsController::class, 'index'])->middleware($can(Permission::REPORTS_VIEW))->name('admin.analytics');

    Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])->middleware($can(Permission::AUDIT_VIEW))->name('admin.audit');
    Route::get('/audit-logs/export', [AdminAuditLogController::class, 'export'])->middleware($can(Permission::AUDIT_EXPORT))->name('admin.audit.export');

    // Changing the commission rate on this form additionally requires commission.manage (checked in saveSettings).
    Route::get('/settings', [AdminSettingsController::class, 'index'])->middleware($can(Permission::SETTINGS_VIEW))->name('admin.settings.index');
    Route::middleware($can(Permission::SETTINGS_MANAGE))->group(function () {
        Route::post('/settings', [AdminSettingsController::class, 'update'])->name('admin.settings.save');
        Route::post('/settings/announcements', [AdminSettingsController::class, 'createAnnouncement'])->name('admin.announcements.store');
        Route::patch('/settings/announcements/{announcement}/toggle', [AdminSettingsController::class, 'toggleAnnouncement'])->name('admin.announcements.toggle');
        Route::delete('/settings/announcements/{announcement}', [AdminSettingsController::class, 'deleteAnnouncement'])->name('admin.announcements.delete');
    });

    Route::get('/chat', [AdminChatController::class, 'index'])->middleware($can(Permission::MESSAGING_VIEW))->name('admin.chat');
    Route::post('/chat/send', [AdminChatController::class, 'send'])->middleware($can(Permission::MESSAGING_MANAGE))->name('admin.chat.send');
});
