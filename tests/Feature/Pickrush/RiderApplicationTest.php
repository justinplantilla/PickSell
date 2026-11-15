<?php

namespace Tests\Feature\Pickrush;

use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Mail\RiderApplicationDecidedMail;
use App\Pickrush\Mail\RiderEmailCodeMail;
use App\Pickrush\Models\Company;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\Hq\HubCreator;
use App\Pickrush\Services\Rider\DocumentRenewal;
use App\Pickrush\Services\Station\RiderAvailability;
use App\Pickrush\Services\Station\RiderOnboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Riders apply in the rider app; the station hub manager approves. */
class RiderApplicationTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private Hub $tanauan;

    private Staff $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildNetwork();
        Storage::fake('local');
        Mail::fake();
        $this->tanauan = $this->station('041029000');
        $this->manager = $this->staff(StaffRole::HubManager, $this->tanauan);
    }

    private function api(string $path): string
    {
        return '/api/rider/'.ltrim($path, '/');
    }

    /** Sends a code and returns the verification token for it. */
    private function verifiedEmail(string $email = 'juan@example.com'): string
    {
        $this->travel(2)->minutes();   // past the resend wait from any earlier code
        $this->postJson($this->api('email-code/send'), ['email' => $email])->assertOk();
        $code = null;
        Mail::assertSent(RiderEmailCodeMail::class, function (RiderEmailCodeMail $mail) use ($email, &$code) {
            if (! $mail->hasTo($email)) {
                return false;
            }
            $code = $mail->code;   // the newest one wins

            return true;
        });

        return $this->postJson($this->api('email-code/verify'), ['email' => $email, 'code' => $code])->assertOk()->json('verificationToken');
    }

    private function application(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'birth_date' => '1998-04-12', 'mobile' => '9171234567',
            'email' => 'juan@example.com', 'password' => 'Secret#123',
            'province_code' => '041000000', 'province_name' => 'Batangas', 'city_code' => '041029000', 'city_name' => 'City of Tanauan',
            'barangay_code' => '041029001', 'barangay_name' => 'Altura Bata', 'street' => '12 Rizal St.',
            'vehicle_type' => 'motorcycle', 'hub_id' => $this->tanauan->id,
            'plate_number' => 'nab 1234', 'license_no' => 'n01-23-456789', 'license_expiry' => now()->addYears(2)->toDateString(),
            'license_front' => UploadedFile::fake()->create('front.jpg', 120, 'image/jpeg'),
            'license_back' => UploadedFile::fake()->create('back.jpg', 120, 'image/jpeg'),
            'orcr' => [UploadedFile::fake()->create('orcr.pdf', 80, 'application/pdf')],
            'orcr_expiry' => now()->addMonths(8)->toDateString(),
        ], $overrides);
    }

    private function apply(array $overrides = []): TestResponse
    {
        $token = $this->verifiedEmail($overrides['email'] ?? 'juan@example.com');

        return $this->post($this->api('apply'), $this->application($overrides + ['email_verification_token' => $token]), ['Accept' => 'application/json']);
    }

    private function login(string $email = 'juan@example.com', string $password = 'Secret#123'): string
    {
        return $this->postJson($this->api('login'), compact('email', 'password'))->assertOk()->json('token');
    }

    // Email code ------------------------------------------------------------------------------

    public function test_the_email_code_is_checked_and_limited(): void
    {
        $this->postJson($this->api('email-code/send'), ['email' => 'Ana@Example.com'])->assertOk();
        $this->postJson($this->api('email-code/send'), ['email' => 'ana@example.com'])->assertStatus(422)->assertJsonPath('message', 'A code was just sent. Wait a minute before asking for another.');

        $this->postJson($this->api('email-code/verify'), ['email' => 'ana@example.com', 'code' => '000000'])->assertStatus(422)->assertJsonValidationErrors('code');
        $this->travel(11)->minutes();
        $this->postJson($this->api('email-code/verify'), ['email' => 'ana@example.com', 'code' => '000000'])->assertStatus(422)->assertJsonPath('message', 'The code has expired. Ask for a new one.');

        $this->assertNotEmpty($this->verifiedEmail('ana@example.com'));
    }

    public function test_a_registered_email_cannot_ask_for_a_code(): void
    {
        $this->apply()->assertCreated();

        $this->postJson($this->api('email-code/send'), ['email' => 'juan@example.com'])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    // Stations --------------------------------------------------------------------------------

    public function test_stations_near_the_rider_are_their_city_first_then_their_province(): void
    {
        $sanJose = $this->station('041005000');
        $this->staff(StaffRole::HubManager, $this->station('041005000'), ['is_active' => false]);   // inactive manager: not accepting

        $this->getJson($this->api('stations?city_code=041029000&province_code=041000000'))->assertOk()
            ->assertJsonPath('scope', 'city')->assertJsonPath('hubs.0.id', (string) $this->tanauan->id)->assertJsonCount(1, 'hubs');

        // San Jose has no active manager yet, so its riders are offered the rest of Batangas.
        $this->getJson($this->api("stations?city_code={$sanJose->psgc_code}&province_code=041000000"))->assertOk()
            ->assertJsonPath('scope', 'province')->assertJsonPath('hubs.0.name', $this->tanauan->name)->assertJsonCount(1, 'hubs');

        $this->getJson($this->api('stations?city_code=043405000&province_code=043400000'))->assertOk()->assertJsonPath('scope', 'none')->assertJsonCount(0, 'hubs');

        $this->staff(StaffRole::HubManager, $this->station('137404000'));
        $this->getJson($this->api('stations?city_code=137401000&province_code=NCR'))->assertOk()
            ->assertJsonPath('scope', 'province')->assertJsonPath('hubs.0.city', 'Quezon City');
    }

    // Applying --------------------------------------------------------------------------------

    public function test_an_application_lands_in_the_station_as_pending_with_its_documents(): void
    {
        $this->apply()->assertCreated()->assertJsonPath('rider.status', 'pending')->assertJsonPath('rider.hub.id', (string) $this->tanauan->id);

        $rider = Rider::withoutGlobalScopes()->sole();
        $this->assertSame(['Juan Dela Cruz', '09171234567', 'juan@example.com', 'NAB 1234', 'N01-23-456789', null, true],
            [$rider->full_name, $rider->mobile_no, $rider->email, $rider->plate_no, $rider->license_no, $rider->created_by, $rider->on_probation]);
        $this->assertSame('12 Rizal St., Altura Bata, City of Tanauan, Batangas', $rider->address);
        $this->assertSame([['drivers_license', 'front'], ['drivers_license', 'back'], ['or_cr', null]],
            $rider->documents()->orderBy('id')->get()->map(fn ($d) => [$d->doc_type, $d->side])->all());
        Storage::disk('local')->assertExists($rider->documents()->first()->file_path);
        $this->assertNotSame('Secret#123', $rider->password);

        // The station sees the application; only the manager's approval makes the rider active.
        $this->actingAs($this->manager, 'pickrush')->get($this->pickrushUrl('/station/riders?status=pending'))->assertOk()->assertSee('Juan Dela Cruz');
        app(RiderOnboarding::class)->approve($rider, $this->manager);
        $this->assertSame('active', $rider->fresh()->status);
    }

    public function test_a_4_wheel_rider_starts_with_the_bigger_load_cap(): void
    {
        $this->apply(['vehicle_type' => 'four_wheel'])->assertCreated();

        $rider = Rider::withoutGlobalScopes()->sole();
        $this->assertSame(['four_wheel', 40, '4 Wheel · NAB 1234'], [$rider->vehicle_type, $rider->max_parcels, $rider->vehicleLabel()]);
        $this->actingAs($this->manager, 'pickrush')->get($this->pickrushUrl("/station/riders/{$rider->id}"))->assertOk()
            ->assertSee('4 Wheel')->assertSee('40 on probation, 100 after');
    }

    public function test_the_applicant_is_emailed_when_the_station_decides(): void
    {
        $this->apply()->assertCreated();
        $rider = Rider::withoutGlobalScopes()->sole();
        app(RiderOnboarding::class)->reject($rider, $this->manager, 'OR/CR plate does not match');

        Mail::assertSent(RiderApplicationDecidedMail::class, fn (RiderApplicationDecidedMail $mail) => $mail->hasTo('juan@example.com')
            && $mail->envelope()->subject === 'Update on your Pickrush Logistics rider application'
            && str_contains($mail->render(), 'OR/CR plate does not match'));

        $rider->update(['status' => 'pending', 'status_reason' => null]);   // resubmitted
        app(RiderOnboarding::class)->approve($rider, $this->manager);

        Mail::assertSent(RiderApplicationDecidedMail::class, fn (RiderApplicationDecidedMail $mail) => $mail->hasTo('juan@example.com')
            && $mail->envelope()->subject === 'Your Pickrush Logistics rider account is approved'
            && str_contains($mail->render(), 'Tanauan City Station'));
        Mail::assertSent(RiderApplicationDecidedMail::class, 2);
    }

    public function test_a_mail_outage_does_not_undo_the_approval(): void
    {
        $this->apply()->assertCreated();
        $rider = Rider::withoutGlobalScopes()->sole();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));

        app(RiderOnboarding::class)->approve($rider, $this->manager);
        $this->assertSame('active', $rider->fresh()->status);
    }

    public function test_an_application_needs_a_fresh_email_verification(): void
    {
        $token = $this->verifiedEmail();
        $this->post($this->api('apply'), $this->application(['email_verification_token' => $token]), ['Accept' => 'application/json'])->assertCreated();

        // Used once only, and only for the email it was issued to.
        $this->post($this->api('apply'), $this->application(['email' => 'other@example.com', 'mobile' => '9170000000', 'email_verification_token' => $token]), ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('email_verification_token');

        $late = $this->verifiedEmail('late@example.com');
        $this->travel(61)->minutes();
        $this->post($this->api('apply'), $this->application(['email' => 'late@example.com', 'mobile' => '9170000001', 'email_verification_token' => $late]), ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('email_verification_token');
    }

    public function test_an_application_is_refused_for_the_wrong_vehicle_station_age_or_a_taken_mobile(): void
    {
        $noManager = $this->station('041005000');

        $this->apply(['vehicle_type' => 'bicycle', 'hub_id' => $noManager->id, 'birth_date' => now()->subYears(17)->toDateString(), 'license_expiry' => '2020-01-01', 'orcr_expiry' => '2020-01-01'])
            ->assertStatus(422)->assertJsonValidationErrors(['vehicle_type', 'hub_id', 'birth_date', 'license_expiry', 'orcr_expiry']);

        $this->apply()->assertCreated();
        $this->apply(['email' => 'second@example.com'])->assertStatus(422)->assertJsonValidationErrors('mobile');
    }

    // Signing in ------------------------------------------------------------------------------

    public function test_an_applicant_signs_in_with_email_and_password_and_sees_their_status(): void
    {
        $this->apply()->assertCreated();

        $this->postJson($this->api('login'), ['email' => 'juan@example.com', 'password' => 'wrong'])->assertStatus(422);
        $this->getJson($this->api('me'))->assertUnauthorized();

        $token = $this->login('JUAN@example.com');
        $this->getJson($this->api('me'), ['Authorization' => "Bearer {$token}"])->assertOk()
            ->assertJsonPath('rider.status', 'pending')->assertJsonPath('rider.mobile', '9171234567')
            ->assertJsonPath('rider.city.code', '041029000')->assertJsonMissingPath('rider.password');

        $this->postJson($this->api('logout'), [], ['Authorization' => "Bearer {$token}"])->assertOk();
        $this->app->forgetInstance('pickrush.rider');
        $this->getJson($this->api('me'), ['Authorization' => "Bearer {$token}"])->assertUnauthorized();
    }

    public function test_a_deactivated_rider_is_signed_out_and_cannot_sign_in(): void
    {
        $this->apply()->assertCreated();
        $rider = Rider::withoutGlobalScopes()->sole();
        $token = $this->login();

        app(RiderOnboarding::class)->approve($rider, $this->manager);
        app(RiderOnboarding::class)->deactivate($rider->fresh(), $this->manager, 'Left Pickrush');

        $this->getJson($this->api('me'), ['Authorization' => "Bearer {$token}"])->assertUnauthorized();
        $this->postJson($this->api('login'), ['email' => 'juan@example.com', 'password' => 'Secret#123'])->assertForbidden();
    }

    // Resubmitting ----------------------------------------------------------------------------

    public function test_a_rejected_rider_fixes_the_application_and_it_is_pending_again(): void
    {
        $this->apply()->assertCreated();
        $rider = Rider::withoutGlobalScopes()->sole();
        $token = $this->login();
        $headers = ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
        $fields = collect($this->application(['plate_number' => 'NBC 9876']))->except(['email', 'password'])->all();

        $this->post($this->api('application'), $fields, $headers)->assertStatus(422)->assertJsonPath('message', 'Only a rejected application can be resubmitted.');

        app(RiderOnboarding::class)->reject($rider, $this->manager, 'OR/CR plate does not match');
        $this->app->forgetInstance('pickrush.rider');
        $this->getJson($this->api('me'), $headers)->assertJsonPath('rider.status', 'rejected')->assertJsonPath('rider.status_reason', 'OR/CR plate does not match');

        $oldFiles = $rider->documents()->pluck('file_path')->all();
        $this->app->forgetInstance('pickrush.rider');
        $this->post($this->api('application'), $fields, $headers)->assertOk()->assertJsonPath('rider.status', 'pending')->assertJsonPath('rider.status_reason', null);

        $rider->refresh();
        $this->assertSame('NBC 9876', $rider->plate_no);
        $this->assertSame(3, $rider->documents()->count());
        foreach ($oldFiles as $path) {
            Storage::disk('local')->assertMissing($path);
        }
    }

    // Renewing documents ----------------------------------------------------------------------

    /** An approved rider signed in to the app; returns a function that calls the API as them. */
    private function approvedRider(): array
    {
        $this->apply()->assertCreated();
        $rider = Rider::withoutGlobalScopes()->sole();
        app(RiderOnboarding::class)->approve($rider, $this->manager);
        $headers = ['Authorization' => 'Bearer '.$this->login(), 'Accept' => 'application/json'];
        $call = function (string $method, string $path, array $data = []) use ($headers) {
            $this->app->forgetInstance('pickrush.rider');

            return $this->call($method, $this->api($path), $data, [], array_filter($data, fn ($v) => $v instanceof UploadedFile || is_array($v)),
                $this->transformHeadersToServerVars($headers));
        };

        return [$rider->fresh(), $call];
    }

    public function test_a_rider_renews_a_document_and_an_expired_one_blocks_dispatch_until_the_manager_approves(): void
    {
        [$rider, $call] = $this->approvedRider();
        $call('GET', 'me')->assertOk()->assertJsonPath('rider.orcr_expiry', now()->addMonths(8)->toDateString())
            ->assertJsonPath('rider.document_updates', [])->assertJsonPath('rider.blocked_by_document', null);

        // The license lapses: no new tasks.
        $rider->documents()->where('doc_type', 'drivers_license')->update(['expiry_date' => today()->subDay()]);
        $this->assertStringContainsString("Driver's license expired on", app(RiderAvailability::class)->blocker($rider->fresh()));
        $call('GET', 'me')->assertJsonPath('rider.blocked_by_document', "Driver's license");

        $license = fn (array $overrides = []) => $overrides + ['license_no' => 'n01-23-999999', 'expiry_date' => today()->addYears(3)->toDateString(),
            'front' => UploadedFile::fake()->create('front.jpg', 100, 'image/jpeg'), 'back' => UploadedFile::fake()->create('back.jpg', 100, 'image/jpeg')];
        $call('POST', 'documents/drivers_license', $license(['expiry_date' => today()->subDay()->toDateString()]))->assertStatus(422)->assertJsonValidationErrors('expiry_date');
        $call('POST', 'documents/drivers_license', $license(['license_no' => 'TYPO']))->assertCreated();
        $call('POST', 'documents/drivers_license', $license())->assertCreated()
            ->assertJsonPath('rider.document_updates.0.status', 'pending')->assertJsonPath('rider.blocked_by_document', "Driver's license");
        $this->assertSame(['replaced', 'pending'], $rider->documentUpdates()->orderBy('id')->pluck('status')->all(), 'the newer one replaces the one still waiting');

        $update = $rider->documentUpdates()->where('status', 'pending')->sole();
        $page = $this->pickrushUrl("/station/riders/{$rider->id}");
        $decide = $this->pickrushUrl("/station/riders/{$rider->id}/document-updates/{$update->id}");
        $this->actingAs($this->manager, 'pickrush')->get($page)->assertOk()->assertSee('Renewed documents to review')->assertSee('N01-23-999999');
        $this->actingAs($this->manager, 'pickrush')->post($decide, ['decision' => 'reject'])->assertSessionHasErrors('reason');

        $this->actingAs($this->manager, 'pickrush')->post($decide, ['decision' => 'approve'])->assertSessionHasNoErrors();
        $rider->refresh();
        $this->assertNull($rider->expiredDocument());
        $this->assertSame('N01-23-999999', $rider->license_no);
        $this->assertSame([today()->addYears(3)->toDateString()], $rider->documents()->where('doc_type', 'drivers_license')->get()->map(fn ($d) => $d->expiry_date->toDateString())->unique()->values()->all());
        $this->assertSame(2, $rider->documentHistory()->count(), 'the old license (both sides) is kept');
        $this->actingAs($this->manager, 'pickrush')->get($page)->assertOk()->assertSee('Replaced documents (2)')->assertDontSee('Renewed documents to review');
        $this->actingAs($this->manager, 'pickrush')->post($decide, ['decision' => 'approve'])->assertSessionHasErrors('operation');
        $this->assertDatabaseHas('pr_rider_notifications', ['rider_id' => $rider->id, 'type' => 'document_approved']);

        // An OR/CR renewal the manager turns down: the rider sees why.
        $call('POST', 'documents/or_cr', ['expiry_date' => today()->addYear()->toDateString(), 'files' => [UploadedFile::fake()->create('orcr.pdf', 80, 'application/pdf')]])->assertCreated();
        $orcr = $rider->documentUpdates()->where('doc_type', 'or_cr')->sole();
        $this->actingAs($this->manager, 'pickrush')->post($this->pickrushUrl("/station/riders/{$rider->id}/document-updates/{$orcr->id}"),
            ['decision' => 'reject', 'reason' => 'The plate on the OR/CR is not yours.'])->assertSessionHasNoErrors();
        $call('GET', 'me')->assertJsonFragment(['type' => 'or_cr', 'status' => 'rejected', 'reason' => 'The plate on the OR/CR is not yours.']);
        $this->assertDatabaseHas('pr_rider_notifications', ['rider_id' => $rider->id, 'type' => 'document_rejected']);
    }

    public function test_riders_are_reminded_before_a_document_expires_unless_a_renewal_is_waiting(): void
    {
        [$rider, $call] = $this->approvedRider();
        $rider->documents()->where('doc_type', 'drivers_license')->update(['expiry_date' => today()->addDays(7)]);
        $rider->documents()->where('doc_type', 'or_cr')->update(['expiry_date' => today()->addDays(30), 'expiry_estimated' => true]);

        $this->assertSame(2, app(DocumentRenewal::class)->remind(), 'one per document, not per license side');
        $this->assertDatabaseHas('pr_rider_notifications', ['rider_id' => $rider->id, 'title' => "Your Driver's license expires in 7 days"]);

        $call('POST', 'documents/or_cr', ['expiry_date' => today()->addYear()->toDateString(), 'files' => [UploadedFile::fake()->create('orcr.pdf', 80, 'application/pdf')]])->assertCreated();
        $this->assertSame(1, app(DocumentRenewal::class)->remind(), 'the OR/CR is already renewed and waiting');

        // The manager reads the real date off an estimated OR/CR.
        $this->actingAs($this->manager, 'pickrush')->get($this->pickrushUrl("/station/riders/{$rider->id}"))->assertSee('Estimated')->assertSee('Set real expiry');
        $this->actingAs($this->manager, 'pickrush')->patch($this->pickrushUrl("/station/riders/{$rider->id}/orcr-expiry"), ['expiry_date' => today()->addMonths(5)->toDateString()])
            ->assertSessionHasNoErrors();
        $this->assertSame([false], $rider->documents()->where('doc_type', 'or_cr')->pluck('expiry_estimated')->unique()->all());
    }

    // Choosing a company ----------------------------------------------------------------------

    /** Another approved company with its own Tanauan station and hub manager. */
    private function otherCompany(string $status = 'approved'): array
    {
        $company = Company::create(['name' => 'Bilis Hatid Express', 'slug' => 'bilis-hatid', 'tracking_prefix' => 'BH', 'status' => $status]);
        $hubs = app(HubCreator::class);
        $hubs->province($company->id, '041000000');
        $station = $hubs->station($company->id, '041029000');
        $manager = $this->staff(StaffRole::HubManager, $station, ['email' => 'manager@bilishatid.test']);

        return [$company, $station, $manager];
    }

    public function test_the_applicant_picks_a_company_then_one_of_its_stations(): void
    {
        [$company, $station] = $this->otherCompany();
        Company::create(['name' => 'Pending Cargo', 'slug' => 'pending-cargo', 'status' => 'pending']);

        $this->getJson($this->api('companies?city_code=041029000&province_code=041000000'))->assertOk()
            ->assertJsonPath('companies.0.name', 'Pickrush Logistics')->assertJsonPath('companies.0.is_default', true)
            ->assertJsonPath('companies.1.name', 'Bilis Hatid Express')
            ->assertJsonCount(2, 'companies');

        $this->getJson($this->api("stations?city_code=041029000&company_id={$company->id}"))->assertOk()
            ->assertJsonCount(1, 'hubs')->assertJsonPath('hubs.0.id', (string) $station->id);
        $this->getJson($this->api('stations?city_code=041029000'))->assertOk()
            ->assertJsonPath('hubs.0.id', (string) $this->tanauan->id, 'older apps get the default company');
    }

    public function test_the_application_goes_to_the_chosen_companys_station_only(): void
    {
        [$company, $station, $manager] = $this->otherCompany();

        $this->apply(['company_id' => $company->id, 'hub_id' => $this->tanauan->id])->assertStatus(422)->assertJsonValidationErrors('hub_id');
        $this->apply(['company_id' => $company->id, 'hub_id' => $station->id])->assertCreated()
            ->assertJsonPath('rider.company.name', 'Bilis Hatid Express');

        $rider = Rider::withoutGlobalScopes()->sole();
        $this->assertSame($company->id, $rider->company_id);

        // Their manager sees the applicant; Pickrush's does not.
        $this->actingAs($manager, 'pickrush')->get($this->pickrushUrl('/station/riders'))->assertOk()->assertSee('Juan');
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->manager, 'pickrush')->get($this->pickrushUrl('/station/riders'))->assertOk()->assertDontSee('Dela Cruz');
    }

    public function test_a_rejected_rider_may_resubmit_to_another_company(): void
    {
        [$company, $station] = $this->otherCompany();
        $this->apply()->assertCreated();
        $rider = Rider::withoutGlobalScopes()->sole();
        $this->assertSame(Company::defaultId(), $rider->company_id);
        app(RiderOnboarding::class)->reject($rider, $this->manager, 'No slots at this station');

        $headers = ['Authorization' => 'Bearer '.$this->login(), 'Accept' => 'application/json'];
        $fields = collect($this->application(['company_id' => $company->id, 'hub_id' => $station->id]))->except(['email', 'password'])->all();
        $this->post($this->api('application'), $fields, $headers)->assertOk()->assertJsonPath('rider.company.name', 'Bilis Hatid Express');

        $this->assertSame([$company->id, $station->id, 'pending'], [$rider->fresh()->company_id, $rider->fresh()->station_hub_id, $rider->fresh()->status]);
    }

    public function test_a_suspended_company_cannot_take_riders_or_send_them_parcels(): void
    {
        [$company, $station, $manager] = $this->otherCompany();
        $this->apply(['company_id' => $company->id, 'hub_id' => $station->id])->assertCreated();
        $rider = Rider::withoutGlobalScopes()->sole();
        app(RiderOnboarding::class)->approve($rider, $manager);
        $headers = ['Authorization' => 'Bearer '.$this->login(), 'Accept' => 'application/json'];
        $this->getJson($this->api('dashboard'), $headers)->assertOk();

        $company->update(['status' => 'suspended']);
        $this->app->forgetInstance('pickrush.rider');
        $this->getJson($this->api('dashboard'), $headers)->assertForbidden()->assertJsonPath('status', 'company_suspended');
        $this->getJson($this->api('me'), $headers)->assertOk();
        $this->getJson($this->api('companies?city_code=041029000'))->assertJsonMissing(['name' => 'Bilis Hatid Express']);
    }
}
