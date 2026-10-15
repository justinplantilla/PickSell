<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\EmailVerificationService;
use App\Support\LogisticsDomains;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function __construct(private EmailVerificationService $verification) {}

    public function showLogin()    { return view('auth.login'); }

    public function showRegister(Request $request)
    {
        $registrationRoles = $this->registrationRoles($request);
        $registrationToken = (string) Str::uuid();
        $email = strtolower(trim((string) $request->query('email', '')));

        return view('auth.register', compact('registrationRoles', 'email', 'registrationToken'));
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', strtolower(trim($credentials['email'])))->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors(['email' => 'Invalid email or password.'])->onlyInput('email');
        }

        if ($user->isPending()) {
            return back()->withErrors(['email' => 'Your account is still pending approval. Please check your email.'])->onlyInput('email');
        }

        if ($user->status === 'disapproved') {
            return back()->withErrors(['email' => 'Your registration was disapproved. Please contact support.'])->onlyInput('email');
        }

        if ($user->status === 'suspended') {
            return back()->withErrors(['email' => 'Your account has been suspended. Please contact support.'])->onlyInput('email');
        }

        if ($user->isDeactivated()) {
            return back()->withErrors(['email' => 'Your account has been deactivated. Please contact support.'])->onlyInput('email');
        }

        $allowedLoginRoles = $this->isLogisticsPortal($request)
            ? ['logistics', 'courier']
            : ['buyer', 'seller', 'admin'];

        if (!in_array($user->role, $allowedLoginRoles, true)) {
            $siteName = $this->isLogisticsPortal($request)
                ? 'PickSell Logistics'
                : 'PickSell';

            return back()->withErrors([
                'email' => "This account cannot log in through {$siteName}. Please use the correct portal.",
            ])->onlyInput('email');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        if ($user->assignedHub) {
            return redirect()->away($user->assignedHub->portalUrl());
        }

        return redirect($this->redirectByRole($user->role));
    }

    public function register(Request $request)
    {
        $token = (string) $request->input('registration_token', '');
        if (! preg_match('/^[A-Za-z0-9-]{20,64}$/', $token)) {
            return $this->createRegistration($request, null);
        }

        // A double-clicked Register button submits the same form token twice: the second request waits for
        // the first and then lands on the same next step instead of a duplicate-email error.
        return Cache::lock("registration:{$token}:lock", 20)
            ->block(15, fn () => $this->createRegistration($request, $token));
    }

    private function createRegistration(Request $request, ?string $token)
    {
        if ($token && ($userId = Cache::get("registration:{$token}:user")) && ($existing = User::find($userId))) {
            return $this->afterRegistration($request, $existing, false);
        }

        $request->merge([
            'email' => strtolower(trim((string) $request->input('email'))),
            'contact_no' => $this->normalizeMobile($request->input('contact_no')),
            'first_name' => $this->squish($request->input('first_name')),
            'last_name' => $this->squish($request->input('last_name')),
        ]);
        $role = $request->input('role', 'buyer');
        $registrationRoles = $this->registrationRoles($request);
        $namePattern = "/^\\pL[\\pL\\s.'\\-]*$/u";

        $rules = [
            'role'           => 'required|in:'.implode(',', $registrationRoles),
            'provider_type'  => 'required_if:role,logistics|nullable|in:company,individual',
            'last_name'      => ['required', 'string', 'min:2', 'max:50', "regex:{$namePattern}"],
            'first_name'     => ['required', 'string', 'min:2', 'max:50', "regex:{$namePattern}"],
            'middle_initial' => ['nullable', 'string', 'max:5', "regex:/^[\\pL.\\s]+$/u"],
            'sex'            => 'required|in:Male,Female',
            'email'          => [...$this->emailRules(), function (string $attribute, $value, $fail) use ($request, $role): void {
                // Buyers confirm their email with a one-time code on the form's Account step.
                if ($role === 'buyer' && $request->session()->get('registration_verified_email') !== $value) {
                    $fail('Verify your email with the 6-digit code we send you before submitting.');
                }
            }],
            'password'       => ['required', 'confirmed', PasswordRule::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'confirmed'      => 'accepted',
            'terms'          => 'accepted',
            'contact_no'     => ['required', function (string $attribute, $value, $fail): void {
                if (! preg_match('/^\d+$/', (string) $value)) {
                    $fail('Enter a valid mobile number using digits only, like 09171234567.');
                } elseif (strlen((string) $value) !== 11) {
                    $fail('The mobile number must be exactly 11 digits.');
                } elseif (! str_starts_with((string) $value, '09')) {
                    $fail('The mobile number must start with 09.');
                }
            }, Rule::unique('users', 'contact_no')],
            'birthday'       => ['required', 'date', function (string $attribute, $value, $fail): void {
                $birthday = Carbon::parse($value)->startOfDay();
                if ($birthday->isFuture()) {
                    $fail('The date of birth cannot be in the future.');
                } elseif ($birthday->gt(now()->subYears(18)->startOfDay())) {
                    $fail('You must be at least 18 years old to register.');
                }
            }],
            'province'       => 'required|string',
            'municipality'   => 'required|string',
            'barangay'       => 'required|string',
            'street'         => 'nullable|string|max:255',
            'house_no'       => 'nullable|string|max:100',
            'id_upload'      => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ];

        if (in_array($role, ['seller', 'logistics'], true)) {
            $rules['business_name']    = 'required|string|max:255';
            $rules['line_of_business'] = 'required|string|max:255';
            $rules['business_permit']  = 'required|file|mimes:jpg,jpeg,png,pdf|max:5120';
        }

        if ($role === 'courier') {
            $rules['vehicle_type'] = 'required|string';
            $rules['plate_number'] = 'required|string|max:20';
            $rules['or_cr_upload'] = 'required|file|mimes:jpg,jpeg,png,pdf|max:5120';
            $rules['delivery_area'] = 'required|string|max:255';
        }

        $data = $request->validate($rules, [
            'first_name.regex' => 'The first name may only contain letters, spaces, hyphens (-), apostrophes (\') and periods.',
            'last_name.regex' => 'The last name may only contain letters, spaces, hyphens (-), apostrophes (\') and periods.',
            'first_name.min' => 'The first name must be at least 2 characters.',
            'last_name.min' => 'The last name must be at least 2 characters.',
            'first_name.max' => 'The first name may not be longer than 50 characters.',
            'last_name.max' => 'The last name may not be longer than 50 characters.',
            'middle_initial.regex' => 'The middle initial may only contain letters and periods.',
            ...$this->emailMessages(),
            'contact_no.unique' => 'This mobile number is already in use.',
            'password.confirmed' => 'The passwords do not match.',
            'terms.accepted' => 'You must accept the Terms of Service and Privacy Policy to register.',
            'confirmed.accepted' => 'Confirm that the information and documents you provided are accurate.',
        ]);

        $age = Carbon::parse($data['birthday'])->age;

        $idPath = $request->file('id_upload')->store('uploads/ids', 'public');

        $extra = [];
        if (in_array($role, ['seller', 'logistics'], true)) {
            $extra['business_name']    = $data['business_name'];
            $extra['line_of_business'] = $data['line_of_business'];
            $extra['business_permit']  = $request->file('business_permit')->store('uploads/permits', 'public');
        }
        if ($role === 'courier') {
            $extra['vehicle_type'] = $data['vehicle_type'];
            $extra['plate_number'] = $data['plate_number'];
            $extra['or_cr_upload'] = $request->file('or_cr_upload')->store('uploads/orcr', 'public');
            $extra['delivery_area'] = $data['delivery_area'];
        }

        $applicant = User::create(array_merge([
            'role'           => $role,
            'provider_type'  => $role === 'logistics' ? $data['provider_type'] : null,
            'status'         => 'pending',
            'last_name'      => $data['last_name'],
            'first_name'     => $data['first_name'],
            'middle_initial' => $data['middle_initial'] ?? null,
            'sex'            => $data['sex'],
            'email'          => $data['email'],
            'password'       => Hash::make($data['password']),
            'contact_no'     => $data['contact_no'],
            'birthday'       => $data['birthday'],
            'age'            => $age,
            'province'       => $data['province'],
            'municipality'   => $data['municipality'],
            'barangay'       => $data['barangay'],
            'street'         => $data['street'] ?? null,
            'house_no'       => $data['house_no'] ?? null,
            'id_upload'      => $idPath,
        ], $extra));
        if ($role === 'buyer') {
            $applicant->forceFill(['email_verified_at' => now()])->save();
        }
        if ($token) {
            Cache::put("registration:{$token}:user", $applicant->id, now()->addMinutes(30));
        }

        return $this->afterRegistration($request, $applicant, true);
    }

    private function afterRegistration(Request $request, User $applicant, bool $justCreated)
    {
        if ($justCreated) {
            $request->session()->forget('registration_verified_email');
            app(\App\Services\AdminNotificationService::class)->notifyAdmins(
                'registration.pending',
                'Pending registration',
                "{$applicant->first_name} {$applicant->last_name} submitted a {$applicant->role} registration.",
                route('admin.registrations.show', $applicant, false),
            );
        }

        return redirect('/login')->with('success', 'Registration submitted! Please wait for admin approval. You will be notified via email.');
    }

    /** Registration form, Account step: email a one-time code to the address being registered. */
    public function sendRegistrationCode(Request $request)
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate(['email' => $this->emailRules(), 'first_name' => ['nullable', 'string', 'max:50']], $this->emailMessages());
        $email = $data['email'];

        if (($wait = $this->verification->resendWaitSeconds($email)) > 0) {
            return response()->json(['message' => "Please wait {$wait} seconds before requesting another code.", 'wait' => $wait], 429);
        }
        if ($request->session()->get('registration_verified_email') === $email) {
            return response()->json(['message' => 'This email is already verified.', 'verified' => true]);
        }

        if (! $this->verification->send($email, $data['first_name'] ?? null)) {
            return response()->json(['message' => 'The code could not be sent. Check the email address and try again.'], 503);
        }

        return response()->json([
            'message' => "We sent a 6-digit code to {$email}. It expires in ".EmailVerificationService::CODE_TTL_MINUTES.' minutes.',
            'wait' => EmailVerificationService::RESEND_COOLDOWN_SECONDS,
        ]);
    }

    /** Registration form, Account step: confirm the code; the email then counts as verified for this session. */
    public function verifyRegistrationCode(Request $request)
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->input('email'))),
            'code' => preg_replace('/\s+/', '', (string) $request->input('code')),
        ]);
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'code' => ['required', 'regex:/^\d{6}$/'],
        ], [
            'code.required' => 'Enter the 6-digit code from the email.',
            'code.regex' => 'The code must be exactly 6 digits.',
        ]);

        $result = $this->verification->check($data['email'], $data['code']);
        if ($result === 'verified') {
            $request->session()->put('registration_verified_email', $data['email']);

            return response()->json(['verified' => true, 'message' => 'Email verified.']);
        }

        $attemptsLeft = $this->verification->attemptsLeft($data['email']);

        return response()->json(['verified' => false, 'message' => match ($result) {
            'invalid' => "That code is incorrect. You have {$attemptsLeft} ".($attemptsLeft === 1 ? 'try' : 'tries').' left.',
            'expired' => 'This code has expired. Send a new code and try again.',
            'locked' => 'Too many incorrect attempts. Send a new code and try again.',
            default => 'Send a code to this email first.',
        }], 422);
    }

    private function emailRules(): array
    {
        return ['required', 'string', 'max:255', 'email:rfc', 'regex:/^[^@\s]+@[^@\s]+\.[A-Za-z]{2,}$/', Rule::unique('users', 'email')];
    }

    private function emailMessages(): array
    {
        return [
            'email.email' => 'Enter a valid email address, like name@example.com.',
            'email.regex' => 'Enter a valid email address, like name@example.com.',
            'email.unique' => 'This email is already in use. Log in instead, or use a different email.',
        ];
    }

    /** "+63 917 123 4567", "639171234567" and "09171234567" are the same number. */
    private function normalizeMobile($value): string
    {
        $value = preg_replace('/\s+/', '', (string) $value);

        return preg_match('/^\+?63(9\d{9})$/', $value, $matches) ? '0'.$matches[1] : $value;
    }

    private function squish($value): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $value));
    }

    private function registrationRoles(Request $request): array
    {
        return $this->isLogisticsPortal($request)
            ? ['courier', 'logistics']
            : ['buyer', 'seller'];
    }

    private function isLogisticsPortal(Request $request): bool
    {
        return LogisticsDomains::isLogisticsHost($request->getHost());
    }

    public function showForgotPassword() { return view('auth.forgot-password'); }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $status = Password::sendResetLink($request->only('email'));
        return $status === Password::RESET_LINK_SENT
            ? back()->with('success', 'Password reset link sent to your email.')
            : back()->withErrors(['email' => __($status)]);
    }

    public function showResetPassword(string $token)
    {
        return view('auth.reset-password', ['token' => $token]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect('/login')->with('success', 'Password reset successfully. Please log in.')
            : back()->withErrors(['email' => __($status)]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }

    public function deleteAccount(Request $request)
    {
        $request->validate(['password' => 'required|string']);
        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            return back()->withErrors(['password' => 'The password is incorrect.']);
        }

        DB::transaction(function () use ($user): void {
            app(\App\Services\AuditLogger::class)->record(
                'user.deleted',
                $user,
                [],
                ['deleted_actor_id' => $user->id],
            );
            $user->delete();
        });
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Your account has been deleted.');
    }

    private function redirectByRole(string $role): string
    {
        return match($role) {
            'admin'   => '/admin/dashboard',
            'logistics' => '/logistics/dashboard',
            'seller'  => '/seller/dashboard',
            'courier' => '/courier/dashboard',
            default   => '/buyer/dashboard',
        };
    }
}
