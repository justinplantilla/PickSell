<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PickSell Courier - @yield('title')</title>
    <style>
        :root { --coral:#E8472A; --charcoal:#2D2D2D; --bone:#F5F0E8; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:'Segoe UI',sans-serif; background:#f4f4f4; color:#2d2d2d; }
        a { color:inherit; text-decoration:none; }
        .topbar { background:var(--charcoal); color:#fff; padding:0.9rem 1.5rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; }
        .dashboard-datetime { color:#aaa; font-size:0.78rem; white-space:nowrap; }
        .brand { color:var(--coral); font-size:1.35rem; font-weight:800; }
        .brand span { color:#fff; }
        nav { display:flex; gap:0.9rem; flex-wrap:wrap; align-items:center; }
        nav a { color:#ddd; font-size:0.85rem; }
        nav a:hover, nav a.active { color:#ff927d; }
        .page { max-width:1200px; margin:0 auto; padding:1.5rem; }
        .card { background:#fff; border:1px solid #e8e2d8; border-radius:10px; margin-bottom:1.2rem; overflow:hidden; }
        .card-header { padding:1rem 1.2rem; border-bottom:1px solid #f0ebe0; display:flex; justify-content:space-between; gap:1rem; align-items:center; flex-wrap:wrap; }
        .card-body { padding:1.2rem; }
        .card-title { font-weight:700; }
        .stats { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; margin-bottom:1.2rem; }
        .stat { background:#fff; border:1px solid #e8e2d8; border-radius:10px; padding:1rem; }
        .stat strong { display:block; font-size:1.5rem; color:var(--coral); }
        .stat span { color:#888; font-size:0.8rem; }
        table { width:100%; border-collapse:collapse; font-size:0.86rem; }
        th { text-align:left; color:#888; font-size:0.72rem; text-transform:uppercase; padding:0.65rem; border-bottom:1px solid #eee; }
        td { padding:0.75rem 0.65rem; border-bottom:1px solid #f3f0eb; vertical-align:middle; }
        .btn { display:inline-block; border:0; border-radius:6px; padding:0.48rem 0.8rem; font-weight:600; cursor:pointer; font-size:0.8rem; }
        .btn-coral { background:var(--coral); color:#fff; }
        .btn-success { background:#16a34a; color:#fff; }
        .btn-outline { border:1px solid #d8d0c3; background:#fff; color:#555; }
        .filter-select, .form-control { padding:0.5rem 0.65rem; border:1px solid #d8d0c3; border-radius:6px; background:#fff; }
        .badge { display:inline-block; border-radius:999px; padding:0.2rem 0.55rem; font-size:0.7rem; font-weight:700; text-transform:uppercase; }
        .badge-shipped { background:#eff6ff; color:#2563eb; }
        .badge-processing { background:#fff8e1; color:#b45309; }
        .badge-completed { background:#f0fdf4; color:#16a34a; }
        .alert { padding:0.8rem 1rem; border-radius:7px; margin-bottom:1rem; }
        .alert-success { background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; }
        .alert-error { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }
        .form-group { margin-bottom:1rem; }
        .form-label { display:block; font-weight:600; font-size:0.82rem; margin-bottom:0.35rem; }
        @media(max-width:700px) { .stats { grid-template-columns:repeat(2,1fr); } .page { padding:1rem; } .topbar { align-items:flex-start; flex-direction:column; } }
    </style>
</head>
<body>
<header class="topbar">
    <a class="brand" href="{{ route('courier.orders') }}">Pick<span>Sell</span> <small style="font-size:0.65rem;color:#aaa;">COURIER</small></a>
    <span class="dashboard-datetime">{{ now()->format('M d, Y h:i A') }}</span>
    <nav>
        <a class="{{ request()->routeIs('courier.orders') ? 'active' : '' }}" href="{{ route('courier.orders') }}">Delivery Tasks</a>
        <a class="{{ request()->routeIs('courier.reports') ? 'active' : '' }}" href="{{ route('courier.reports') }}">Reports</a>
        <a class="{{ request()->routeIs('courier.chat') ? 'active' : '' }}" href="{{ route('courier.chat') }}">Chat</a>
        <a class="{{ request()->routeIs('courier.account') ? 'active' : '' }}" href="{{ route('courier.account') }}">Account</a>
        <form method="POST" action="{{ route('logout') }}" style="display:inline;">@csrf<button class="btn btn-outline" type="submit">Log out</button></form>
    </nav>
</header>
<main class="page">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif
    @yield('content')
</main>
</body>
</html>
