<?php

namespace Tests\Feature\Pickrush;

use App\Models\User;
use App\Services\Geo\AddressResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressCodesTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildNetwork();
    }

    private function user(string $province, string $municipality, string $barangay): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'role' => 'buyer', 'status' => 'approved', 'first_name' => 'Ana', 'last_name' => "Codes{$n}", 'sex' => 'Female',
            'email' => "codes{$n}@example.com", 'password' => bcrypt('secret-password'), 'contact_no' => '09170000000',
            'birthday' => '1995-01-01', 'age' => 30, 'province' => $province, 'municipality' => $municipality, 'barangay' => $barangay,
        ]);
    }

    public function test_codes_follow_the_address_names_on_every_save(): void
    {
        $user = $this->user('Batangas', 'City of Tanauan', 'Altura Bata');
        $this->assertSame(['041000000', '041029000', '041029001'], [$user->province_code, $user->municipality_code, $user->barangay_code]);

        $user->update(['province' => 'Laguna', 'municipality' => 'Calamba City', 'barangay' => 'Brgy. Bagong Kalsada']);
        $this->assertSame(['043400000', '043405000', '043405001'], [$user->province_code, $user->municipality_code, $user->barangay_code]);

        $user->update(['barangay' => 'Nowhere']);
        $this->assertSame(['043405000', null], [$user->municipality_code, $user->barangay_code], 'no guessing: an unknown barangay stays empty');
    }

    public function test_metro_manila_is_matched_by_region(): void
    {
        $user = $this->user('Metro Manila', 'Quezon City', 'Bagong Pag-asa');

        $this->assertSame(['137400000', '137404000', '137404001'], [$user->province_code, $user->municipality_code, $user->barangay_code]);
    }

    public function test_registration_stores_codes(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $this->post(config('app.url').'/register', [
            'role' => 'buyer', 'last_name' => 'Reyes', 'first_name' => 'Joy', 'sex' => 'Female',
            'email' => 'joy@example.com', 'password' => 'secret-pass', 'password_confirmation' => 'secret-pass', 'confirmed' => '1',
            'contact_no' => '09171112222', 'birthday' => '1995-05-05',
            'province' => 'Batangas', 'municipality' => 'City of Tanauan', 'barangay' => 'Altura Bata',
            'id_upload' => \Illuminate\Http\UploadedFile::fake()->create('id.pdf', 20, 'application/pdf'),
        ])->assertRedirect('/login');

        $this->assertDatabaseHas('users', ['email' => 'joy@example.com', 'municipality_code' => '041029000', 'barangay_code' => '041029001']);
    }

    public function test_backfill_command_resolves_existing_accounts_and_lists_misses(): void
    {
        $ok = $this->user('Batangas', 'City of Tanauan', 'Altura Bata');
        $miss = $this->user('Batangas', 'Atlantis', 'Somewhere');
        User::query()->update(['province_code' => null, 'municipality_code' => null, 'barangay_code' => null]);

        $this->artisan('geo:backfill-user-codes')->expectsOutputToContain('1 addresses resolved')->expectsOutputToContain('1 addresses need a manual fix')->assertSuccessful();

        $this->assertSame('041029001', $ok->fresh()->barangay_code);
        $this->assertNull($miss->fresh()->municipality_code);
    }

    public function test_name_keys_ignore_city_and_barangay_wording(): void
    {
        $this->assertSame(AddressResolver::key('City of Tanauan'), AddressResolver::key('TANAUAN CITY'));
        $this->assertSame(AddressResolver::key('Adams (Pob.)'), AddressResolver::key('Adams Poblacion'));
        $this->assertSame(AddressResolver::key('Parañaque'), AddressResolver::key('Paranaque'));
        $this->assertSame('041029001', app(AddressResolver::class)->resolve('Batangas', 'Tanauan', 'Altura Bata')['barangay_code']);
    }
}
