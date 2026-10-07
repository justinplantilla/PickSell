<?php

use App\Auth\Permission;
use App\Http\Controllers\AdminAnalyticsController;
use App\Http\Controllers\AdminAuditLogController;
use App\Http\Controllers\AdminAccountController;
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
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\AdminSortingCenterController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BuyerController;
use App\Http\Controllers\BuyerReturnRequestController;
use App\Http\Controllers\CourierController;
use App\Http\Controllers\LogisticsController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\SellerProductImageController;
use App\Http\Controllers\SellerReturnRequestController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\BuyerMiddleware;
use App\Http\Middleware\CourierMiddleware;
use App\Http\Middleware\LogisticsMiddleware;
use App\Http\Middleware\SellerMiddleware;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::get('/', function () {
    if (request()->getHost() === 'logistics.pick-sell.shop') {
        return redirect()->route('logistics.dashboard');
    }

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
            $builder->where('name', 'like', "%{$search}%")
                ->orWhere('category', 'like', "%{$search}%");
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
Route::post('/contact', fn () => back()->with('success', 'Message sent!'))->name('contact.send');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::delete('/account', [AuthController::class, 'deleteAccount'])->name('account.delete');
    Route::get('/buyer/dashboard', fn () => view('dashboard'))->name('buyer.dashboard');
    Route::get('/courier/dashboard', fn () => redirect('/courier/orders'))->name('courier.dashboard');
    Route::get('/dashboard', fn () => redirect(match (auth()->user()->role) {
        'seller' => '/seller/dashboard',
        'courier' => '/courier/dashboard',
        'admin' => '/admin/dashboard',
        'logistics' => '/logistics/dashboard',
        default => '/buyer/dashboard',
    }));
});

// Logistics / Sorting Center Staff Routes
Route::middleware(['auth', LogisticsMiddleware::class])->prefix('logistics')->group(function () {
    Route::get('/dashboard', [LogisticsController::class, 'dashboard'])->name('logistics.dashboard');
    Route::get('/branches', [LogisticsController::class, 'branches'])->name('logistics.branches');
    Route::post('/branches', [LogisticsController::class, 'storeBranch'])->name('logistics.branches.store');
    Route::get('/branches/{branch}/edit', [LogisticsController::class, 'editBranch'])->name('logistics.branches.edit');
    Route::put('/branches/{branch}', [LogisticsController::class, 'updateBranch'])->name('logistics.branches.update');
    Route::get('/coverage', [LogisticsController::class, 'module'])->defaults('module', 'coverage')->name('logistics.coverage');
    Route::get('/assignments', [LogisticsController::class, 'module'])->defaults('module', 'assignments')->name('logistics.assignments');
    Route::get('/applications', [LogisticsController::class, 'applications'])->name('logistics.applications');
    Route::patch('/applications/{user}/approve', [LogisticsController::class, 'approveCourier'])->name('logistics.applications.approve');
    Route::patch('/applications/{user}/disapprove', [LogisticsController::class, 'disapproveCourier'])->name('logistics.applications.disapprove');
    Route::patch('/riders/{user}/status', [LogisticsController::class, 'updateRiderStatus'])->name('logistics.riders.status');
    Route::get('/parcels', [LogisticsController::class, 'parcels'])->name('logistics.parcels');
    Route::patch('/parcels/{order}/approve-pickup', [LogisticsController::class, 'approvePickup'])->name('logistics.parcels.approve-pickup');
    Route::patch('/parcels/{order}/scan', [LogisticsController::class, 'scanParcel'])->name('logistics.parcels.scan');
    Route::patch('/parcels/{order}/sort', [LogisticsController::class, 'sortParcel'])->name('logistics.parcels.sort');
    Route::patch('/parcels/{order}/assign', [LogisticsController::class, 'assignCourier'])->name('logistics.parcels.assign');
    Route::post('/parcels/{order}/exceptions', [LogisticsController::class, 'openException'])->name('logistics.parcels.exceptions.store');
    Route::patch('/exceptions/{exception}/resolve', [LogisticsController::class, 'resolveException'])->name('logistics.exceptions.resolve');
    Route::get('/tracking', [LogisticsController::class, 'module'])->defaults('module', 'tracking')->name('logistics.tracking');
    Route::get('/riders', [LogisticsController::class, 'module'])->defaults('module', 'riders')->name('logistics.riders');
    Route::get('/reports/deliveries', [LogisticsController::class, 'deliveryReports'])->name('logistics.reports.deliveries');
    Route::get('/reports/deliveries/export', [LogisticsController::class, 'exportDeliveryReports'])->name('logistics.reports.deliveries.export');
    Route::get('/reports/branches', [LogisticsController::class, 'module'])->defaults('module', 'branch-reports')->name('logistics.reports.branches');
    Route::get('/complaints', [LogisticsController::class, 'module'])->defaults('module', 'complaints')->name('logistics.complaints');
    Route::get('/messages', [LogisticsController::class, 'messages'])->name('logistics.messages');
    Route::post('/messages/send', [LogisticsController::class, 'sendMessage'])->name('logistics.messages.send');
    Route::get('/account', [LogisticsController::class, 'account'])->name('logistics.account');
    Route::patch('/account', [LogisticsController::class, 'updateAccount'])->name('logistics.account.update');
    Route::patch('/account/password', [LogisticsController::class, 'updatePassword'])->name('logistics.account.password');
});

// Courier / Rider Routes
Route::middleware(['auth', CourierMiddleware::class])->prefix('courier')->group(function () {
    Route::get('/orders', [CourierController::class, 'dashboard'])->name('courier.orders');
    Route::patch('/orders/{order}/status', [CourierController::class, 'updateStatus'])->name('courier.orders.status');
    Route::get('/reports', [CourierController::class, 'reports'])->name('courier.reports');
    Route::get('/chat', [CourierController::class, 'chat'])->name('courier.chat');
    Route::post('/chat/send', [CourierController::class, 'sendMessage'])->name('courier.chat.send');
    Route::get('/account', [CourierController::class, 'account'])->name('courier.account');
    Route::patch('/account', [CourierController::class, 'updateAccount'])->name('courier.account.update');
    Route::patch('/account/password', [CourierController::class, 'updatePassword'])->name('courier.account.password');
});

// Buyer Routes
Route::middleware(['auth', BuyerMiddleware::class])->prefix('buyer')->group(function () {
    Route::get('/dashboard', [BuyerController::class, 'home'])->name('buyer.dashboard');
    Route::get('/shop', [BuyerController::class, 'home'])->name('buyer.home');
    Route::get('/deals', [BuyerController::class, 'home'])->name('buyer.deals');
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
});

// Admin Routes: the single Super Admin holds every permission (config/permissions.php).
// Deny by default: every route declares an explicit permission (AdminMiddleware rejects any that
// don't); resource and business rules are enforced by Policies inside the controllers.
Route::middleware(['auth', AdminMiddleware::class])->prefix('admin')->group(function () {
    $can = fn (string $permission) => 'can:'.$permission;

    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->middleware($can(Permission::DASHBOARD_VIEW))->name('admin.dashboard');

    // Own notifications and account
    Route::get('/notifications', [AdminController::class, 'notificationCenter'])->middleware($can(Permission::ACCOUNT_VIEW))->name('admin.notifications');
    Route::get('/notifications/feed', [AdminController::class, 'notifications'])->middleware($can(Permission::ACCOUNT_VIEW))->name('admin.notifications.feed');
    Route::post('/notifications/read', [AdminController::class, 'markNotificationsRead'])->middleware($can(Permission::ACCOUNT_MANAGE))->name('admin.notifications.read');
    Route::post('/notifications/{notification}/read', [AdminController::class, 'markNotificationRead'])->middleware($can(Permission::ACCOUNT_MANAGE))->whereUuid('notification')->name('admin.notifications.read-one');
    Route::get('/account', [AdminAccountController::class, 'show'])->middleware($can(Permission::ACCOUNT_VIEW))->name('admin.account');
    Route::patch('/account', [AdminAccountController::class, 'update'])->middleware($can(Permission::ACCOUNT_MANAGE))->name('admin.account.update');
    Route::patch('/account/password', [AdminAccountController::class, 'changePassword'])->middleware($can(Permission::ACCOUNT_MANAGE))->name('admin.account.password');
    Route::delete('/account', [AdminAccountController::class, 'delete'])->middleware($can(Permission::ACCOUNT_MANAGE))->name('admin.account.delete');

    Route::middleware($can(Permission::ORDERS_VIEW))->group(function () {
        Route::get('/orders', [AdminOrderController::class, 'index'])->name('admin.orders');
        Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('admin.orders.show');
    });
    Route::patch('/orders/{order}/status', [AdminOrderController::class, 'overrideStatus'])->middleware($can(Permission::ORDERS_OVERRIDE_STATUS))->name('admin.orders.status');
    Route::patch('/orders/{order}/resolve', [AdminOrderController::class, 'resolveException'])->middleware($can(Permission::ORDERS_MANAGE))->name('admin.orders.resolve');

    Route::middleware($can(Permission::PRODUCTS_VIEW))->group(function () {
        Route::get('/products', [AdminProductController::class, 'index'])->name('admin.products');
        Route::get('/products/{product}', [AdminProductController::class, 'show'])->name('admin.products.show');
    });
    Route::middleware($can(Permission::PRODUCTS_MODERATE))->group(function () {
        Route::patch('/products/{product}/featured', [AdminProductController::class, 'toggleFeatured'])->name('admin.products.featured');
        Route::patch('/products/{product}/status', [AdminProductController::class, 'moderate'])->name('admin.products.status');
    });

    Route::middleware($can(Permission::REGISTRATIONS_VIEW))->group(function () {
        Route::get('/registrations', [AdminRegistrationController::class, 'index'])->name('admin.registrations');
        Route::get('/registrations/{user}', [AdminRegistrationController::class, 'show'])->name('admin.registrations.show');
    });
    Route::middleware($can(Permission::REGISTRATIONS_MANAGE))->group(function () {
        Route::patch('/registrations/{user}/approve', [AdminRegistrationController::class, 'approve'])->name('admin.registrations.approve');
        Route::patch('/registrations/{user}/disapprove', [AdminRegistrationController::class, 'disapprove'])->name('admin.registrations.disapprove');
    });

    Route::middleware($can(Permission::USERS_VIEW))->group(function () {
        Route::get('/users', [AdminUserController::class, 'index'])->name('admin.users');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('admin.users.show');
    });
    Route::patch('/users/{user}/status', [AdminUserController::class, 'updateStatus'])->middleware($can(Permission::USERS_MANAGE))->name('admin.users.status');

    Route::middleware($can(Permission::SELLER_COMPLIANCE_VIEW))->group(function () {
        Route::get('/compliance', [AdminComplianceController::class, 'index'])->name('admin.compliance');
        Route::get('/compliance/cases', [AdminComplianceController::class, 'cases'])->name('admin.compliance.cases');
        Route::get('/compliance/cases/{case}', [AdminComplianceController::class, 'showCase'])->name('admin.compliance.cases.show');
        Route::get('/compliance/sellers/{user}', [AdminComplianceController::class, 'seller'])->name('admin.compliance.seller');
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

    Route::middleware($can(Permission::COMPLAINTS_VIEW))->group(function () {
        Route::get('/complaints', [AdminComplaintController::class, 'index'])->name('admin.complaints');
        Route::get('/complaints/{complaint}', [AdminComplaintController::class, 'show'])->name('admin.complaints.show');
    });
    Route::patch('/complaints/{complaint}', [AdminComplaintController::class, 'update'])->middleware($can(Permission::COMPLAINTS_MANAGE))->name('admin.complaints.update');
    Route::patch('/complaints/{complaint}/resolve', [AdminComplaintController::class, 'resolve'])->middleware($can(Permission::COMPLAINTS_MANAGE))->name('admin.complaints.resolve');

    Route::middleware($can(Permission::LOGISTICS_VIEW))->group(function () {
        Route::get('/logistics', [AdminLogisticsController::class, 'index'])->name('admin.logistics.index');
        Route::get('/logistics/sorting-center', [AdminLogisticsController::class, 'sorting'])->name('admin.logistics.sorting');
        Route::get('/logistics/rider-assignment', [AdminLogisticsController::class, 'riders'])->name('admin.logistics.riders');
    });
    Route::post('/logistics/{order}/scan', [AdminSortingCenterController::class, 'scan'])->middleware($can(Permission::LOGISTICS_SCAN))->name('admin.logistics.scan');
    Route::post('/logistics/{order}/assign', [AdminSortingCenterController::class, 'assign'])->middleware($can(Permission::LOGISTICS_ASSIGN_RIDER))->name('admin.logistics.assign');
    Route::post('/logistics/{order}/resolve-exception', [AdminSortingCenterController::class, 'resolveException'])->middleware($can(Permission::LOGISTICS_RESOLVE_EXCEPTION))->name('admin.logistics.resolve-exception');
    Route::post('/logistics/{order}/exceptions', [AdminSortingCenterController::class, 'openException'])->middleware($can(Permission::LOGISTICS_MANAGE))->name('admin.logistics.exceptions.store');
    Route::patch('/logistics/exceptions/{exception}/resolve', [AdminSortingCenterController::class, 'resolveParcelException'])->middleware($can(Permission::LOGISTICS_RESOLVE_EXCEPTION))->name('admin.logistics.exceptions.resolve');

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
