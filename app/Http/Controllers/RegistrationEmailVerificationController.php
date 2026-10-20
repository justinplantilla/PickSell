<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AdminNotificationService;
use App\Services\EmailVerificationService;
use Illuminate\Http\Request;

/**
 * Logistics applicants confirm their email with a 6-digit code after submitting the registration form.
 * Until then the account is "Unverified" (pending, no email_verified_at) and is not in the admin's queue;
 * once verified it is Pending Approval and admins are notified. The applicant is never signed in here.
 */
class RegistrationEmailVerificationController extends Controller
{
    public const SESSION_KEY = 'email_verification_user_id';

    public function __construct(private EmailVerificationService $verification) {}

    /** Called right after registration: send the first code (once) and open the verification page. */
    public function start(Request $request, User $applicant, bool $justCreated)
    {
        $request->session()->put(self::SESSION_KEY, $applicant->id);

        if (! $justCreated) {
            return redirect()->route('register.verify-email');
        }

        $sent = $this->verification->send($applicant->email, $applicant->first_name);

        return redirect()->route('register.verify-email')->with($sent ? 'success' : 'warning', $sent
            ? "Registration submitted! We sent a 6-digit code to {$applicant->email}. It expires in ".EmailVerificationService::CODE_TTL_MINUTES.' minutes.'
            : 'Registration submitted, but the verification code could not be sent. Click Resend code to try again.');
    }

    public function show(Request $request)
    {
        $applicant = $this->applicant($request);
        if (! $applicant) {
            return redirect('/login');
        }

        return view('auth.verify-email', [
            'applicant' => $applicant,
            'resendWait' => $this->verification->resendWaitSeconds($applicant->email),
        ]);
    }

    public function verify(Request $request, AdminNotificationService $notifications)
    {
        $applicant = $this->applicant($request);
        if (! $applicant) {
            return redirect('/login')->withErrors(['email' => 'Log in again to verify your email.']);
        }

        $request->merge(['code' => preg_replace('/\s+/', '', (string) $request->input('code'))]);
        $data = $request->validate(['code' => ['required', 'regex:/^\d{6}$/']], [
            'code.required' => 'Enter the 6-digit code from the email.',
            'code.regex' => 'The code must be exactly 6 digits.',
        ]);

        $result = $this->verification->check($applicant->email, $data['code']);
        if ($result !== 'verified') {
            $attemptsLeft = $this->verification->attemptsLeft($applicant->email);

            return back()->withErrors(['code' => match ($result) {
                'invalid' => "That code is incorrect. You have {$attemptsLeft} ".($attemptsLeft === 1 ? 'try' : 'tries').' left.',
                'expired' => 'This code has expired. Click Resend code to get a new one.',
                'locked' => 'Too many incorrect attempts. Click Resend code to get a new one.',
                default => 'There is no active code for this email. Click Resend code to get a new one.',
            }]);
        }

        $applicant->forceFill(['email_verified_at' => now()])->save();
        $request->session()->forget(self::SESSION_KEY);
        $notifications->notifyAdmins(
            'registration.pending',
            'Pending registration',
            "{$applicant->first_name} {$applicant->last_name} submitted a {$applicant->role} registration.",
            route('admin.registrations.show', $applicant, false),
        );

        return redirect('/login')->with('success', 'Email verified! Your application is now waiting for admin approval. You will be notified via email.');
    }

    public function resend(Request $request)
    {
        $applicant = $this->applicant($request);
        if (! $applicant) {
            return redirect('/login')->withErrors(['email' => 'Log in again to verify your email.']);
        }

        if (($wait = $this->verification->resendWaitSeconds($applicant->email)) > 0) {
            return back()->withErrors(['code' => "Please wait {$wait} seconds before requesting another code."]);
        }
        if (! $this->verification->send($applicant->email, $applicant->first_name)) {
            return back()->withErrors(['code' => 'The code could not be sent. Try again in a moment.']);
        }

        return back()->with('success', "We sent a new 6-digit code to {$applicant->email}. Earlier codes no longer work.");
    }

    private function applicant(Request $request): ?User
    {
        $applicant = User::find($request->session()->get(self::SESSION_KEY));

        return $applicant?->isAwaitingEmailVerification() ? $applicant : null;
    }
}
