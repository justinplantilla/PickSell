<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\RegistrationResubmission;
use Illuminate\Http\Request;

/**
 * A rejected seller or buyer corrects their documents and sends the same application back for review
 * (RegistrationResubmission). They reach this page by logging in with their email and password
 * (AuthController::login), which does not sign them in; the application then returns to Pending
 * Approval and reappears in the admin's pending list.
 */
class RegistrationResubmissionController extends Controller
{
    public function __construct(private RegistrationResubmission $resubmission) {}

    public function show(Request $request)
    {
        $applicant = $this->applicant($request);
        if (! $applicant) {
            return redirect('/login');
        }

        return view('auth.resubmit', [
            'applicant' => $applicant,
            'documents' => $this->resubmission->documents($applicant),
            'reason' => $this->resubmission->reason($applicant),
        ]);
    }

    public function store(Request $request)
    {
        $applicant = $this->applicant($request);
        if (! $applicant) {
            return redirect('/login')->withErrors(['email' => 'Log in again to resubmit your application.']);
        }

        $this->resubmission->submit($applicant, $request);
        $request->session()->forget('resubmission_user_id');

        return redirect('/login')->with('success', 'Your application was resubmitted and is pending approval again. You will be notified via email.');
    }

    private function applicant(Request $request): ?User
    {
        $user = User::find($request->session()->get('resubmission_user_id'));

        return RegistrationResubmission::canResubmit($user) ? $user : null;
    }
}
