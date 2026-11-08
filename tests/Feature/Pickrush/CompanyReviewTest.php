<?php

namespace Tests\Feature\Pickrush;

use App\Models\User;
use App\Pickrush\Mail\CompanyApplicationDecidedMail;
use App\Pickrush\Models\Company;
use App\Pickrush\Services\Registration\CompanyRegistration;
use App\Pickrush\Services\Registration\CompanyReviewer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyReviewTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        $this->buildNetwork();
        $this->company = $this->register();
    }

    public function test_the_admin_reviews_a_registration_and_its_documents(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->get($this->admin('/admin/logistics'))->assertOk()
            ->assertSeeInOrder(['Company registrations', 'Review registrations', 'Bilis Hatid Express Inc.', 'Logistics companies'])
            ->assertSee('data-row-href="'.route('admin.logistics.companies.show', $this->company).'"', false);
        $this->actingAs($admin)->get($this->admin('/admin/logistics/companies'))->assertOk()->assertSee('Bilis Hatid Express Inc.')->assertDontSee('Pickrush Logistics')
            ->assertSee('data-row-href="'.route('admin.logistics.companies.show', $this->company).'"', false);
        $this->actingAs($admin)->get($this->admin("/admin/logistics/companies/{$this->company->id}"))->assertOk()
            ->assertSee('Carlos Antonio Mendoza')->assertSee('Operations Manager')->assertSee('Altura Bata')
            ->assertSee('govt_id_front.jpg')->assertSee('Approve');

        $document = $this->company->latestDocument('dti_permit');
        $this->actingAs($admin)->get($this->admin("/admin/logistics/documents/{$document->id}"))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_only_administrators_open_applications_and_documents(): void
    {
        $buyer = $this->user('buyer');
        $document = $this->company->latestDocument('valid_id');

        foreach (['/admin/logistics/companies', "/admin/logistics/companies/{$this->company->id}", "/admin/logistics/documents/{$document->id}"] as $url) {
            $this->assertContains($this->actingAs($buyer)->get($url)->status(), [302, 403, 404]);
        }
        $this->actingAs($buyer)->patch($this->admin("/admin/logistics/companies/{$this->company->id}/approve"));
        $this->assertSame('pending', $this->company->fresh()->status);
        $this->assertStringNotContainsString('storage', $document->path, 'documents never sit on the public disk');
    }

    public function test_approving_gives_a_tracking_prefix_and_opens_the_portal(): void
    {
        $this->actingAs($this->user('admin'))->patch($this->admin("/admin/logistics/companies/{$this->company->id}/approve"))
            ->assertRedirect("/admin/logistics/companies/{$this->company->id}");

        $company = $this->company->fresh();
        $this->assertSame('approved', $company->status);
        $this->assertSame('BH', $company->tracking_prefix);
        $this->assertNotNull($company->approved_at);
        $this->assertSame('approved', $company->reviews()->first()->decision);
        Mail::assertSent(CompanyApplicationDecidedMail::class, fn ($mail) => $mail->hasTo('operations@bilishatid.example.com') && $mail->decision === 'approved');

        $this->actingAs($company->owner, 'pickrush')->get($this->pickrushUrl('/hq'))->assertOk();
    }

    public function test_a_rejected_company_corrects_and_resubmits(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->patch($this->admin("/admin/logistics/companies/{$this->company->id}/reject"), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->patch($this->admin("/admin/logistics/companies/{$this->company->id}/reject"), ['reason' => 'The DTI permit is blurry. Upload a clear copy.']);

        $this->assertSame('rejected', $this->company->fresh()->status);
        Mail::assertSent(CompanyApplicationDecidedMail::class, fn ($mail) => $mail->decision === 'rejected' && $mail->reason === 'The DTI permit is blurry. Upload a clear copy.');

        $owner = $this->company->owner;
        $this->actingAs($owner, 'pickrush')->get($this->pickrushUrl('/'))->assertOk()
            ->assertSee('The DTI permit is blurry.')->assertSee(route('pickrush.application.edit'), false);
        $this->get($this->pickrushUrl('/application'))->assertOk()
            ->assertSee('Correct and resubmit')->assertSee('value="Bilis Hatid Express Inc."', false)->assertSee('Current: dti_permit.pdf', false);

        $this->put($this->pickrushUrl('/application'), array_merge($this->form(), [
            'position' => 'General Manager',
            'valid_id' => null,
            'dti_permit' => UploadedFile::fake()->create('mayors_permit_2026.pdf', 1200, 'application/pdf'),
        ]))->assertSessionHasNoErrors()->assertRedirect(route('pickrush.dashboard'));

        $company = $this->company->fresh();
        $this->assertSame('pending', $company->status);
        $this->assertNull($company->rejection_reason);
        $this->assertSame('General Manager', $owner->fresh()->position);
        $this->assertSame(2, $company->latestDocument('dti_permit')->version);
        $this->assertSame(1, $company->latestDocument('valid_id')->version, 'a document left empty is kept');
        $this->assertSame('resubmitted', $company->reviews()->first()->decision);

        $this->actingAs($admin)->get($this->admin('/admin/logistics/companies'))->assertSee('Bilis Hatid Express Inc.');
    }

    public function test_decision_emails_name_the_company(): void
    {
        $approved = (new CompanyApplicationDecidedMail(tap($this->company)->forceFill(['tracking_prefix' => 'BH']), 'approved'))->render();
        $this->assertStringContainsString('Bilis Hatid Express Inc. is approved!', $approved);
        $this->assertStringContainsString('<strong>BH</strong>', $approved);
        $this->assertStringContainsString('Hi Carlos Antonio', $approved);

        $rejected = (new CompanyApplicationDecidedMail($this->company, 'rejected', 'The DTI permit is blurry.'))->render();
        $this->assertStringContainsString('reviewed the registration of Bilis Hatid Express Inc. and could not approve it', $rejected);
        $this->assertStringContainsString('The DTI permit is blurry.', $rejected);

        foreach ([$approved, $rejected] as $html) {
            $this->assertStringNotContainsString('{&quot;id&quot;', $html);
            $this->assertStringNotContainsString('{"id"', $html);
        }
    }

    public function test_only_a_rejected_owner_may_resubmit(): void
    {
        $this->actingAs($this->company->owner, 'pickrush')->get($this->pickrushUrl('/application'))->assertForbidden();
        $this->put($this->pickrushUrl('/application'), $this->form())->assertForbidden();
    }

    public function test_suspending_and_reinstating(): void
    {
        $admin = $this->user('admin');
        app(CompanyReviewer::class)->approve($this->company, $admin);

        $this->actingAs($admin)->patch($this->admin("/admin/logistics/companies/{$this->company->id}/suspend"), ['reason' => 'Too many lost parcels this month.']);
        $this->assertSame('suspended', $this->company->fresh()->status);
        $this->actingAs($this->company->owner, 'pickrush')->get($this->pickrushUrl('/hq'))->assertRedirect(route('pickrush.dashboard'));
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->company->fresh()->owner, 'pickrush')->get($this->pickrushUrl('/'))->assertSee('Account suspended');

        $this->actingAs($admin)->patch($this->admin("/admin/logistics/companies/{$this->company->id}/reinstate"))->assertSessionHasNoErrors()->assertRedirect("/admin/logistics/companies/{$this->company->id}");
        $this->assertSame('approved', $this->company->fresh()->status);
        $this->assertSame('BH', $this->company->fresh()->tracking_prefix, 'the prefix stays with the company');
    }

    public function test_the_default_carrier_cannot_be_suspended(): void
    {
        $this->actingAs($this->user('admin'))->patch($this->admin('/admin/logistics/companies/'.Company::defaultId().'/suspend'), ['reason' => 'Testing the default carrier.'])
            ->assertSessionHasErrors('decision');
        $this->assertSame('approved', Company::find(Company::defaultId())->status);
    }

    public function test_tracking_prefixes_are_two_free_letters(): void
    {
        $reviewer = app(CompanyReviewer::class);
        Company::create(['name' => 'Taken', 'slug' => 'taken', 'tracking_prefix' => 'BH', 'status' => 'approved']);

        $this->assertSame('BI', $reviewer->trackingPrefix('Bilis Hatid'));
        $this->assertSame('PI', $reviewer->trackingPrefix('Prime Route'), 'PR belongs to Pickrush');
        $this->assertSame('XG', $reviewer->trackingPrefix('Xe 2 Go'));
        $this->assertMatchesRegularExpression('/^[A-Z]{2}$/', $reviewer->trackingPrefix('123'));
    }

    /** Admin pages live on the marketplace host, whichever host the last request went to. */
    private function admin(string $path): string
    {
        return rtrim(config('app.url'), '/').$path;
    }

    private function register(): Company
    {
        $data = $this->form() + [
            'email' => 'operations@bilishatid.example.com',
            'password' => 'Hatid@2026',
        ];

        return app(CompanyRegistration::class)->register($data, $data['valid_id'], $data['dti_permit']);
    }

    private function form(): array
    {
        return [
            'last_name' => 'Mendoza', 'first_name' => 'Carlos Antonio', 'middle_initial' => null, 'sex' => 'male',
            'contact_no' => '09285551234', 'birthday' => '1990-03-22',
            'business_name' => 'Bilis Hatid Express Inc.', 'position' => 'Operations Manager',
            'province' => '041000000', 'municipality' => '041029000', 'barangay' => '041029001', 'street_address' => 'Unit 5B, 88 West Capitol Drive',
            'valid_id' => UploadedFile::fake()->create('govt_id_front.jpg', 1200, 'image/jpeg'),
            'dti_permit' => UploadedFile::fake()->create('dti_permit.pdf', 1000, 'application/pdf'),
            'terms' => '1',
        ];
    }

    private function user(string $role): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'first_name' => ucfirst($role), 'last_name' => "User{$n}", 'email' => "{$role}{$n}@picksell.test",
            'password' => bcrypt('password'), 'role' => $role, 'status' => 'approved', 'sex' => 'Male',
            'contact_no' => '0917123456'.$n, 'birthday' => '1990-01-01', 'age' => 35,
            'province' => 'Metro Manila', 'municipality' => 'Quezon City', 'barangay' => 'Bahay',
        ]);
    }
}
