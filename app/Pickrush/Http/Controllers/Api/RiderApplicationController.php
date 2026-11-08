<?php

namespace App\Pickrush\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Pickrush\Models\Company;
use App\Pickrush\Models\Rider;
use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Services\Rider\EmailVerification;
use App\Pickrush\Services\Rider\RiderApplication;
use App\Pickrush\Support\Actor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Applying as a rider from the rider app, and resubmitting after a rejection. */
class RiderApplicationController extends Controller
{
    public function __construct(private RiderApplication $applications) {}

    /** Logistics companies taking riders near the applicant's address; the default company first. */
    public function companies(Request $request): JsonResponse
    {
        $data = $request->validate(['city_code' => 'nullable|string|max:10', 'province_code' => 'nullable|string|max:10']);

        return response()->json([
            'companies' => $this->applications->companiesNear($data['city_code'] ?? null, $data['province_code'] ?? null)
                ->map(fn (Company $company) => RiderProfile::company($company))->values(),
        ]);
    }

    /**
     * Stations of one company the applicant can choose: their city first, otherwise their
     * province. Without company_id (older app versions) the default company's stations.
     */
    public function stations(Request $request): JsonResponse
    {
        $data = $request->validate([
            'city_code' => 'nullable|string|max:10',
            'province_code' => 'nullable|string|max:10',
            'company_id' => 'nullable|integer',
        ]);
        $near = $this->applications->stationsNear($data['city_code'] ?? null, $data['province_code'] ?? null,
            (int) ($data['company_id'] ?? Company::defaultId()));

        return response()->json([
            'scope' => $near['scope'],
            'hubs' => $near['stations']->map(fn ($hub) => RiderProfile::hub($hub))->values(),
        ]);
    }

    public function register(Request $request, EmailVerification $verification): JsonResponse
    {
        $data = $request->validate($this->rules() + [
            'email' => ['required', 'email:rfc', 'max:255', function ($attribute, $value, $fail) {
                if (Rider::where('email', EmailVerification::normalize($value))->exists()) {
                    $fail('This email is already registered. Log in instead.');
                }
            }],
            'password' => ['required', 'string', 'max:72', Password::min(8)->mixedCase()->numbers()->symbols()],
            'email_verification_token' => 'required|string',
        ]);

        if (! $verification->consume($data['email'], $data['email_verification_token'])) {
            $message = 'Your email verification has expired. Please verify your email again.';

            return response()->json(['message' => $message, 'errors' => ['email_verification_token' => [$message]]], 422);
        }

        $rider = $this->applications->apply($data, $this->files($request));

        return response()->json([
            'message' => 'Application received. Your station will review it.',
            'rider' => RiderProfile::of($rider),
        ], 201);
    }

    public function resubmit(Request $request): JsonResponse
    {
        $rider = Actor::rider();
        $data = $request->validate($this->rules($rider));

        try {
            $rider = $this->applications->resubmit($rider, $data, $this->files($request));
        } catch (OperationRefused $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Application resubmitted.', 'rider' => RiderProfile::of($rider)]);
    }

    /** The fields the rider app sends, shared by a first application and a resubmission. */
    private function rules(?Rider $rider = null): array
    {
        $image = 'file|mimes:jpg,jpeg,png,heic,webp|max:8192';

        return [
            'first_name' => 'required|string|max:60',
            'last_name' => 'required|string|max:60',
            'birth_date' => 'required|date|before_or_equal:-18 years',
            'mobile' => ['required', 'regex:/^9\d{9}$/', function ($attribute, $value, $fail) use ($rider) {
                $taken = Rider::where('mobile_no', RiderApplication::mobileNo($value))
                    ->when($rider, fn ($q) => $q->whereKeyNot($rider->id))->exists();
                if ($taken) {
                    $fail('This mobile number is already registered.');
                }
            }],
            'province_code' => 'required|string|max:10',
            'province_name' => 'required|string|max:100',
            'city_code' => 'required|string|max:10',
            'city_name' => 'required|string|max:100',
            'barangay_code' => 'required|string|max:10',
            'barangay_name' => 'required|string|max:100',
            'street' => 'required|string|max:150',
            'vehicle_type' => ['required', Rule::in(array_keys(Rider::VEHICLE_TYPES))],
            'company_id' => ['nullable', 'integer', function ($attribute, $value, $fail) {
                if (! Company::approved()->whereKey($value)->exists()) {
                    $fail('This logistics company is not taking riders. Choose another company.');
                }
            }],
            'hub_id' => ['required', 'integer', function ($attribute, $value, $fail) {
                $companyId = request()->integer('company_id') ?: null;
                if (! RiderApplication::acceptingStations($companyId)->whereKey($value)->exists()) {
                    $fail($companyId ? 'This station is not accepting riders for the chosen company. Choose another station.'
                        : 'This station is not accepting riders. Choose another station.');
                }
            }],
            'plate_number' => 'required|string|max:20',
            'license_no' => 'required|string|max:30',
            'license_expiry' => 'required|date|after:today',
            'license_front' => "required|{$image}",
            'license_back' => "required|{$image}",
            'orcr' => 'required|array|min:1|max:5',
            'orcr.*' => 'file|mimes:jpg,jpeg,png,heic,webp,pdf|max:8192',
        ];
    }

    private function files(Request $request): array
    {
        return [
            'license_front' => $request->file('license_front'),
            'license_back' => $request->file('license_back'),
            'orcr' => array_values($request->file('orcr')),
        ];
    }
}
