@php
    $routeName = request()->route()?->getName() ?? '';
    $descriptions = [
        'dashboard' => 'Review the latest activity and important updates for your account.',
        'inventory' => 'Manage product listings, stock levels, and inventory details.',
        'products' => 'Browse product information and manage product listings.',
        'product' => 'Review product details and available purchase options.',
        'seller' => "Review this seller's storefront and available products.",
        'deals' => 'Explore current offers and discounted products.',
        'feedback' => 'Share feedback about your experience with PickSell.',
        'orders' => 'Review order details and manage order progress.',
        'returns' => 'Review return requests and manage their resolution.',
        'reports' => 'Review performance data and generate reports.',
        'earnings' => 'Review your earnings, payouts, and transaction details.',
        'analytics' => 'Explore platform activity and performance trends.',
        'account' => 'Review and update your account information.',
        'chat' => 'View conversations and send messages.',
        'messages' => 'View conversations and send messages.',
        'users' => 'Review user accounts and manage access or status.',
        'registrations' => 'Review registration applications and their approval status.',
        'settings' => 'Manage platform settings and preferences.',
        'audit' => 'Review recorded administrative activity.',
        'complaints' => 'Review customer complaints and follow up on their resolution.',
        'compliance' => 'Review compliance cases and related seller information.',
        'refunds' => 'Review refund requests and manage their status.',
        'commission' => 'Review commission rates and reconciliation details.',
        'parcels' => 'Manage parcel intake, sorting, and pickup operations.',
        'tracking' => 'Monitor delivery progress and shipment status.',
        'applications' => 'Review rider applications and manage rider accounts.',
        'branches' => 'Manage logistics branches and their details.',
        'shop' => 'Browse available products and find items to purchase.',
        'cart' => 'Review items in your cart before checkout.',
        'checkout' => 'Confirm your order details and complete your purchase.',
        'notifications' => 'Review updates and important account activity.',
        'chat' => 'View conversations and send messages.',
    ];
    $routeParts = explode('.', $routeName);
    $description = collect($routeParts)->reverse()->map(fn ($part) => $descriptions[$part] ?? null)->first(fn ($text) => $text)
        ?? 'Use this page to manage ' . strtolower(trim($__env->yieldContent('title'))) . ' and review related information.';
@endphp
<p class="page-intro">{{ $description }}</p>
