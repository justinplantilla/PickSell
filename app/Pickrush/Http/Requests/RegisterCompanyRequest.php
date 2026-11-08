<?php

namespace App\Pickrush\Http\Requests;

use App\Pickrush\Services\Registration\AddressOptions;
use App\Pickrush\Support\AccountContacts;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

/** The logistics company registration form. Every rule here is also checked in the browser. */
class RegisterCompanyRequest extends FormRequest
{
    private const NAME = '/^[A-Za-zÑñ\' \-]+$/u';

    private const BUSINESS_NAME = '/^[\pL\pN\s&.,\'\-()\/+#@!:]+$/u';

    public const DOCUMENT_RULES = ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf', 'mimetypes:image/jpeg,image/png,application/pdf'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $squish = fn ($value) => is_string($value) ? trim(preg_replace('/\s+/u', ' ', $value)) : $value;

        $this->merge([
            'last_name' => $squish($this->input('last_name')),
            'first_name' => $squish($this->input('first_name')),
            'middle_initial' => $squish($this->input('middle_initial')) ?: null,
            'email' => AccountContacts::normalizeEmail($this->input('email')),
            'contact_no' => preg_replace('/\s+/', '', (string) $this->input('contact_no')),
            'business_name' => $squish($this->input('business_name')),
            'position' => $squish($this->input('position')),
            'street_address' => $squish($this->input('street_address')),
        ]);
    }

    public function rules(): array
    {
        $address = app(AddressOptions::class);

        return [
            'last_name' => ['required', 'string', 'min:2', 'max:50', 'regex:'.self::NAME],
            'first_name' => ['required', 'string', 'min:2', 'max:50', 'regex:'.self::NAME],
            'middle_initial' => ['nullable', 'string', 'regex:/^[A-Za-zÑñ]\.?$/u'],
            'sex' => ['required', 'in:male,female'],
            'email' => ['required', 'string', 'max:255', 'email:rfc', 'regex:/^[^@\s]+@[^@\s]+\.[A-Za-z]{2,}$/',
                function (string $attribute, $value, Closure $fail) {
                    if (AccountContacts::emailTaken($value)) {
                        $fail('This email address is already registered.');
                    } elseif ($this->session()->get('logistics_verified_email') !== $value) {
                        $fail('Verify this email address first.');
                    }
                }],
            'contact_no' => ['required', 'regex:/^(09\d{9}|\+639\d{9})$/',
                fn (string $attribute, $value, Closure $fail) => AccountContacts::mobileTaken($value, $this->ownStaffId()) ? $fail('This mobile number is already registered.') : null],
            'birthday' => ['required', 'date_format:Y-m-d', 'before_or_equal:today',
                fn (string $attribute, $value, Closure $fail) => Carbon::createFromFormat('Y-m-d', $value)->startOfDay()->age < 18
                    ? $fail('You must be at least 18 years old.') : null],

            'business_name' => ['required', 'string', 'max:150', 'regex:'.self::BUSINESS_NAME,
                fn (string $attribute, $value, Closure $fail) => DB::table('pr_companies')->whereRaw('lower(name) = ?', [mb_strtolower($value)])
                    ->when($this->ownCompanyId(), fn ($q, $id) => $q->where('id', '!=', $id))->exists()
                    ? $fail('A logistics company with this name is already registered.') : null],
            'position' => ['required', 'string', 'max:100'],

            'province' => ['required', 'string', fn ($a, $value, Closure $fail) => $address->validProvince($value) ? null : $fail('Choose a province from the list.')],
            'municipality' => ['required', 'string', fn ($a, $value, Closure $fail) => $address->validMunicipality((string) $this->input('province'), $value)
                ? null : $fail('Choose a city or municipality in the selected province.')],
            'barangay' => ['required', 'string', fn ($a, $value, Closure $fail) => $address->validBarangay((string) $this->input('municipality'), $value)
                ? null : $fail('Choose a barangay in the selected city or municipality.')],
            'street_address' => ['required', 'string', 'min:5', 'max:255'],

            'valid_id' => self::DOCUMENT_RULES,
            'dti_permit' => self::DOCUMENT_RULES,

            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'terms' => ['accepted'],
        ];
    }

    /** The applicant's own account, which a resubmission may keep its mobile number from. */
    protected function ownStaffId(): ?int
    {
        return null;
    }

    /** The applicant's own company, which a resubmission may keep its name from. */
    protected function ownCompanyId(): ?int
    {
        return null;
    }

    public function messages(): array
    {
        return [
            'last_name.regex' => 'Use letters, spaces, hyphens and apostrophes only.',
            'first_name.regex' => 'Use letters, spaces, hyphens and apostrophes only.',
            'middle_initial.regex' => 'Enter one letter, optionally followed by a period (e.g. A or A.).',
            'sex.in' => 'Choose Male or Female.',
            'email.email' => 'Enter a valid email address.',
            'email.regex' => 'Enter a valid email address.',
            'contact_no.regex' => 'Enter a Philippine mobile number like 09171234567 or +639171234567.',
            'birthday.date_format' => 'Enter a valid date.',
            'birthday.before_or_equal' => 'The birthday cannot be in the future.',
            'business_name.regex' => 'Use letters, numbers, spaces and common business characters only.',
            '*.required' => 'This field is required.',
            'valid_id.max' => 'The file must be 5 MB or smaller.',
            'dti_permit.max' => 'The file must be 5 MB or smaller.',
            'valid_id.mimes' => 'Upload a JPG, JPEG, PNG or PDF file.',
            'dti_permit.mimes' => 'Upload a JPG, JPEG, PNG or PDF file.',
            'valid_id.mimetypes' => 'Upload a JPG, JPEG, PNG or PDF file.',
            'dti_permit.mimetypes' => 'Upload a JPG, JPEG, PNG or PDF file.',
            'password.confirmed' => 'The passwords do not match.',
            'terms.accepted' => 'You must agree to the Terms and Privacy Policy.',
        ];
    }
}
