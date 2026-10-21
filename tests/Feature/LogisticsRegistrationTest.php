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
 * Logistics provider registration on the logistics portal. Like buyers and sellers, applicants verify their
 * email with a 6-digit code on the form's Account step before the account exists, then wait for admin
 * approval; method names reference the TC-LOG test sheet.
 */
class LogisticsRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private string $portal;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Mail::fake();
        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(9, 0));
        $this->portal = 'https://'.config('logistics.superadmin_domain');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'role' => 'logistics',
            'provider_type' => 'company',
            'first_name' => 'Carlos',
            'last_name' => 'Reyes',
            'sex' => 'Male',
            'email' => 'operations@bilishatid.example.com',
            'contact_no' => '09171234567',
            'birthday' => '1988-05-14',
            'password' => 'Hatid@2026',
            'password_confirmation' => 'Hatid@2026',
            'business_name' => 'Bilis Hatid Express Inc.',
            'sec_registration_no' => 'CS202012345',
            'tin' => '234-567-890-000',
            'service_areas' => ['NCR', 'Region III', 'Region IV-A'],
            'region' => 'National Capital Region (NCR)',
            'province' => 'Metro Manila',
            'municipality' => 'Pasig City',
            'barangay' => 'Kapitolyo',
            'postal_code' => '1603',
            'sec_certificate' => $this->file('sec_certificate.pdf', 1000),
            'bir_certificate' => $this->file('bir_2303.pdf', 1100),
            'business_permit' => $this->file('mayors_permit.pdf', 1300),
            'id_upload' => $this->file('govt_id_front.png', 1200),
            'confirmed' => '1',
            'terms' => '1',
            'registration_token' => (string) Str::uuid(),
        ], $overrides);
    }

    private function file(string $name, int $kilobytes = 200): UploadedFile
    {
        $mime = match (pathinfo($name, PATHINFO_EXTENSION)) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            default => 'application/octet-stream',
        };

        return UploadedFile::fake()->create($name, $kilobytes, $mime);
    }

    /** Submit the form, first verifying its email the way the Account step does. */
    private function register(array $overrides = [])
    {
        $payload = $this->payload($overrides);
        $this->verifyEmail((string) $payload['email']);

        return $this->from("{$this->portal}/register")->post("{$this->portal}/register", $payload);
    }

    private function sendCode(string $email = 'operations@bilishatid.example.com')
    {
        return $this->postJson("{$this->portal}/register/email-code", ['email' => $email, 'first_name' => 'Carlos']);
    }

    private function checkCode(string $code, string $email = 'operations@bilishatid.example.com')
    {
        return $this->postJson("{$this->portal}/register/email-code/verify", ['email' => $email, 'code' => $code]);
    }

    private function lastCode(string $email = 'operations@bilishatid.example.com'): ?string
    {
        return Mail::sent(EmailVerificationCodeMail::class, fn ($mail) => $mail->hasTo(strtolower(trim($email))))->last()?->code;
    }

    private function verifyEmail(string $email): void
    {
        if ($this->sendCode($email)->isOk() && ($code = $this->lastCode($email))) {
            $this->checkCode($code, $email);
        }
        $this->travel(61)->seconds();
    }

    private function assertRejected(array $overrides, string $field): void
    {
        $this->register($overrides)->assertSessionHasErrors($field);
        $this->assertDatabaseMissing('users', ['email' => $overrides['email'] ?? 'operations@bilishatid.example.com']);
    }

    private function assertAccepted(array $overrides): void
    {
        $this->register($overrides)->assertSessionHasNoErrors()->assertRedirect('/login');
        User::where('role', 'logistics')->delete();
    }

    private function existingLogistics(array $overrides = []): User
    {
        return tap(User::create(array_merge([
            'role' => 'logistics', 'provider_type' => 'company', 'status' => 'approved', 'first_name' => 'Ana', 'last_name' => 'Lim', 'sex' => 'Female',
            'email' => 'existing@logistics.example.com', 'password' => bcrypt('Hatid@2026'), 'contact_no' => '09285551234',
            'birthday' => '1985-01-01', 'age' => 41, 'region' => 'National Capital Region (NCR)', 'province' => 'Metro Manila',
            'municipality' => 'Pasig City', 'barangay' => 'Kapitolyo', 'postal_code' => '1603', 'id_upload' => 'uploads/ids/id.jpg',
            'business_name' => 'Existing Freight Corp.', 'sec_registration_no' => 'CS202000001', 'tin' => '234-567-890-001',
            'service_areas' => ['NCR'], 'sec_certificate' => 'uploads/permits/sec.pdf', 'bir_certificate' => 'uploads/permits/bir.pdf',
            'business_permit' => 'uploads/permits/permit.pdf',
        ], $overrides)), fn (User $user) => $user->forceFill(['email_verified_at' => now()])->save());
    }

    private function admin(): User
    {
        return User::create([
            'role' => 'admin', 'status' => 'approved', 'first_name' => 'Site', 'last_name' => 'Admin', 'sex' => 'Female',
            'email' => 'admin@example.com', 'password' => bcrypt('Admin@2026'), 'contact_no' => '09170000009',
            'birthday' => '1990-01-01', 'age' => 36, 'province' => 'Laguna', 'municipality' => 'Santa Cruz', 'barangay' => 'Poblacion',
            'id_upload' => 'uploads/ids/admin.jpg',
        ]);
    }

    public function test_tc_log_001_valid_registration_after_email_verification(): void
    {
        $this->sendCode()->assertOk();
        $code = $this->lastCode();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->checkCode($code)->assertOk()->assertJsonPath('verified', true);

        $this->from("{$this->portal}/register")->post("{$this->portal}/register", $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect('/login')
            ->assertSessionHas('success');

        $applicant = User::where('email', 'operations@bilishatid.example.com')->sole();
        $this->assertSame('pending', $applicant->status);
        $this->assertNotNull($applicant->email_verified_at);
        $this->assertSame('CS202012345', $applicant->sec_registration_no);
        $this->assertSame(['NCR', 'Region III', 'Region IV-A'], $applicant->service_areas);
        $this->assertSame('National Capital Region (NCR)', $applicant->region);
        $this->assertSame('1603', $applicant->postal_code);
        foreach (['sec_certificate', 'bir_certificate', 'business_permit', 'id_upload'] as $document) {
            Storage::disk('public')->assertExists($applicant->{$document});
        }
        $this->assertGuest();
    }

    public function test_registration_form_shows_the_logistics_fields(): void
    {
        $this->get("{$this->portal}/register")->assertOk()
            ->assertSee('name="sec_registration_no"', false)
            ->assertSee('name="region"', false)
            ->assertSee('name="service_areas[]"', false)
            ->assertSee('Region IV-A (CALABARZON)')
            ->assertSee("Mayor's / Business Permit", false)
            ->assertSee('name="bir_certificate"', false);
    }

    public function test_tc_log_002_003_required_fields(): void
    {
        $this->from("{$this->portal}/register")->post("{$this->portal}/register", ['role' => 'logistics', 'registration_token' => (string) Str::uuid()])
            ->assertSessionHasErrors(['first_name', 'email', 'password', 'contact_no', 'business_name', 'tin', 'service_areas', 'region', 'province', 'municipality', 'barangay', 'postal_code', 'id_upload', 'bir_certificate', 'business_permit', 'provider_type', 'terms']);
        Mail::assertNothingSent();

        foreach (['business_name', 'sec_registration_no', 'tin', 'region', 'province', 'municipality', 'barangay', 'postal_code', 'service_areas', 'sec_certificate', 'bir_certificate', 'business_permit', 'id_upload'] as $field) {
            $this->assertRejected([$field => null], $field);
        }
        $this->assertSame(0, User::count());
    }

    public function test_tc_log_004_005_006_contact_person_names(): void
    {
        $this->assertRejected(['first_name' => 'Carlos Antonio7'], 'first_name');
        $this->assertRejected(['last_name' => 'Dela Cruz@#'], 'last_name');
        $this->assertRejected(['first_name' => 'C'], 'first_name');
        $this->assertRejected(['first_name' => str_repeat('a', 51)], 'first_name');
        $this->assertAccepted(['first_name' => 'Co']);
        $this->assertAccepted(['first_name' => str_repeat('a', 50)]);

        $this->register(['first_name' => 'Iñigo Rafael', 'last_name' => "Muñoz-D'Angelo"])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['first_name' => 'Iñigo Rafael', 'last_name' => "Muñoz-D'Angelo"]);
    }

    public function test_tc_log_007_to_010_email_format_duplicates_and_trimming(): void
    {
        foreach (['operations.bilishatidexample.com', 'operations@', '@example.com', 'operations@bilishatid', 'operations@bilishatid.example .com'] as $email) {
            $this->assertRejected(['email' => $email], 'email');
        }

        $this->existingLogistics(['email' => 'operations@bilishatid.example.com']);
        $this->register(['email' => 'operations@bilishatid.example.com', 'business_name' => 'Other Co.', 'sec_registration_no' => 'CS209999999', 'tin' => '999-999-999-999'])->assertSessionHasErrors('email');
        $this->register(['email' => 'OPERATIONS@BILISHATID.EXAMPLE.COM', 'business_name' => 'Other Co.', 'sec_registration_no' => 'CS209999999', 'tin' => '999-999-999-999'])->assertSessionHasErrors('email');

        $this->register(['email' => '  logistics.test01@example.com  '])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'logistics.test01@example.com']);
    }

    public function test_tc_log_011_to_014_mobile_numbers(): void
    {
        foreach (['0917ABC4567', '0917-123-456', '12345678901', '02-8123-4567', '0917123456', '091712345678'] as $number) {
            $this->assertRejected(['contact_no' => $number], 'contact_no');
        }
        $this->assertAccepted(['contact_no' => '09170000001']);

        $this->existingLogistics();
        $this->assertRejected(['contact_no' => '09285551234'], 'contact_no');
        $this->assertRejected(['contact_no' => '+639285551234'], 'contact_no');
    }

    public function test_tc_log_015_to_017_password_rules(): void
    {
        $this->assertRejected(['password' => 'Hati@26', 'password_confirmation' => 'Hati@26'], 'password');
        foreach (['hatid@2026', 'HATID@2026', 'Hatid@abcd', 'Hatid2026'] as $password) {
            $this->assertRejected(['password' => $password, 'password_confirmation' => $password], 'password');
        }
        $this->assertRejected(['password_confirmation' => 'Hatid@2027'], 'password');
        $this->assertAccepted(['password' => 'Hatid@26', 'password_confirmation' => 'Hatid@26']);
    }

    public function test_tc_log_019_terms_must_be_accepted(): void
    {
        $this->assertRejected(['terms' => null], 'terms');
    }

    public function test_tc_log_020_repeated_submission_creates_one_account_and_sends_one_code(): void
    {
        $payload = $this->payload();
        $this->verifyEmail($payload['email']);
        $this->post("{$this->portal}/register", $payload)->assertRedirect('/login');
        $this->post("{$this->portal}/register", $payload)->assertRedirect('/login');
        $this->post("{$this->portal}/register", $payload)->assertRedirect('/login');

        $this->assertSame(1, User::where('email', 'operations@bilishatid.example.com')->count());
        Mail::assertSent(EmailVerificationCodeMail::class, 1);
    }

    public function test_tc_log_021_company_name_already_registered(): void
    {
        $this->existingLogistics(['business_name' => 'Bilis Hatid Express Inc.']);
        $this->assertRejected([], 'business_name');
        $this->assertRejected(['business_name' => 'bilis hatid express inc.'], 'business_name');
    }

    public function test_tc_log_022_023_sec_registration_number(): void
    {
        foreach (['CS20201234', '202012345CS', 'CS2020-12345', '12345678901'] as $number) {
            $this->assertRejected(['sec_registration_no' => $number], 'sec_registration_no');
        }
        $this->assertAccepted(['sec_registration_no' => 'cs202012345']);

        $this->existingLogistics(['sec_registration_no' => 'CS202012345']);
        $this->assertRejected([], 'sec_registration_no');
    }

    public function test_only_company_logistics_registrations_are_allowed(): void
    {
        $this->get("{$this->portal}/register")->assertOk()
            ->assertSee('Logistics accounts are registered as companies.')
            ->assertDontSee('Individual Logistics Provider');

        $this->register(['provider_type' => 'individual'])->assertSessionHasErrors('provider_type');
        $this->assertDatabaseMissing('users', ['email' => 'operations@bilishatid.example.com']);
    }

    public function test_tc_log_024_several_service_areas_are_saved(): void
    {
        $this->assertRejected(['service_areas' => []], 'service_areas');
        $this->assertRejected(['service_areas' => ['Atlantis']], 'service_areas.0');
        $this->register()->assertSessionHasNoErrors();
        $this->assertSame(['NCR', 'Region III', 'Region IV-A'], User::where('role', 'logistics')->sole()->service_areas);
    }

    public function test_tc_log_025_026_tin_format_and_duplicates(): void
    {
        foreach (['123-456-789-00', '345-678-901-0001', '123-456-78A-000', 'ABCDEFGHIJKL'] as $tin) {
            $this->assertRejected(['tin' => $tin], 'tin');
        }
        $this->assertAccepted(['tin' => '234567890000']);

        $this->existingLogistics(['tin' => '234-567-890-000']);
        $this->assertRejected([], 'tin');
    }

    public function test_tc_log_028_postal_code(): void
    {
        foreach (['160', '16030', '16A3'] as $code) {
            $this->assertRejected(['postal_code' => $code], 'postal_code');
        }
        $this->assertAccepted(['postal_code' => '1603']);
    }

    public function test_tc_log_029_to_031_document_types_and_sizes(): void
    {
        $this->assertAccepted(['id_upload' => $this->file('govt_id_front.jpg', 1200)]);
        foreach (['price_list.docx', 'store_banner.gif', 'installer.exe'] as $name) {
            $this->assertRejected(['business_permit' => $this->file($name, 300)], 'business_permit');
        }
        $this->assertAccepted(['business_permit' => $this->file('size_4_9MB.pdf', 4900)]);
        $this->assertAccepted(['business_permit' => $this->file('size_5_0MB.pdf', 5120)]);
        $this->assertRejected(['business_permit' => $this->file('size_5_1MB.pdf', 5121)], 'business_permit');
    }

    public function test_tc_log_032_037_registration_is_blocked_until_the_email_is_verified(): void
    {
        $this->from("{$this->portal}/register")->post("{$this->portal}/register", $this->payload())
            ->assertSessionHasErrors(['email' => 'Verify your email with the 6-digit code we send you before submitting.']);
        $this->assertSame(0, User::count());

        $this->sendCode()->assertOk();
        $this->checkCode($this->lastCode())->assertOk()->assertJsonPath('verified', true);
        $this->from("{$this->portal}/register")->post("{$this->portal}/register", $this->payload())->assertSessionHasNoErrors();
        $this->assertSame('pending', User::where('role', 'logistics')->sole()->status);
    }

    public function test_tc_log_033_034_incorrect_or_malformed_codes_are_rejected(): void
    {
        $this->sendCode()->assertOk();
        $wrong = $this->lastCode() === '000000' ? '111111' : '000000';

        $this->checkCode($wrong)->assertStatus(422)->assertJsonPath('message', 'That code is incorrect. You have 4 tries left.');
        foreach (['48291', '4829137', '48A91B', ''] as $code) {
            $this->checkCode($code)->assertStatus(422)->assertJsonValidationErrors('code');
        }
        $this->assertSame(0, User::count());
    }

    public function test_tc_log_035_expired_code_is_rejected(): void
    {
        $this->sendCode()->assertOk();
        $code = $this->lastCode();
        $this->travel(5 * 60 + 1)->seconds();

        $this->checkCode($code)->assertStatus(422)->assertJsonPath('message', 'This code has expired. Send a new code and try again.');
    }

    public function test_tc_log_036_resend_replaces_the_previous_code(): void
    {
        $this->sendCode()->assertOk();
        $first = $this->lastCode();

        $this->sendCode()->assertStatus(429);
        $this->travel(61)->seconds();
        $this->sendCode()->assertOk();
        $second = $this->lastCode();
        Mail::assertSent(EmailVerificationCodeMail::class, 2);

        if ($first !== $second) {
            $this->checkCode($first)->assertStatus(422);
        }
        $this->checkCode($second)->assertOk()->assertJsonPath('verified', true);
    }

    public function test_tc_log_038_pending_provider_cannot_log_in(): void
    {
        $this->existingLogistics(['status' => 'pending']);
        $this->post("{$this->portal}/login", ['email' => 'existing@logistics.example.com', 'password' => 'Hatid@2026'])
            ->assertSessionHasErrors(['email' => 'Your account is still pending approval. Please check your email.']);
        $this->assertGuest();
    }

    public function test_tc_log_039_040_application_is_in_the_admin_queue_and_can_be_approved(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $this->register();
        $applicant = User::where('role', 'logistics')->sole();

        $this->actingAs($admin)->get(route('admin.registrations'))->assertSee($applicant->email);
        $this->actingAs($admin)->get(route('admin.registrations.show', $applicant))->assertOk()
            ->assertSee('CS202012345')->assertSee('234-567-890-000')
            ->assertSee('Region IV-A (CALABARZON)')
            ->assertSee('SEC Certificate of Incorporation')->assertSee('BIR Certificate of Registration (Form 2303)')
            ->assertSee('Mayor’s / Business Permit');

        $this->actingAs($admin)->patch(route('admin.registrations.approve', $applicant), [
            'checklist' => array_fill_keys(array_keys(RegistrationChecklist::for($applicant)), '1'),
        ])->assertRedirect();
        $this->assertSame('approved', $applicant->fresh()->status);
    }

    public function test_tc_log_041_042_rejected_provider_sees_the_reason_and_resubmits(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $applicant = $this->existingLogistics(['status' => 'disapproved']);
        RegistrationReview::create([
            'user_id' => $applicant->id, 'reviewed_by' => $admin->id, 'decision' => RegistrationReview::DISAPPROVED,
            'reason' => "Mayor's permit expired on 12/31/2025.", 'reviewed_at' => now(),
        ]);

        $this->post("{$this->portal}/login", ['email' => $applicant->email, 'password' => 'Hatid@2026'])
            ->assertRedirectContains('/registration/resubmit');
        $this->assertGuest();
        $this->get("{$this->portal}/registration/resubmit")->assertOk()
            ->assertSee('Mayor&#039;s permit expired on 12/31/2025.', false)
            ->assertSee('Mayor’s / Business Permit')
            ->assertSee('SEC Certificate of Incorporation');

        $this->post("{$this->portal}/registration/resubmit", ['business_permit' => $this->file('mayors_permit_2026.pdf', 1200)])
            ->assertRedirect('/login')->assertSessionHas('success');

        $applicant->refresh();
        $this->assertSame('pending', $applicant->status);
        $this->assertNotSame('uploads/permits/permit.pdf', $applicant->business_permit);
        Storage::disk('public')->assertExists($applicant->business_permit);
        $this->actingAs($admin)->get(route('admin.registrations'))->assertSee($applicant->email);
    }
}
