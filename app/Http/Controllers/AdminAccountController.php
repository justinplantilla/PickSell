<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangeAdminPasswordRequest;
use App\Http\Requests\DeleteAdminAccountRequest;
use App\Http\Requests\UpdateAdminAccountRequest;
use App\Models\User;
use App\Services\Admin\AdminAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminAccountController extends Controller
{
    public function show(): View
    {
        Gate::authorize('viewAdminAccount', request()->user());

        return view('admin.account');
    }

    public function update(UpdateAdminAccountRequest $request, AdminAccountService $accounts): RedirectResponse
    {
        $updated = $accounts->updateProfile(
            $request->user(),
            $request->safe()->only(['first_name', 'last_name', 'email', 'contact_no']),
        );

        return back()->with('success', $updated ? 'Account updated successfully.' : 'No profile changes were made.');
    }

    public function changePassword(ChangeAdminPasswordRequest $request, AdminAccountService $accounts): RedirectResponse
    {
        $accounts->changePassword($request->user(), $request->validated('password'));

        return back()->with('success', 'Password changed successfully.');
    }

    public function delete(DeleteAdminAccountRequest $request, AdminAccountService $accounts): RedirectResponse
    {
        $accounts->delete($request->user());

        return redirect('/')->with('success', 'Your administrator account was deleted.');
    }
}
