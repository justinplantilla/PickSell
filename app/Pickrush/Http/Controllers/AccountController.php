<?php

namespace App\Pickrush\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/** Every staff member's own password. */
class AccountController extends PortalController
{
    public function edit()
    {
        return view('pickrush.account', ['staff' => $this->staff()]);
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(10)],
        ]);
        $staff = $this->staff();
        if (! Hash::check($data['current_password'], $staff->password)) {
            return back()->withErrors(['current_password' => 'That is not your current password.']);
        }
        $staff->update(['password' => $data['password']]);
        $request->session()->regenerate();

        return back()->with('success', 'Password changed.');
    }
}
