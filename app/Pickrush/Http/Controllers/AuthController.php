<?php

namespace App\Pickrush\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Pickrush\Models\AuditLog;
use App\Pickrush\Models\Staff;
use App\Pickrush\Support\Actor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Email and password sign-in for portal staff. Riders sign in through the rider API with OTP. */
class AuthController extends Controller
{
    public function showLogin()
    {
        return view('pickrush.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);
        $credentials['email'] = strtolower(trim($credentials['email']));

        if (! Auth::guard('pickrush')->attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            $inactive = Staff::where('email', $credentials['email'])->where('is_active', false)->exists();

            return back()->withErrors(['email' => $inactive
                ? 'This account is inactive. Ask HQ to activate it.'
                : 'These credentials do not match a Pickrush account.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $staff = Actor::staff();
        $staff->forceFill(['last_login_at' => now()])->save();
        AuditLog::record($staff, 'signed_in');

        return redirect()->intended(route('pickrush.dashboard'));
    }

    public function logout(Request $request)
    {
        AuditLog::record(Actor::staff(), 'signed_out');
        Auth::guard('pickrush')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('pickrush.login');
    }
}
