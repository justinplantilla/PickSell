<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Log In | Pickrush Logistics</title>
    @vite('resources/css/pickrush/app.css')
</head>
<body>
<div class="pr-auth">
    <div class="pr-auth-card">
        <div class="pr-brand"><span class="pr-brand-mark" aria-hidden="true">PR</span> Pickrush Logistics</div>
        <h1>Staff sign-in</h1>
        <p class="pr-muted">For HQ, province hub and station hub staff. Riders sign in with the rider app.</p>

        <form method="POST" action="{{ route('pickrush.login.attempt') }}" novalidate>
            @csrf
            <div class="pr-field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" class="pr-input" autocomplete="username" required autofocus>
                @error('email')<span class="pr-field-error">{{ $message }}</span>@enderror
            </div>
            <div class="pr-field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" class="pr-input" autocomplete="current-password" required>
                @error('password')<span class="pr-field-error">{{ $message }}</span>@enderror
            </div>
            <label class="pr-check"><input type="checkbox" name="remember" value="1"> Keep me signed in on this device</label>
            <button type="submit" class="pr-btn pr-btn-primary" style="width:100%">Sign in</button>
        </form>
    </div>
</div>
</body>
</html>
