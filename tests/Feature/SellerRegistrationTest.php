<?php

namespace Tests\Feature;

use App\Mail\EmailVerificationCodeMail;
use App\Models\RegistrationReview;
use App\Models\User;
use App\Support\RegistrationChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Seller registration. Like buyers, sellers verify their email with a one-time code on the form's Account
 * step before the account exists; method names reference the TC-SEL test sheet.
 */
class SellerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Mail::fake();
        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(9, 0));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'role' => 'seller',
            'first_name' => 'Maria Clara',
            'last_name' => 'Santos',
            'sex' => 'Female',
            'email' => 'mariaclara.santos@example.com',
            'contact_no' => '09189876543',
            'birthday' => '1995-03-08',
            'password' => 'Tindahan@2026',
            'password_confirmation' => 'Tindahan@2026',
            'province' => 'Laguna',
            'municipality' => 'Santa Cruz',
            'barangay' => 'Poblacion',
            'postal_code' => '4009',
            'seller_type' => 'individual',
            'business_name' => 'Maria Clara Handicrafts',
            'line_of_business' => 'Home & Living',
            'tin' => '345-678-901-000',
            'id_upload' => $this->file('govt_id_front.jpg'),
            'confirmed' => '1',
            'terms' => '1',
            'registration_token' => (string) Str::uuid(),
        ], $overrides);
    }

    private function file(string $name, int $kilobytes = 200): UploadedFile
    {
        $mime = str_ends_with($name, '.pdf') ? 'application/pdf' : 'image/jpeg';

        return UploadedFile::fake()->create($name, $kilobytes, $mime);
    }

    /** Submit the form, first verifying its email the way the Account step does. */
    private function register(array $overrides = [])
    {
        $payload = $this->payload($overrides);
        $this->verifyEmail((string) $payload['email']);

        return $this->from('/register')->post('/register', $payload);
    }

    private function assertRejected(array $overrides, string $field): void
    {
        $this->register($overrides)->assertSessionHasErrors($field);
        $this->assertDatabaseMissing('users', ['role' => 'seller', 'business_name' => $overrides['business_name'] ?? 'Maria Clara Handicrafts']);
    }

    private function assertAccepted(array $overrides): void
    {
        $this->register($overrides)->assertSessionHasNoErrors()->assertRedirect('/login');
        User::where('role', 'seller')->delete();
    }

    private function sendCode(string $email)
    {
        return $this->postJson(route('register.email-code'), ['email' => $email, 'first_name' => 'Maria']);
    }

    private function lastCode(string $email): ?string
    {
        $email = strtolower(trim($email));

        return Mail::sent(EmailVerificationCodeMail::class, fn ($mail) => $mail->hasTo($email))->last()?->code;
    }

    private function checkCode(string $email, string $code)
    {
        return $this->postJson(route('register.email-code.verify'), ['email' => $email, 'code' => $code]);
    }

    private function verifyEmail(string $email): void
    {
        if ($this->sendCode($email)->isOk() && ($code = $this->lastCode($email))) {
            $this->checkCode($email, $code);
        }
        $this->travel(61)->seconds();
    }

    private function existingSeller(array $overrides = []): User
    {
        return User::create(array_merge([
            'role' => 'seller', 'status' => 'approved', 'first_name' => 'Existing', 'last_name' => 'Seller', 'sex' => 'Female',
            'email' => 'existing.seller@example.com', 'password' => bcrypt('Tindahan@2026'), 'contact_no' => '09170000001',
            'birthday' => '1990-01-01', 'age' => 36, 'province' => 'Laguna', 'municipality' => 'Santa Cruz', 'barangay' => 'Poblacion',
            'id_upload' => 'uploads/ids/id.jpg', 'business_name' => 'Existing Shop', 'line_of_business' => 'Fashion',
            'seller_type' => 'individual', 'tin' => '111-222-333-444', 'postal_code' => '4009',
        ], $overrides));
    }

    public function test_tc_sel_001_valid_registration_after_email_verification(): void
    {
        $this->sendCode('mariaclara.santos@example.com')->assertOk();
        $code = $this->lastCode('mariaclara.santos@example.com');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->checkCode('mariaclara.santos@example.com', $code)->assertOk()->assertJsonPath('verified', true);

        $this->from('/register')->post('/register', $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect('/login')
            ->assertSessionHas('success');

        $seller = User::where('email', 'mariaclara.santos@example.com')->sole();
        $this->assertSame('seller', $seller->role);
        $this->assertSame('pending', $seller->status);
        $this->assertNotNull($seller->email_verified_at);
        $this->assertSame('individual', $seller->seller_type);
        $this->assertSame('345-678-901-000', $seller->tin);
        $this->assertSame('4009', $seller->postal_code);
        Storage::disk('public')->assertExists($seller->id_upload);
    }

    public function test_registration_form_shows_the_seller_fields(): void
    {
        $this->get('/register')->assertOk()
            ->assertSee('name="seller_type"', false)
            ->assertSee('name="tin"', false)
            ->assertSee('name="postal_code"', false)
            ->assertSee('data-seller-types="sole_proprietorship"', false)
            ->assertSee('data-seller-types="corporation"', false)
            ->assertSee('data-seller-types="sole_proprietorship corporation"', false)
            ->assertSee('id="emailOtp"', false);
    }

    public function test_tc_sel_032_033_registration_is_blocked_until_the_email_is_verified(): void
    {
        $this->sendCode('mariaclara.santos@example.com');
        $this->checkCode('mariaclara.santos@example.com', '000000')->assertStatus(422)->assertJsonPath('verified', false);

        $this->from('/register')->post('/register', $this->payload())->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_tc_sel_002_003_required_fields(): void
    {
        $this->from('/register')->post('/register', ['role' => 'seller'])
            ->assertSessionHasErrors(['first_name', 'last_name', 'email', 'contact_no', 'password', 'province', 'postal_code',
                'seller_type', 'business_name', 'line_of_business', 'tin', 'id_upload', 'terms']);

        foreach (['first_name', 'last_name', 'business_name', 'seller_type', 'tin', 'postal_code', 'id_upload'] as $field) {
            $this->assertRejected([$field => ''], $field);
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_tc_sel_004_names_with_numbers_or_symbols_are_rejected(): void
    {
        $this->assertRejected(['first_name' => 'Maria Clara99'], 'first_name');
        $this->assertRejected(['last_name' => 'Dela Cruz@#'], 'last_name');
    }

    public function test_tc_sel_005_names_with_enye_and_punctuation_are_saved_as_typed(): void
    {
        $this->register(['first_name' => 'Niña Corazon', 'last_name' => "Peñaranda-D'Souza"])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['first_name' => 'Niña Corazon', 'last_name' => "Peñaranda-D'Souza"]);
    }

    public function test_tc_sel_006_name_length_limits(): void
    {
        $this->assertRejected(['first_name' => 'M'], 'first_name');
        $this->assertRejected(['first_name' => str_repeat('a', 51)], 'first_name');
        $this->assertAccepted(['first_name' => 'Mo']);
        $this->assertAccepted(['first_name' => str_repeat('a', 50)]);
    }

    public function test_tc_sel_007_invalid_email_formats_are_rejected(): void
    {
        foreach (['mariaclara.santosexample.com', 'mariaclara.santos@', '@example.com', 'mariaclara.santos@example', 'maria clara@example.com'] as $email) {
            $this->from('/register')->post('/register', $this->payload(['email' => $email]))->assertSessionHasErrors('email');
            $this->sendCode($email)->assertStatus(422);
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_tc_sel_008_009_010_duplicate_emails_and_trimming(): void
    {
        $this->existingSeller(['email' => 'mariaclara.santos@example.com']);

        $this->sendCode('MARIACLARA.SANTOS@EXAMPLE.COM')->assertStatus(422)->assertJsonValidationErrors('email');
        $this->from('/register')->post('/register', $this->payload(['email' => 'MARIACLARA.SANTOS@EXAMPLE.COM']))
            ->assertSessionHasErrors(['email' => 'This email is already in use. Log in instead, or use a different email.']);

        $this->register(['email' => '  seller.test01@example.com  ', 'contact_no' => '09189876544'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'seller.test01@example.com']);
    }

    public function test_tc_sel_011_012_mobile_number_format_and_length(): void
    {
        foreach (['0917ABC4567', '0917-123-456', '12345678901', '02-8123-4567', '0917000000', '091712345678'] as $mobile) {
            $this->assertRejected(['contact_no' => $mobile], 'contact_no');
        }
        $this->assertAccepted(['contact_no' => '09170000001']);
    }

    public function test_tc_sel_013_014_duplicate_mobile_numbers_including_plus_63_format(): void
    {
        $this->existingSeller(['contact_no' => '09189876543']);

        $this->assertRejected(['contact_no' => '09189876543'], 'contact_no');
        $this->assertRejected(['contact_no' => '+639189876543'], 'contact_no');
        $this->register(['contact_no' => '+639189876544'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'mariaclara.santos@example.com', 'contact_no' => '09189876544']);
    }

    public function test_tc_sel_015_016_017_password_rules(): void
    {
        $this->assertRejected(['password' => 'Tind@26', 'password_confirmation' => 'Tind@26'], 'password');
        foreach (['tindahan@2026', 'TINDAHAN@2026', 'Tindahan@abcd', 'Tindahan2026'] as $password) {
            $this->assertRejected(['password' => $password, 'password_confirmation' => $password], 'password');
        }
        $this->register(['password' => 'Tindahan@2026', 'password_confirmation' => 'Tindahan@2027'])
            ->assertSessionHasErrors(['password' => 'The passwords do not match.']);
        $this->assertAccepted(['password' => 'Tinda@26', 'password_confirmation' => 'Tinda@26']);
    }

    public function test_tc_sel_019_terms_must_be_accepted(): void
    {
        $this->assertRejected(['terms' => null], 'terms');
    }

    public function test_tc_sel_020_repeated_submission_creates_one_account(): void
    {
        $payload = $this->payload();
        $this->verifyEmail($payload['email']);

        foreach (range(1, 3) as $attempt) {
            $this->from('/register')->post('/register', [...$payload, 'id_upload' => $this->file('govt_id_front.jpg')])
                ->assertRedirect('/login')->assertSessionHasNoErrors();
        }

        $this->assertSame(1, User::where('email', $payload['email'])->count());
        $this->assertCount(1, Mail::sent(EmailVerificationCodeMail::class));
    }

    public function test_tc_sel_021_shop_name_already_used_by_another_seller(): void
    {
        $this->existingSeller(['business_name' => 'Maria Clara Handicrafts']);

        $this->register()->assertSessionHasErrors(['business_name' => 'This shop name is already taken. Choose a different name.']);
        $this->register(['business_name' => 'maria clara  handicrafts'])->assertSessionHasErrors('business_name');
        $this->assertSame(1, User::where('role', 'seller')->count());
    }

    public function test_tc_sel_022_shop_name_length_limits(): void
    {
        $this->assertRejected(['business_name' => 'MC'], 'business_name');
        $this->assertRejected(['business_name' => 'Maria Clara Handicrafts, Manila'], 'business_name');
        $this->assertAccepted(['business_name' => 'MCH']);
        $this->assertAccepted(['business_name' => 'Maria Clara Handicrafts Manila']);
    }

    public function test_tc_sel_023_required_documents_change_with_the_seller_type(): void
    {
        $this->assertAccepted(['seller_type' => 'individual']);

        $this->register(['seller_type' => 'sole_proprietorship'])->assertSessionHasErrors(['dti_certificate', 'bir_certificate']);
        $this->register([
            'seller_type' => 'sole_proprietorship',
            'dti_certificate' => $this->file('dti_certificate.pdf'),
            'bir_certificate' => $this->file('bir_2303.pdf'),
        ])->assertSessionHasNoErrors();
        $seller = User::where('role', 'seller')->sole();
        Storage::disk('public')->assertExists([$seller->dti_certificate, $seller->bir_certificate]);
        $this->assertNull($seller->sec_certificate);
        $this->assertSame(['identity', 'contact', 'address', 'seller_type', 'tin', 'dti_certificate', 'bir_certificate', 'business_details'],
            array_keys(RegistrationChecklist::for($seller)));
        $seller->delete();

        $this->register([
            'seller_type' => 'corporation',
            'sec_certificate' => $this->file('sec_certificate.pdf'),
            'bir_certificate' => $this->file('bir_2303.pdf'),
        ])->assertSessionHasNoErrors();
        $this->assertSame('corporation', User::where('role', 'seller')->value('seller_type'));
    }

    public function test_tc_sel_024_corporation_without_sec_certificate_is_blocked(): void
    {
        $this->register(['seller_type' => 'corporation', 'bir_certificate' => $this->file('bir_2303.pdf')])
            ->assertSessionHasErrors(['sec_certificate' => 'Corporations must upload their SEC Certificate.']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_tc_sel_025_tin_format_and_length(): void
    {
        foreach (['123-456-789-00', '123-456-789-0001', '123-456-78A-000'] as $tin) {
            $this->assertRejected(['tin' => $tin], 'tin');
        }
        $this->register(['tin' => '345 678 901 000'])->assertSessionHasNoErrors();
        $this->assertSame('345-678-901-000', User::where('role', 'seller')->value('tin'));
    }

    public function test_tc_sel_026_tin_already_registered(): void
    {
        $this->existingSeller(['tin' => '123-456-789-000']);

        $this->register(['tin' => '123456789000'])->assertSessionHasErrors(['tin' => 'This TIN is already registered to another account.']);
    }

    public function test_tc_sel_028_postal_code_length_and_format(): void
    {
        foreach (['112', '11210', '11A1'] as $postalCode) {
            $this->assertRejected(['postal_code' => $postalCode], 'postal_code');
        }
        $this->assertAccepted(['postal_code' => '1121']);
    }

    public function test_tc_sel_030_031_document_file_type_and_size(): void
    {
        foreach (['price_list.docx', 'store_banner.gif', 'installer.exe'] as $name) {
            $this->assertRejected(['id_upload' => UploadedFile::fake()->create($name, 300)], 'id_upload');
        }
        $this->assertRejected(['id_upload' => $this->file('size_5.1MB.jpg', 5222)], 'id_upload');
        $this->assertAccepted(['id_upload' => $this->file('size_4.9MB.jpg', 5018)]);
        $this->assertAccepted(['id_upload' => $this->file('size_5.0MB.jpg', 5120)]);
    }

    public function test_tc_sel_034_malformed_codes_are_rejected(): void
    {
        $this->sendCode('mariaclara.santos@example.com');

        foreach (['48291', '4829137', '48A91B', ''] as $code) {
            $this->checkCode('mariaclara.santos@example.com', $code)->assertStatus(422)->assertJsonValidationErrors('code');
        }
    }

    public function test_tc_sel_035_expired_code_is_rejected(): void
    {
        $this->sendCode('mariaclara.santos@example.com');
        $code = $this->lastCode('mariaclara.santos@example.com');
        $this->travel(5)->minutes();
        $this->travel(1)->seconds();

        $this->checkCode('mariaclara.santos@example.com', $code)->assertStatus(422)
            ->assertJsonPath('message', 'This code has expired. Send a new code and try again.');
    }

    public function test_tc_sel_036_resend_replaces_the_previous_code(): void
    {
        $this->sendCode('mariaclara.santos@example.com');
        $first = $this->lastCode('mariaclara.santos@example.com');
        $this->travel(61)->seconds();
        $this->sendCode('mariaclara.santos@example.com')->assertOk();
        $second = $this->lastCode('mariaclara.santos@example.com');

        if ($first !== $second) {
            $this->checkCode('mariaclara.santos@example.com', $first)->assertStatus(422);
        }
        $this->checkCode('mariaclara.santos@example.com', $second)->assertOk()->assertJsonPath('verified', true);
    }

    public function test_tc_sel_037_no_account_exists_before_the_email_is_verified(): void
    {
        $this->sendCode('mariaclara.santos@example.com');

        $this->post('/login', ['email' => 'mariaclara.santos@example.com', 'password' => 'Tindahan@2026'])
            ->assertSessionHasErrors(['email' => 'Invalid email or password.']);
        $this->assertGuest();
    }

    public function test_tc_sel_042_rejected_seller_corrects_documents_and_resubmits(): void
    {
        Notification::fake();
        $admin = $this->existingSeller(['role' => 'admin', 'email' => 'admin@example.com', 'contact_no' => '09170000009', 'tin' => null, 'seller_type' => null, 'business_name' => null]);
        $seller = $this->existingSeller([
            'status' => 'disapproved', 'email' => 'mariaclara.santos@example.com', 'seller_type' => 'sole_proprietorship',
            'dti_certificate' => 'uploads/permits/dti_blurred.jpg', 'bir_certificate' => 'uploads/permits/bir.pdf',
        ]);
        RegistrationReview::create([
            'user_id' => $seller->id, 'reviewed_by' => $admin->id, 'decision' => RegistrationReview::DISAPPROVED,
            'reason' => 'DTI certificate is blurred and the business name cannot be read.', 'reviewed_at' => now(),
        ]);

        $this->post('/login', ['email' => $seller->email, 'password' => 'Tindahan@2026'])
            ->assertRedirect(route('registration.resubmit'));
        $this->assertGuest();
        $this->get(route('registration.resubmit'))->assertOk()
            ->assertSee('DTI certificate is blurred and the business name cannot be read.')
            ->assertSee('DTI Certificate of Business Name Registration')
            ->assertDontSee('SEC Certificate of Incorporation');

        $this->from(route('registration.resubmit'))->post(route('registration.resubmit.store'))
            ->assertSessionHasErrors('documents');

        $this->post(route('registration.resubmit.store'), ['dti_certificate' => $this->file('dti_certificate_clear.pdf', 1000)])
            ->assertRedirect('/login')->assertSessionHas('success');

        $seller->refresh();
        $this->assertSame('pending', $seller->status);
        $this->assertNotSame('uploads/permits/dti_blurred.jpg', $seller->dti_certificate);
        Storage::disk('public')->assertExists($seller->dti_certificate);
        $this->assertDatabaseHas('user_status_histories', ['user_id' => $seller->id, 'from_status' => 'disapproved', 'to_status' => 'pending']);

        // The application is back in the admin's pending list and can be decided again.
        $this->actingAs($admin)->get(route('admin.registrations'))->assertSee($seller->email);
        $this->actingAs($admin)->patch(route('admin.registrations.approve', $seller), [
            'checklist' => array_fill_keys(array_keys(RegistrationChecklist::for($seller)), '1'),
        ])->assertRedirect();
        $this->assertSame('approved', $seller->fresh()->status);
    }

    public function test_resubmission_page_requires_a_rejected_seller_login(): void
    {
        $this->get(route('registration.resubmit'))->assertRedirect('/login');
        $this->post(route('registration.resubmit.store'))->assertRedirect('/login');

        $courier = $this->existingSeller(['role' => 'courier', 'status' => 'disapproved', 'tin' => null, 'seller_type' => null, 'business_name' => null]);
        $this->post('/login', ['email' => $courier->email, 'password' => 'Tindahan@2026'])
            ->assertSessionHasErrors(['email' => 'Your registration was disapproved. Please contact support.']);
    }
}
