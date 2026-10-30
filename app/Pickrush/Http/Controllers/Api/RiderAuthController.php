<?php

namespace App\Pickrush\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\RiderToken;
use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Services\Rider\EmailVerification;
use App\Pickrush\Services\Rider\PasswordReset;
use App\Pickrush\Support\Actor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/** Rider app sign-in: email code while applying, then email and password. No SMS. */
class RiderAuthController extends Controller
{
    public function __construct(private EmailVerification $verification) {}

    public function sendCode(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => 'required|email:rfc|max:255']);

        try {
            $this->verification->send($data['email']);
        } catch (OperationRefused $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['email' => [$e->getMessage()]]], 422);
        }

        return response()->json(['message' => 'We sent a 6-digit code to your email.']);
    }

    public function verifyCode(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => 'required|email:rfc|max:255', 'code' => 'required|digits:6']);

        try {
            $token = $this->verification->verify($data['email'], $data['code']);
        } catch (OperationRefused $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['code' => [$e->getMessage()]]], 422);
        }

        return response()->json(['verificationToken' => $token, 'expiresInMinutes' => EmailVerification::TOKEN_MINUTES]);
    }

    /** Any applicant can sign in; the app shows what their status allows. */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => 'required|email:rfc', 'password' => 'required|string']);

        $rider = Rider::where('email', EmailVerification::normalize($data['email']))->first();
        if (! $rider || ! $rider->password || ! Hash::check($data['password'], $rider->password)) {
            return response()->json(['message' => 'Incorrect email or password.'], 422);
        }
        if ($rider->status === 'deactivated') {
            return response()->json(['message' => 'This rider account has been deactivated. Contact your station.'], 403);
        }

        return response()->json(['token' => RiderToken::issue($rider), 'rider' => RiderProfile::of($rider)]);
    }

    public function me(): JsonResponse
    {
        return response()->json(['rider' => RiderProfile::of(Actor::rider())]);
    }

    /** "Forgot password?": a code to the registered email, shown masked. Same answer for unknown emails. */
    public function forgotPassword(Request $request, PasswordReset $reset): JsonResponse
    {
        $data = $request->validate(['email' => 'required|email:rfc|max:255']);
        try {
            $masked = $reset->send($data['email']);
        } catch (OperationRefused $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => "If this email is registered, we sent a 6-digit code to {$masked}.", 'masked_email' => $masked]);
    }

    /** The code from that email plus a new password. Every phone is signed out. */
    public function resetPassword(Request $request, PasswordReset $reset): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email:rfc|max:255',
            'code' => 'required|digits:6',
            'password' => ['required', 'string', 'max:72', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);
        try {
            $reset->reset($data['email'], $data['code'], $data['password']);
        } catch (OperationRefused $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['code' => [$e->getMessage()]]], 422);
        }

        return response()->json(['message' => 'Password reset. Log in with your new password.']);
    }

    /** Change password: needs the current one. Other phones signed in to this account are signed out. */
    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'string', 'max:72', 'confirmed', 'different:current_password', Password::min(8)->mixedCase()->numbers()->symbols()],
        ], ['password.different' => 'Choose a password different from your current one.']);
        $rider = Actor::rider();
        if (! Hash::check($data['current_password'], (string) $rider->password)) {
            return response()->json(['message' => 'Your current password is incorrect.', 'errors' => ['current_password' => ['Your current password is incorrect.']]], 422);
        }
        $rider->update(['password' => $data['password']]);
        $rider->tokens()->whereKeyNot($request->attributes->get('rider_token')->id)->delete();

        return response()->json(['message' => 'Password changed. Other phones were signed out.']);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->attributes->get('rider_token')->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
