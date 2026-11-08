<?php

namespace Tests\Feature\Pickrush;

use App\Mail\EmailVerificationCodeMail;
use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Company;
use App\Pickrush\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\ExpectationFailedException;
use Tests\TestCase;

class CompanyRegistrationTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private const EMAIL = 'operations@bilishatid.example.com';

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.verify_domains' => false]);
        Mail::fake();
        Storage::fake('local');
        $this->buildNetwork();
    }

    public function test_the_landing_and_login_pages_lead_to_registration(): void
    {
        $this->get($this->pickrushUrl('/login'))->assertOk()->assertSee(route('pickrush.register'), false);
        $this->get($this->pickrushUrl('/register'))->assertOk()
            ->assertSee('Register your company')
            ->assertSee('data-verify-email', false)
            ->assertSee('Submit registration')
            ->assertSee('data-verified-badge  hidden', false)   // before verifying, only Verify shows
            ->assertDontSee('@hidden', false);
    }

    public function test_verify_sends_a_code_and_the_dialog_confirms_it(): void
    {
        $this->postJson($this->pickrushUrl('/register/email-code'), ['email' => '  Operations@BilisHatid.example.com '])
            ->assertOk()->assertJson(['expires_in' => 300, 'resend_in' => 60])->assertJsonMissing(['code']);

        // Resending inside 60 seconds is refused.
        $this->postJson($this->pickrushUrl('/register/email-code'), ['email' => self::EMAIL])->assertStatus(429);

        $this->postJson($this->pickrushUrl('/register/email-code/verify'), ['email' => self::EMAIL, 'code' => $this->otherCode()])
            ->assertStatus(422)->assertJson(['verified' => false]);
        $this->postJson($this->pickrushUrl('/register/email-code/verify'), ['email' => self::EMAIL, 'code' => $this->sentCode()])
            ->assertOk()->assertJson(['verified' => true]);
        $this->assertSame(self::EMAIL, session('logistics_verified_email'));

        // A code works once.
        $this->postJson($this->pickrushUrl('/register/email-code/verify'), ['email' => self::EMAIL, 'code' => $this->sentCode()])->assertStatus(422);
    }

    public function test_five_wrong_codes_lock_the_address(): void
    {
        $this->postJson($this->pickrushUrl('/register/email-code'), ['email' => self::EMAIL])->assertOk();
        foreach (range(1, 4) as $try) {
            $this->postJson($this->pickrushUrl('/register/email-code/verify'), ['email' => self::EMAIL, 'code' => $this->otherCode()])->assertStatus(422);
        }
        $this->postJson($this->pickrushUrl('/register/email-code/verify'), ['email' => self::EMAIL, 'code' => $this->otherCode()])
            ->assertStatus(422)->assertJsonStructure(['locked_for']);

        $this->travel(2)->minutes();
        $this->postJson($this->pickrushUrl('/register/email-code'), ['email' => self::EMAIL])->assertStatus(429)->assertJsonStructure(['locked_for']);
        $this->travel(15)->minutes();
        $this->postJson($this->pickrushUrl('/register/email-code'), ['email' => self::EMAIL])->assertOk();
    }

    public function test_a_registered_email_cannot_get_a_code(): void
    {
        $this->staff(StaffRole::Support, null, ['email' => 'taken@example.com']);

        $this->postJson($this->pickrushUrl('/register/email-code'), ['email' => 'TAKEN@example.com'])->assertStatus(422);
        Mail::assertNothingSent();
    }

    public function test_a_verified_applicant_registers_a_pending_company(): void
    {
        $this->verify();

        $this->post($this->pickrushUrl('/register'), $this->form())
            ->assertSessionHasNoErrors()->assertRedirect(route('pickrush.register.submitted'));
        $this->followingRedirects()->get($this->pickrushUrl('/register/submitted'))
            ->assertSee("please wait for the administrator's approval, which will be sent to your email", false);

        $company = Company::where('name', 'Bilis Hatid Express Inc.')->firstOrFail();
        $this->assertSame('pending', $company->status);
        $this->assertNull($company->tracking_prefix);
        $this->assertSame('041029000', $company->municipality_code);
        $this->assertSame('041029001', $company->barangay_code);
        $this->assertSame(config('pickrush.registration.terms_version'), $company->terms_version);

        $owner = $company->owner;
        $this->assertSame(StaffRole::SuperAdmin, $owner->role);
        $this->assertSame($company->id, $owner->company_id);
        $this->assertSame('Carlos Antonio M. Mendoza', $owner->full_name);
        $this->assertSame('09285551234', $owner->mobile_no);
        $this->assertNotNull($owner->email_verified_at);
        $this->assertNotSame('Hatid@2026', $owner->password);

        $this->assertCount(2, $company->documents);
        foreach ($company->documents as $document) {
            Storage::disk('local')->assertExists($document->path);
            $this->assertStringStartsWith("logistics-applications/{$company->id}/", $document->path);
        }
        $this->assertNull(session('logistics_verified_email'), 'the verified email is used once');
    }

    public function test_the_form_is_refused_until_the_email_is_verified(): void
    {
        $this->post($this->pickrushUrl('/register'), $this->form())->assertSessionHasErrors('email');
        $this->assertSame(1, Company::count(), 'only Pickrush');
    }

    public function test_field_rules(): void
    {
        $this->verify();
        $this->withoutMiddleware(ThrottleRequests::class);   // more attempts than one applicant gets in a minute
        $cases = [
            ['last_name', 'M'], ['first_name', 'Carlos7'], ['middle_initial', 'AB'], ['sex', 'other'],
            ['contact_no', '0917123456'], ['contact_no', '02-8123-4567'], ['birthday', now()->subYears(18)->addDay()->toDateString()],
            ['birthday', '2000-02-30'], ['birthday', now()->addDay()->toDateString()], ['position', '   '],
            ['street_address', 'Blk'], ['province', '999999999'], ['municipality', '043405000'], ['barangay', '043405001'],
            ['password', 'hatid2026'], ['password_confirmation', 'Hatid@2027'], ['terms', null],
        ];
        foreach ($cases as [$field, $value]) {
            $response = $this->post($this->pickrushUrl('/register'), array_merge($this->form(), [$field => $value]));
            try {
                $response->assertSessionHasErrors($field === 'password_confirmation' ? 'password' : $field);
            } catch (ExpectationFailedException) {
                $this->fail("{$field} = ".var_export($value, true)." was accepted (HTTP {$response->status()})");
            }
        }

        foreach (['valid_id' => UploadedFile::fake()->create('price_list.docx', 320, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            'dti_permit' => UploadedFile::fake()->create('big.jpg', 5121, 'image/jpeg')] as $field => $file) {
            $this->post($this->pickrushUrl('/register'), array_merge($this->form(), [$field => $file]))->assertSessionHasErrors($field);
        }
        $this->assertSame(1, Company::count());
    }

    public function test_a_refused_form_keeps_what_was_typed(): void
    {
        $this->verify();
        $this->from($this->pickrushUrl('/register'))->post($this->pickrushUrl('/register'), array_merge($this->form(), ['contact_no' => '123']));

        $this->get($this->pickrushUrl('/register'))->assertOk()
            ->assertSee('value="Mendoza"', false)
            ->assertSee('value="Bilis Hatid Express Inc."', false)
            ->assertSee('data-municipality="041029000"', false);
    }

    public function test_plus_63_numbers_and_names_must_be_new(): void
    {
        $this->staff(StaffRole::HubManager, $this->station('041029000'), ['mobile_no' => '09285551234']);
        Company::create(['name' => 'Agila Cargo Movers Corp.', 'slug' => 'agila', 'status' => 'pending']);
        $this->verify();

        $this->post($this->pickrushUrl('/register'), array_merge($this->form(), ['contact_no' => '+639285551234', 'business_name' => 'agila cargo movers corp.']))
            ->assertSessionHasErrors(['contact_no', 'business_name']);
    }

    public function test_a_pending_company_sees_only_its_status(): void
    {
        $this->verify();
        $this->post($this->pickrushUrl('/register'), $this->form());
        $owner = Staff::where('email', self::EMAIL)->firstOrFail();

        $this->post($this->pickrushUrl('/login'), ['email' => self::EMAIL, 'password' => 'Hatid@2026'])->assertRedirect();
        $this->get($this->pickrushUrl('/'))->assertOk()->assertSee('Your registration is under review');
        $this->get($this->pickrushUrl('/hq'))->assertRedirect(route('pickrush.dashboard'));
        $this->get($this->pickrushUrl('/parcels'))->assertRedirect(route('pickrush.dashboard'));

        $owner->company->update(['status' => 'rejected', 'rejection_reason' => 'The DTI permit is unreadable.']);
        $this->app['auth']->forgetGuards();   // the next request loads the account afresh, as a real one would
        $this->get($this->pickrushUrl('/'))->assertSee('Registration not approved')->assertSee('The DTI permit is unreadable.');

        $this->post($this->pickrushUrl('/logout'))->assertRedirect(route('pickrush.login'));
        $this->assertGuest('pickrush');
    }

    public function test_address_dropdowns_follow_each_other(): void
    {
        $this->getJson($this->pickrushUrl('/register/address/provinces'))->assertOk()
            ->assertJsonFragment(['code' => '041000000', 'name' => 'Batangas'])
            ->assertJsonFragment(['code' => 'NCR', 'name' => 'Metro Manila']);
        $this->getJson($this->pickrushUrl('/register/address/municipalities?province=NCR'))->assertOk()
            ->assertJsonFragment(['name' => 'Quezon City'])->assertJsonMissing(['name' => 'City of Tanauan']);
        $this->getJson($this->pickrushUrl('/register/address/barangays?municipality=041029000'))->assertOk()
            ->assertExactJson([['code' => '041029001', 'name' => 'Altura Bata']]);
    }

    private function verify(): void
    {
        $this->postJson($this->pickrushUrl('/register/email-code'), ['email' => self::EMAIL])->assertOk();
        $this->postJson($this->pickrushUrl('/register/email-code/verify'), ['email' => self::EMAIL, 'code' => $this->sentCode()])->assertOk();
    }

    private function sentCode(): string
    {
        $code = null;
        Mail::assertSent(EmailVerificationCodeMail::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return $code;
    }

    private function otherCode(): string
    {
        return $this->sentCode() === '000000' ? '111111' : '000000';
    }

    private function form(): array
    {
        return [
            'last_name' => 'Mendoza', 'first_name' => 'Carlos  Antonio', 'middle_initial' => 'm', 'sex' => 'male',
            'email' => self::EMAIL, 'contact_no' => '09285551234', 'birthday' => '1990-03-22',
            'business_name' => ' Bilis Hatid Express Inc. ', 'position' => 'Operations Manager',
            'province' => '041000000', 'municipality' => '041029000', 'barangay' => '041029001', 'street_address' => 'Unit 5B, 88 West Capitol Drive',
            'valid_id' => UploadedFile::fake()->create('govt_id_front.jpg', 1200, 'image/jpeg'),
            'dti_permit' => UploadedFile::fake()->create('dti_permit.pdf', 1000, 'application/pdf'),
            'password' => 'Hatid@2026', 'password_confirmation' => 'Hatid@2026', 'terms' => '1',
        ];
    }
}
