<?php

namespace App\Pickrush\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Pickrush\Http\Requests\RegisterCompanyRequest;
use App\Pickrush\Services\Registration\AddressOptions;
use App\Pickrush\Services\Registration\CompanyRegistration;
use App\Pickrush\Services\Registration\RegistrationEmailCodes;
use App\Pickrush\Support\AccountContacts;
use App\Rules\DeliverableEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Logistics company self-registration. The email is verified inside the form: "Verify" sends a
 * code and a dialog takes it; the form submits only once the address is verified for this session.
 */
class RegistrationController extends Controller
{
    /** How long a verified email stays good for submitting the form. */
    private const VERIFIED_FOR_MINUTES = 30;

    public function __construct(private RegistrationEmailCodes $codes) {}

    public function show(Request $request)
    {
        return view('pickrush.auth.register', ['verifiedEmail' => $this->verifiedEmail($request)]);
    }

    public function sendCode(Request $request): JsonResponse
    {
        $request->merge(['email' => AccountContacts::normalizeEmail($request->input('email'))]);
        $request->validate(['email' => ['required', 'string', 'max:255', 'email:rfc', 'regex:/^[^@\s]+@[^@\s]+\.[A-Za-z]{2,}$/', new DeliverableEmail]],
            ['email.*' => 'Enter a valid email address.']);
        $email = $request->input('email');

        if (AccountContacts::emailTaken($email)) {
            return response()->json(['message' => 'This email address is already registered.'], 422);
        }
        if ($this->verifiedEmail($request) === $email) {
            return response()->json(['message' => 'This email is already verified.', 'verified' => true]);
        }

        $result = $this->codes->send($email);

        return match ($result['status']) {
            'sent' => response()->json([
                'message' => "We sent a 6-digit code to {$email}.",
                'expires_in' => $result['expires_in'],
                'resend_in' => $result['wait'],
            ]),
            'wait' => response()->json(['message' => "Please wait {$result['wait']} seconds before requesting another code.", 'resend_in' => $result['wait']], 429),
            'locked' => response()->json(['message' => $this->lockedMessage($result['wait']), 'locked_for' => $result['wait']], 429),
            default => response()->json(['message' => 'The code could not be sent. Check the email address and try again.'], 503),
        };
    }

    public function verifyCode(Request $request): JsonResponse
    {
        $request->merge([
            'email' => AccountContacts::normalizeEmail($request->input('email')),
            'code' => preg_replace('/\s+/', '', (string) $request->input('code')),
        ]);
        $data = $request->validate(['email' => ['required', 'email'], 'code' => ['required', 'regex:/^\d{6}$/']],
            ['code.required' => 'Enter the 6-digit code from the email.', 'code.regex' => 'The code must be exactly 6 digits.']);

        $result = $this->codes->verify($data['email'], $data['code']);
        if ($result['status'] === 'verified') {
            $request->session()->put('logistics_verified_email', $data['email']);
            $request->session()->put('logistics_verified_at', now()->getTimestamp());

            return response()->json(['verified' => true, 'message' => 'Email verified.']);
        }

        return response()->json(['verified' => false] + match ($result['status']) {
            'invalid' => ['message' => "That code is incorrect. You have {$result['attempts_left']} ".($result['attempts_left'] === 1 ? 'try' : 'tries').' left.'],
            'expired' => ['message' => 'This code has expired. Send a new code and try again.'],
            'locked' => ['message' => $this->lockedMessage($result['wait']), 'locked_for' => $result['wait']],
            default => ['message' => 'Send a code to this email first.'],
        }, 422);
    }

    public function store(RegisterCompanyRequest $request, CompanyRegistration $registration)
    {
        if ($this->verifiedEmail($request) !== $request->input('email')) {
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => 'Your email verification has expired. Verify the email again.']);
        }

        // A double click must not file the application twice.
        $lock = Cache::lock('logistics-register:'.sha1($request->input('email')), 30);
        if (! $lock->get()) {
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['business_name' => 'Your registration is already being submitted.']);
        }

        try {
            $company = $registration->register($request->validated(), $request->file('valid_id'), $request->file('dti_permit'));
        } finally {
            $lock->release();
        }

        $request->session()->forget(['logistics_verified_email', 'logistics_verified_at']);

        return redirect()->route('pickrush.register.submitted')->with('registered_company', $company->name);
    }

    public function submitted(Request $request)
    {
        if (! $request->session()->has('registered_company')) {
            return redirect()->route('pickrush.register');
        }

        return view('pickrush.auth.register-submitted', ['company' => $request->session()->get('registered_company')]);
    }

    public function provinces(AddressOptions $address): JsonResponse
    {
        return response()->json($address->provinces());
    }

    public function municipalities(Request $request, AddressOptions $address): JsonResponse
    {
        return response()->json($address->municipalities((string) $request->query('province')));
    }

    public function barangays(Request $request, AddressOptions $address): JsonResponse
    {
        return response()->json($address->barangays((string) $request->query('municipality')));
    }

    private function verifiedEmail(Request $request): ?string
    {
        $at = (int) $request->session()->get('logistics_verified_at');

        return $at && now()->getTimestamp() - $at <= self::VERIFIED_FOR_MINUTES * 60
            ? $request->session()->get('logistics_verified_email')
            : null;
    }

    private function lockedMessage(int $seconds): string
    {
        $minutes = max(1, (int) ceil($seconds / 60));

        return "Too many incorrect codes. Try again in {$minutes} ".($minutes === 1 ? 'minute' : 'minutes').'.';
    }
}
