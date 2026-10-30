<?php

namespace Tests\Feature\Pickrush;

use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Mail\RiderEmailCodeMail;
use App\Pickrush\Models\Assignment;
use App\Pickrush\Models\CodRemittance;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\RiderBatch;
use App\Pickrush\Models\RiderDocument;
use App\Pickrush\Models\RiderToken;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\ParcelRegistrar;
use App\Pickrush\Services\Rider\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** The rider app's Earnings and Account pages. */
class RiderAccountTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private Hub $tanauan;

    private Staff $manager;

    private Rider $rider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildNetwork();
        $this->tanauan = $this->station('041029000');
        $this->manager = $this->staff(StaffRole::HubManager, $this->tanauan, ['full_name' => 'Paolo Santos', 'mobile_no' => '09171112222']);
        $this->rider = Rider::withoutGlobalScopes()->create([
            'station_hub_id' => $this->tanauan->id, 'first_name' => 'Xtian', 'last_name' => 'Sorro', 'mobile_no' => '09181234567',
            'email' => 'xtian@rider.test', 'password' => 'Old-pass1!', 'vehicle_type' => 'motorcycle', 'plate_no' => 'NAB 1234',
            'status' => 'active', 'shift_status' => 'on_shift', 'cod_limit' => 5000, 'max_parcels' => 20,
            'payout_method' => 'gcash', 'payout_account' => '09179876543', 'created_by' => $this->manager->id,
        ]);
    }

    private function api(): static
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.RiderToken::issue($this->rider), 'Accept' => 'application/json']);
    }

    private function parcel(float $cod = 500): Parcel
    {
        return app(ParcelRegistrar::class)->register([
            'shipper_name' => 'Tote House', 'pickup_address' => 'Altura Bata', 'pickup_barangay_code' => '041029001', 'pickup_municipality_code' => '041029000',
            'consignee_name' => 'Ana', 'delivery_address' => 'Altura Bata', 'delivery_barangay_code' => '041029001', 'delivery_municipality_code' => '041029000',
            'cod_amount' => $cod,
        ]);
    }

    /** A finished task of the rider, $daysAgo days back. */
    private function done(string $type, int $daysAgo, ?int $batchId = null, float $cod = 0): Assignment
    {
        return Assignment::create([
            'parcel_id' => $this->parcel()->id, 'rider_id' => $this->rider->id, 'batch_id' => $batchId, 'type' => $type,
            'assigned_at' => now()->subDays($daysAgo), 'status' => 'completed', 'completed_at' => now()->subDays($daysAgo),
            'cod_collected' => $cod ?: null,
        ]);
    }

    public function test_earnings_add_up_finished_tasks_by_period_day_and_batch_with_cod_in_hand(): void
    {
        $batch = RiderBatch::create(['type' => 'delivery', 'rider_id' => $this->rider->id, 'station_hub_id' => $this->tanauan->id,
            'status' => 'completed', 'offered_at' => now(), 'completed_at' => now()]);
        $this->done('pickup', 0);
        $this->done('pickup', 0);
        $this->done('delivery', 0, $batch->id, 650);
        $this->done('delivery', 2, null, 300);
        Assignment::create(['parcel_id' => $this->parcel()->id, 'rider_id' => $this->rider->id, 'type' => 'delivery',
            'assigned_at' => now(), 'status' => 'failed', 'completed_at' => now()]);      // a failed attempt earns nothing
        CodRemittance::create(['rider_id' => $this->rider->id, 'station_hub_id' => $this->tanauan->id, 'remit_date' => today(),
            'expected_amount' => 300, 'received_amount' => 300, 'variance' => 0, 'status' => 'reconciled', 'received_by' => $this->manager->id]);
        Assignment::where('cod_collected', 300)->update(['cod_remittance_id' => CodRemittance::sole()->id]);

        $pickup = (float) config('pickrush.rider_pay.pickup_per_parcel');
        $delivery = (float) config('pickrush.rider_pay.delivery_per_parcel');

        $this->api()->getJson('/api/rider/earnings')->assertOk()
            ->assertJsonPath('today.pickups', 2)
            ->assertJsonPath('today.deliveries', 1)
            ->assertJsonPath('today.earnings', fn ($v) => (float) $v === 2 * $pickup + $delivery)
            ->assertJsonPath('week.deliveries', now()->subDays(2)->gte(now()->startOfWeek()) ? 2 : 1)
            ->assertJsonCount(7, 'days')
            ->assertJsonPath('days.6.date', today()->toDateString())
            ->assertJsonPath('days.4.deliveries', 1)
            ->assertJsonPath('batches.0.batch_no', $batch->fresh()->batch_no)
            ->assertJsonPath('batches.0.earned', fn ($v) => (float) $v === $delivery)
            ->assertJsonPath('cod.in_hand', fn ($v) => (float) $v === 650.0)
            ->assertJsonPath('cod.remitted_this_month', fn ($v) => (float) $v === 300.0)
            ->assertJsonPath('cod.last_remittance.status', 'reconciled');
    }

    public function test_the_account_shows_station_details_documents_and_masked_payout(): void
    {
        RiderDocument::create(['rider_id' => $this->rider->id, 'doc_type' => 'drivers_license', 'side' => 'front',
            'file_path' => 'x.jpg', 'expiry_date' => now()->addDays(20)->toDateString()]);

        $this->api()->getJson('/api/rider/me')->assertOk()
            ->assertJsonPath('rider.cod_limit', fn ($v) => (float) $v === 5000.0)
            ->assertJsonPath('rider.max_parcels', 20)
            ->assertJsonPath('rider.payout.method', 'gcash')
            ->assertJsonPath('rider.payout.account', '•••••••6543')
            ->assertJsonPath('rider.documents.0.label', "Driver's license")
            ->assertJsonPath('rider.documents.0.expiry', now()->addDays(20)->toDateString())
            ->assertJsonPath('rider.station_contact.name', 'Paolo Santos');
    }

    public function test_a_forgotten_password_is_reset_with_a_code_sent_to_the_registered_email_only(): void
    {
        Mail::fake();
        $phone = RiderToken::issue($this->rider);

        // The answer never reveals whether an email is registered; only a registered one gets mail.
        $this->postJson('/api/rider/password/forgot', ['email' => 'nobody@rider.test'])->assertOk()
            ->assertJsonPath('masked_email', 'no•••dy@rider.test');
        Mail::assertNothingSent();

        $this->postJson('/api/rider/password/forgot', ['email' => 'XTIAN@rider.test'])->assertOk()
            ->assertJsonPath('masked_email', 'xt•••an@rider.test');
        $code = null;
        Mail::assertSent(RiderEmailCodeMail::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return $mail->hasTo('xtian@rider.test') && $mail->purpose === 'reset';
        });
        $this->postJson('/api/rider/password/forgot', ['email' => 'xtian@rider.test'])->assertStatus(422);   // wait a minute

        $this->postJson('/api/rider/password/reset', ['email' => 'xtian@rider.test', 'code' => '000000', 'password' => 'New-pass2!', 'password_confirmation' => 'New-pass2!'])
            ->assertStatus(422)->assertJsonPath('message', 'Incorrect code. Please try again.');
        $this->postJson('/api/rider/password/reset', ['email' => 'xtian@rider.test', 'code' => $code, 'password' => 'New-pass2!', 'password_confirmation' => 'New-pass2!'])
            ->assertOk();

        $this->assertTrue(Hash::check('New-pass2!', $this->rider->fresh()->password));
        $this->assertNull(RiderToken::findByPlain($phone), 'every phone is signed out');
        $this->postJson('/api/rider/login', ['email' => 'xtian@rider.test', 'password' => 'New-pass2!'])->assertOk();
        $this->assertSame('ch'.str_repeat('•', 8).'23@gmail.com', PasswordReset::mask('christian123@gmail.com'));
    }

    public function test_the_rider_changes_their_password_and_other_phones_are_signed_out(): void
    {
        $otherPhone = RiderToken::issue($this->rider);
        $api = $this->api();

        $api->postJson('/api/rider/password', ['current_password' => 'wrong', 'password' => 'New-pass2!', 'password_confirmation' => 'New-pass2!'])
            ->assertStatus(422)->assertJsonValidationErrors('current_password');
        $api->postJson('/api/rider/password', ['current_password' => 'Old-pass1!', 'password' => 'weak', 'password_confirmation' => 'weak'])
            ->assertStatus(422)->assertJsonValidationErrors('password');

        $api->postJson('/api/rider/password', ['current_password' => 'Old-pass1!', 'password' => 'New-pass2!', 'password_confirmation' => 'New-pass2!'])
            ->assertOk();

        $this->assertTrue(Hash::check('New-pass2!', $this->rider->fresh()->password));
        $api->getJson('/api/rider/me')->assertOk();                          // this phone stays signed in
        $this->assertNull(RiderToken::findByPlain($otherPhone));
    }
}
