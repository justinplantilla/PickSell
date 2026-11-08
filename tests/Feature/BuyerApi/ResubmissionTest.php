<?php

namespace Tests\Feature\BuyerApi;

use App\Mail\RegistrationDisapprovedMail;
use App\Models\RegistrationReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

/**
 * A rejected buyer fixes and resubmits the same account, like rejected sellers (RegistrationResubmission):
 * on the website's resubmit page, or in the app with the single-use token from the login refusal.
 */
class ResubmissionTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    private User $admin;

    private User $rejected;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Notification::fake();
        $this->admin = $this->admin();
        $this->rejected = $this->buyer(['status' => 'disapproved', 'id_upload' => 'uploads/ids/blurry.jpg']);
        RegistrationReview::create([
            'user_id' => $this->rejected->id, 'reviewed_by' => $this->admin->id, 'decision' => RegistrationReview::DISAPPROVED,
            'reason' => 'The ID photo is blurry; the name cannot be read.', 'reviewed_at' => now(),
        ]);
    }

    private function id(): UploadedFile
    {
        return UploadedFile::fake()->create('clear-id.jpg', 300, 'image/jpeg');
    }

    private function login(): array
    {
        return $this->postJson('/api/mobile/login', ['email' => $this->rejected->email, 'password' => 'Password1!'])
            ->assertForbidden()->json();
    }

    public function test_the_app_login_says_why_and_gives_a_way_to_fix_it(): void
    {
        $refusal = $this->login();

        $this->assertSame('disapproved', $refusal['status']);
        $this->assertSame('The ID photo is blurry; the name cannot be read.', $refusal['reason']);
        $this->assertSame([['field' => 'id_upload', 'label' => 'Government-issued ID']], $refusal['documents']);
        $this->assertSame(64, strlen($refusal['resubmit_token']));
        $this->assertSame(1800, $refusal['expires_in']);
        $this->assertArrayNotHasKey('token', $refusal, 'not signed in');
    }

    public function test_the_app_resubmits_the_same_account_with_a_new_id(): void
    {
        $token = $this->login()['resubmit_token'];

        $this->post('/api/mobile/register/resubmit', ['resubmit_token' => $token], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonPath('errors.documents.0', 'Upload at least one corrected document before resubmitting.');
        $this->post('/api/mobile/register/resubmit', ['resubmit_token' => $token, 'id_upload' => UploadedFile::fake()->create('id.gif', 10, 'image/gif')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonPath('errors.id_upload.0', 'Only JPG, PNG and PDF files are accepted.');

        $this->post('/api/mobile/register/resubmit', ['resubmit_token' => $token, 'id_upload' => $this->id()], ['Accept' => 'application/json'])
            ->assertOk()->assertExactJson(['status' => 'pending', 'message' => 'Your application was resubmitted and is pending approval again. You will be notified via email.']);

        $buyer = $this->rejected->fresh();
        $this->assertSame('pending', $buyer->status);
        $this->assertNotSame('uploads/ids/blurry.jpg', $buyer->id_upload);
        Storage::disk('public')->assertExists($buyer->id_upload);
        $this->assertDatabaseHas('user_status_histories', ['user_id' => $buyer->id, 'from_status' => 'disapproved', 'to_status' => 'pending']);
        $this->assertSame(1, User::where('email', $buyer->email)->count(), 'the same account, not a new one');

        // Single use, and pending now: logging in says so.
        $this->post('/api/mobile/register/resubmit', ['resubmit_token' => $token, 'id_upload' => $this->id()], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('resubmit_token');
        $this->postJson('/api/mobile/login', ['email' => $buyer->email, 'password' => 'Password1!'])->assertForbidden()->assertJsonPath('status', 'pending');

        // Back in the admin's queue.
        $this->actingAs($this->admin)->get(route('admin.registrations'))->assertOk()->assertSee($buyer->email);
    }

    public function test_a_wrong_or_expired_token_is_refused(): void
    {
        $this->post('/api/mobile/register/resubmit', ['resubmit_token' => str_repeat('x', 64), 'id_upload' => $this->id()], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonPath('errors.resubmit_token.0', 'This link to fix your sign-up has expired. Log in again to resubmit.');

        $token = $this->login()['resubmit_token'];
        $this->travel(31)->minutes();
        $this->post('/api/mobile/register/resubmit', ['resubmit_token' => $token, 'id_upload' => $this->id()], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('resubmit_token');
        $this->assertSame('disapproved', $this->rejected->fresh()->status);
    }

    public function test_the_website_lets_a_rejected_buyer_resubmit_too(): void
    {
        $this->post('/login', ['email' => $this->rejected->email, 'password' => 'Password1!'])->assertRedirect(route('registration.resubmit'));
        $this->assertGuest();
        $this->get(route('registration.resubmit'))->assertOk()
            ->assertSee('Resubmit your buyer sign-up')
            ->assertSee('The ID photo is blurry; the name cannot be read.')
            ->assertSee('Government-issued ID')
            ->assertDontSee('Business permit');

        $this->post(route('registration.resubmit.store'), ['id_upload' => $this->id()])->assertRedirect('/login')->assertSessionHas('success');
        $this->assertSame('pending', $this->rejected->fresh()->status);
    }

    public function test_the_rejection_email_tells_buyers_how_to_fix_it(): void
    {
        $html = (new RegistrationDisapprovedMail($this->rejected, 'The ID photo is blurry.'))->render();

        $this->assertStringContainsString('The ID photo is blurry.', $html);
        $this->assertStringContainsString('upload a clear photo of a valid government ID and resubmit your sign-up', $html);
    }
}
