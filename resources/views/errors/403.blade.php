@php
    $user = auth()->user();
    $message = $exception->getMessage() ?: 'You do not have access to this page.';
    $home = match (true) {
        $user?->assignedHub !== null => $user->assignedHub->portalUrl(),
        $user?->role === 'logistics' => 'https://'.\App\Support\LogisticsDomains::superadminHost().'/logistics/dashboard',
        $user !== null => url('/dashboard'),
        default => url('/'),
    };
    $isOwnPortal = $home === url()->current();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access denied · PickSell</title>
    <style>
        :root { --coral:#E8472A; --ink:#2D2D2D; --muted:#6b7280; --bg:#f4f4f4; --card:#fff; --line:#e8e2d8; }
        @media (prefers-color-scheme: dark) { :root { --ink:#e5e7eb; --muted:#9ca3af; --bg:#111; --card:#1e1e1e; --line:#2a2a2a; } }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; display:grid; place-items:center; padding:1rem; font-family:'Segoe UI',system-ui,sans-serif; background:var(--bg); color:var(--ink); }
        .card { width:min(460px,100%); background:var(--card); border:1px solid var(--line); border-radius:14px; padding:2rem; box-shadow:0 18px 50px rgba(0,0,0,.08); }
        .code { color:var(--coral); font-weight:800; letter-spacing:.08em; font-size:.8rem; }
        h1 { font-size:1.35rem; margin:.35rem 0 .6rem; }
        p { color:var(--muted); line-height:1.55; margin:0 0 1rem; font-size:.92rem; }
        .who { border:1px solid var(--line); border-radius:10px; padding:.75rem .9rem; font-size:.85rem; margin-bottom:1.2rem; }
        .who strong { display:block; color:var(--ink); }
        .actions { display:flex; gap:.6rem; flex-wrap:wrap; }
        .btn { border:0; border-radius:8px; padding:.6rem 1rem; font-weight:700; font-size:.85rem; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; }
        .btn-coral { background:var(--coral); color:#fff; }
        .btn-outline { background:transparent; color:var(--ink); border:1px solid var(--line); }
        form { margin:0; }
    </style>
</head>
<body>
<main class="card">
    <div class="code">403 · ACCESS DENIED</div>
    <h1>{{ $message }}</h1>
    @if($user)
        <div class="who">
            You are signed in as
            <strong>{{ $user->full_name }}</strong>
            {{ $user->email }}@if($user->assignedHub) · operator of {{ $user->assignedHub->name }}@endif
        </div>
        <p>Each logistics portal only admits its own account. Sign out and sign in with the account for this page, or go back to your own portal.</p>
        <div class="actions">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-coral" type="submit">Sign out and switch account</button>
            </form>
            @unless($isOwnPortal)
                <a class="btn btn-outline" href="{{ $home }}">Go to my portal</a>
            @endunless
        </div>
    @else
        <p>Sign in with an account that has access to this page.</p>
        <div class="actions"><a class="btn btn-coral" href="{{ url('/login') }}">Sign in</a></div>
    @endif
</main>
</body>
</html>
