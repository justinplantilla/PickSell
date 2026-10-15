<?php

namespace Tests\Feature;

use App\Mail\EmailVerificationCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Buyer registration. The email is verified with a one-time code on the form's Account step, before the
 * account exists; method names reference the TC-BUY test sheet.
 */
class BuyerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Mail::fake();
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(9, 0));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'role' => 'buyer',
            'first_name' => 'Juan Miguel',
            'last_name' => 'Dela Cruz',
            'sex' => 'Male',
            'email' => 'juanmiguel.delacruz@example.com',
            'contact_no' => '09171234567',
            'birthday' => '1998-05-14',
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
            'province' => 'Laguna',
            'municipality' => 'Santa Cruz',
            'barangay' => 'Poblacion',
            'id_upload' => UploadedFile::fake()->create('id.jpg', 100, 'image/jpeg'),
            'confirmed' => '1',
            'terms' => '1',
            'registration_token' => (string) Str::uuid(),
        ], $overrides);
    }

    /** Submit the form, first verifying its email the way the Account step does. */
    private function register(array $overrides = [])
    {
        $payload = $this->payload($overrides);
        $this->verifyEmail((string) $payload['email']);

        return $this->from('/register')->post('/register', $payload);
    }

    private function sendCode(string $email)
    {
        return $this->postJson(route('register.email-code'), ['email' => $email, 'first_name' => 'Juan']);
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

    public function test_tc_buy_001_valid_registration_after_email_verification(): void
    {
        $this->sendCode('juanmiguel.delacruz@example.com')->assertOk()->assertJsonPath('wait', 60);
        $code = $this->lastCode('juanmiguel.delacruz@example.com');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertSame(0, User::count(), 'No account exists until the form is submitted.');

        $this->checkCode('juanmiguel.delacruz@example.com', $code)->assertOk()->assertJsonPath('verified', true);
        $this->from('/register')->post('/register', $this->payload())->assertRedirect('/login');

        $user = User::sole();
        $this->assertSame('pending', $user->status);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_registration_is_blocked_until_the_submitted_email_is_verified(): void
    {
        $this->from('/register')->post('/register', $this->payload())
            ->assertSessionHasErrors(['email' => 'Verify your email with the 6-digit code we send you before submitting.']);

        $this->verifyEmail('someone.else@example.com');
        $this->from('/register')->post('/register', $this->payload())->assertSessionHasErrors('email');
        $this->assertSame(0, User::count());
    }

    public function test_tc_buy_002_003_required_fields(): void
    {
        $this->from('/register')->post('/register', ['registration_token' => (string) Str::uuid()])
            ->assertSessionHasErrors(['first_name', 'last_name', 'email', 'contact_no', 'birthday', 'password', 'terms']);

        foreach (['first_name', 'last_name', 'email', 'contact_no', 'birthday', 'password', 'password_confirmation'] as $field) {
            $this->register([$field => ''])->assertSessionHasErrors($field === 'password_confirmation' ? 'password' : $field);
        }
        $this->assertSame(0, User::count());
    }

    public function test_tc_buy_004_names_with_numbers_or_symbols_are_rejected(): void
    {
        $this->register(['first_name' => 'Juan Miguel123', 'last_name' => 'Dela Cruz@#'])
            ->assertSessionHasErrors(['first_name', 'last_name']);
        $this->assertSame(0, User::count());
    }

    public function test_tc_buy_005_names_with_enye_and_punctuation_are_saved_as_typed(): void
    {
        $this->register(['first_name' => 'Niño Angelo', 'last_name' => 'Peñaflor-O\'Hara'])->assertSessionHasNoErrors();

        $user = User::sole();
        $this->assertSame('Niño Angelo', $user->first_name);
        $this->assertSame('Peñaflor-O\'Hara', $user->last_name);
    }

    public function test_tc_buy_006_name_length_limits(): void
    {
        $fifty = str_repeat('a', 50);
        $this->register(['first_name' => 'J'])->assertSessionHasErrors('first_name');
        $this->register(['first_name' => $fifty.'a'])->assertSessionHasErrors('first_name');
        $this->register(['first_name' => 'Jo', 'email' => 'two@example.com', 'contact_no' => '09170000001'])->assertSessionHasNoErrors();
        $this->register(['first_name' => $fifty, 'email' => 'fifty@example.com', 'contact_no' => '09170000002'])->assertSessionHasNoErrors();
        $this->assertSame(2, User::count());
    }

    public function test_tc_buy_007_008_date_of_birth_limits(): void
    {
        $this->register(['birthday' => '2008-10-16'])->assertSessionHasErrors(['birthday' => 'You must be at least 18 years old to register.']);
        $this->register(['birthday' => '2026-10-16'])->assertSessionHasErrors(['birthday' => 'The date of birth cannot be in the future.']);
        $this->register(['birthday' => '2008-10-15', 'email' => 'eighteen@example.com', 'contact_no' => '09170000003'])->assertSessionHasNoErrors();
        $this->register(['birthday' => '2008-10-14', 'email' => 'older@example.com', 'contact_no' => '09170000004'])->assertSessionHasNoErrors();
    }

    public function test_tc_buy_009_invalid_email_formats_are_rejected(): void
    {
        foreach (['juanmiguel.delacruzexample.com', 'juanmiguel.delacruz@', '@example.com', 'juanmiguel.delacruz@example', 'juanmiguel delacruz@example.com'] as $email) {
            $this->sendCode($email)->assertUnprocessable()->assertJsonValidationErrors('email');
            $this->register(['email' => $email])->assertSessionHasErrors('email');
        }
        Mail::assertNothingSent();
        $this->assertSame(0, User::count());
    }

    public function test_tc_buy_010_011_012_duplicate_emails_and_trimming(): void
    {
        $this->register(['email' => '  JuanMiguel.DelaCruz@Example.com  '])->assertSessionHasNoErrors();
        $this->assertSame('juanmiguel.delacruz@example.com', User::sole()->email);

        $this->sendCode('juanmiguel.delacruz@example.com')
            ->assertJsonValidationErrors(['email' => 'This email is already in use. Log in instead, or use a different email.']);
        $this->sendCode('JUANMIGUEL.DELACRUZ@EXAMPLE.COM')->assertJsonValidationErrors('email');
        $this->register(['contact_no' => '09170000005'])->assertSessionHasErrors('email');
        $this->assertSame(1, User::count());
    }

    public function test_tc_buy_013_014_mobile_number_format_and_length(): void
    {
        foreach (['0917ABC4567', '0917-123-456', '12345678901', '02-8123-4567'] as $number) {
            $this->register(['contact_no' => $number])->assertSessionHasErrors('contact_no');
        }
        $this->register(['contact_no' => '0917123456'])->assertSessionHasErrors(['contact_no' => 'The mobile number must be exactly 11 digits.']);
        $this->register(['contact_no' => '091712345678'])->assertSessionHasErrors(['contact_no' => 'The mobile number must be exactly 11 digits.']);
        $this->register(['contact_no' => '09170000001'])->assertSessionHasNoErrors();
    }

    public function test_tc_buy_015_016_duplicate_mobile_numbers_including_plus_63_format(): void
    {
        $this->register()->assertSessionHasNoErrors();

        $this->register(['email' => 'second@example.com'])
            ->assertSessionHasErrors(['contact_no' => 'This mobile number is already in use.']);
        $this->register(['email' => 'third@example.com', 'contact_no' => '+639171234567'])
            ->assertSessionHasErrors(['contact_no' => 'This mobile number is already in use.']);

        $this->register(['email' => 'fourth@example.com', 'contact_no' => '+63 918 765 4321'])->assertSessionHasNoErrors();
        $this->assertSame('09187654321', User::where('email', 'fourth@example.com')->value('contact_no'));
    }

    public function test_tc_buy_017_018_019_password_rules(): void
    {
        $this->register(['password' => 'Bili@26', 'password_confirmation' => 'Bili@26'])->assertSessionHasErrors('password');

        $cases = [
            'mamimili@2026' => 'uppercase and one lowercase',
            'MAMIMILI@2026' => 'uppercase and one lowercase',
            'Mamimili@abcd' => 'one number',
            'Mamimili2026' => 'one symbol',
        ];
        foreach ($cases as $password => $message) {
            $errors = $this->register(['password' => $password, 'password_confirmation' => $password])
                ->assertSessionHasErrors('password')
                ->getSession()->get('errors')->get('password');
            $this->assertStringContainsString($message, implode(' ', $errors), "Password {$password}");
        }

        $this->register(['password_confirmation' => 'Mamimili@2027'])->assertSessionHasErrors(['password' => 'The passwords do not match.']);
        $this->register(['password' => 'Bili@202', 'password_confirmation' => 'Bili@202'])->assertSessionHasNoErrors();
    }

    public function test_tc_buy_021_terms_must_be_accepted(): void
    {
        $this->register(['terms' => null])
            ->assertSessionHasErrors(['terms' => 'You must accept the Terms of Service and Privacy Policy to register.']);
        $this->assertSame(0, User::count());
        $this->get(route('terms'))->assertOk();
        $this->get(route('privacy'))->assertOk();
    }

    public function test_tc_buy_022_repeated_submission_creates_one_account(): void
    {
        $payload = $this->payload();
        $this->verifyEmail($payload['email']);
        $this->post('/register', $payload)->assertRedirect('/login');
        $payload['id_upload'] = UploadedFile::fake()->create('id.jpg', 100, 'image/jpeg');
        $this->post('/register', $payload)->assertRedirect('/login');

        $this->assertSame(1, User::count());
    }

    public function test_tc_buy_024_025_incorrect_or_malformed_codes_are_rejected(): void
    {
        $this->sendCode('juanmiguel.delacruz@example.com')->assertOk();
        $wrong = $this->lastCode('juanmiguel.delacruz@example.com') === '000000' ? '111111' : '000000';

        $this->checkCode('juanmiguel.delacruz@example.com', $wrong)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'That code is incorrect. You have 4 tries left.');
        foreach (['48291', '4829137', '48A91B', ''] as $code) {
            $this->checkCode('juanmiguel.delacruz@example.com', $code)->assertJsonValidationErrors('code');
        }
        $this->from('/register')->post('/register', $this->payload())->assertSessionHasErrors('email');
    }

    public function test_tc_buy_026_expired_code_is_rejected(): void
    {
        $this->sendCode('juanmiguel.delacruz@example.com');
        $code = $this->lastCode('juanmiguel.delacruz@example.com');
        $this->travel(5)->minutes();
        $this->travel(1)->seconds();

        $this->checkCode('juanmiguel.delacruz@example.com', $code)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This code has expired. Send a new code and try again.');
    }

    public function test_tc_buy_027_resend_waits_a_minute_and_replaces_the_previous_code(): void
    {
        $this->sendCode('juanmiguel.delacruz@example.com')->assertOk();
        $first = $this->lastCode('juanmiguel.delacruz@example.com');

        $this->sendCode('juanmiguel.delacruz@example.com')->assertStatus(429);
        Mail::assertSentCount(1);

        $this->travel(61)->seconds();
        $this->sendCode('juanmiguel.delacruz@example.com')->assertOk();
        $second = $this->lastCode('juanmiguel.delacruz@example.com');

        if ($first !== $second) {
            $this->checkCode('juanmiguel.delacruz@example.com', $first)->assertUnprocessable();
        }
        $this->checkCode('juanmiguel.delacruz@example.com', $second)->assertOk()->assertJsonPath('verified', true);
    }

    public function test_too_many_wrong_codes_lock_the_code(): void
    {
        $this->sendCode('juanmiguel.delacruz@example.com');
        $code = $this->lastCode('juanmiguel.delacruz@example.com');
        $wrong = $code === '000000' ? '111111' : '000000';
        foreach (range(1, 5) as $attempt) {
            $this->checkCode('juanmiguel.delacruz@example.com', $wrong);
        }

        $this->checkCode('juanmiguel.delacruz@example.com', $code)
            ->assertJsonPath('message', 'Too many incorrect attempts. Send a new code and try again.');
    }

    public function test_tc_buy_028_no_account_exists_before_the_email_is_verified(): void
    {
        $this->sendCode('juanmiguel.delacruz@example.com');

        $this->post('/login', ['email' => 'juanmiguel.delacruz@example.com', 'password' => 'Mamimili@2026'])
            ->assertSessionHasErrors(['email' => 'Invalid email or password.']);
        $this->assertGuest();
    }

    public function test_tc_buy_029_verified_and_approved_buyer_can_log_in(): void
    {
        $this->register();
        $this->post('/login', ['email' => 'juanmiguel.delacruz@example.com', 'password' => 'Mamimili@2026'])
            ->assertSessionHasErrors(['email' => 'Your account is still pending approval. Please check your email.']);

        User::sole()->update(['status' => 'approved']);
        $this->post('/login', ['email' => 'juanmiguel.delacruz@example.com', 'password' => 'Mamimili@2026'])
            ->assertRedirect('/buyer/dashboard');
        $this->assertAuthenticated();
    }
}
