<?php

namespace Tests\Feature;

use App\Auth\Permission;
use App\Mail\SellerWarningMail;
use App\Models\AuditLog;
use App\Models\ComplianceAction;
use App\Models\ComplianceCase;
use App\Models\Product;
use App\Models\User;
use App\Models\UserStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

/** Module 5 — Seller Compliance: category check, cases, evidence, warnings, suspensions, history. */
class SellerComplianceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        Storage::fake('local');
        $this->admin = $this->user('admin');
        $this->seller = $this->user('seller', ['business_name' => 'Tote House', 'line_of_business' => 'Fashion']);
    }

    private function user(string $role, array $overrides = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'first_name' => ucfirst($role), 'last_name' => "Comp{$n}", 'email' => "{$role}{$n}@comp.test",
            'password' => bcrypt('password'), 'role' => $role, 'status' => 'approved', 'sex' => 'Male',
            'contact_no' => '09171234567', 'birthday' => '1990-01-01', 'age' => 35,
            'province' => 'Metro Manila', 'municipality' => 'Pasig', 'barangay' => 'San Miguel',
        ], $overrides));
    }

    private function product(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'seller_id' => $this->seller->id, 'name' => 'Canvas Tote', 'category' => 'Fashion',
            'price' => 500, 'stock' => 10, 'status' => 'active',
        ], $overrides));
    }

    private function openCase(array $overrides = [])
    {
        return $this->actingAs($this->admin)->post(route('admin.compliance.cases.store', $this->seller), array_merge([
            'type' => 'category_mismatch',
            'severity' => 'high',
            'description' => 'Seller lists phone chargers while registered for Fashion.',
        ], $overrides));
    }

    // Category comparison --------------------------------------------------------------------

    public function test_product_category_is_compared_with_registration_category(): void
    {
        $this->product(['name' => 'Canvas Tote', 'category' => 'fashion ']); // case/space-insensitive match
        $this->product(['name' => 'Phone Charger', 'category' => 'Electronics']);
        $this->product(['name' => 'Old Speaker', 'category' => 'Electronics', 'status' => 'archived']); // archived: not counted

        $this->actingAs($this->admin)->get(route('admin.compliance'))->assertOk()
            ->assertSee('Tote House')->assertSeeInOrder(['Fashion', '1']);

        $page = $this->actingAs($this->admin)->get(route('admin.compliance.seller', $this->seller))->assertOk();
        $page->assertSeeInOrder(['Registered category', 'Phone Charger', 'Electronics', 'Outside registered category'])
            ->assertSeeInOrder(['Product violations', 'Phone Charger', 'is listed under', 'Electronics']);

        $this->actingAs($this->admin)->get(route('admin.compliance', ['flag' => 'mismatch']))->assertSee('Tote House');
        $clean = $this->user('seller', ['business_name' => 'Clean Shop', 'line_of_business' => 'Books']);
        Product::create(['seller_id' => $clean->id, 'name' => 'Novel', 'category' => 'Books', 'price' => 200, 'stock' => 3, 'status' => 'active']);
        $this->actingAs($this->admin)->get(route('admin.compliance', ['flag' => 'mismatch']))->assertDontSee('Clean Shop');
    }

    // Violation case + evidence ---------------------------------------------------------

    public function test_violation_case_can_be_opened_with_evidence_retained(): void
    {
        $charger = $this->product(['name' => 'Phone Charger', 'category' => 'Electronics']);

        $this->openCase([
            'product_id' => $charger->id,
            'evidence' => [UploadedFile::fake()->create('listing.png', 120, 'image/png'), UploadedFile::fake()->create('report.pdf', 80, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $case = ComplianceCase::sole();
        $this->assertSame(['category_mismatch', 'high', 'open', $charger->id, $this->admin->id],
            [$case->type, $case->severity, $case->status, $case->product_id, $case->opened_by]);

        $opened = ComplianceAction::where('action', ComplianceAction::CASE_OPENED)->sole();
        $this->assertSame('Seller lists phone chargers while registered for Fashion.', $opened->reason);
        $this->assertCount(2, $opened->attachments);
        Storage::disk('local')->assertExists($opened->attachments[0]['path']);
        $this->assertStringStartsWith("compliance/{$this->seller->id}/", $opened->attachments[0]['path']);

        // Evidence is private: served only to compliance viewers.
        $this->actingAs($this->admin)->get(route('admin.compliance.evidence', ['action' => $opened, 'index' => 0]))->assertOk();
        $this->actingAs($this->seller)->get(route('admin.compliance.evidence', ['action' => $opened, 'index' => 0]))->assertRedirect('/dashboard');
        $this->actingAs($this->admin)->get(route('admin.compliance.evidence', ['action' => $opened, 'index' => 9]))->assertNotFound();

        $this->actingAs($this->admin)->get(route('admin.compliance.cases.show', $case))->assertOk()
            ->assertSee('listing.png')->assertSee('report.pdf')->assertSee('Phone Charger');
    }

    public function test_case_requires_valid_input_and_own_product(): void
    {
        $otherSeller = $this->user('seller', ['business_name' => 'Other']);
        $foreign = Product::create(['seller_id' => $otherSeller->id, 'name' => 'Foreign', 'category' => 'Toys', 'price' => 1, 'stock' => 1, 'status' => 'active']);

        $this->openCase(['description' => 'Too short'])->assertSessionHasErrors('description');
        $this->openCase(['severity' => 'apocalyptic'])->assertSessionHasErrors('severity');
        $this->openCase(['product_id' => $foreign->id])->assertSessionHasErrors('product_id');
        $this->openCase(['evidence' => [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')]])->assertSessionHasErrors('evidence.0');
        $this->assertSame(0, ComplianceCase::count());
    }

    public function test_case_lifecycle_notes_escalation_and_closure(): void
    {
        $this->openCase(['severity' => 'medium']);
        $case = ComplianceCase::sole();

        $this->actingAs($this->admin)->post(route('admin.compliance.cases.notes', $case), [
            'note' => 'Seller confirmed the listings are intentional.', 'severity' => 'critical', 'investigate' => '1',
            'evidence' => [UploadedFile::fake()->create('chat.png', 50, 'image/png')],
        ])->assertSessionHasNoErrors();
        $this->assertSame(['investigating', 'critical'], [$case->fresh()->status, $case->fresh()->severity]);

        $this->actingAs($this->admin)->patch(route('admin.compliance.cases.resolve', $case), ['outcome' => 'resolved'])->assertSessionHasErrors('reason');
        $this->actingAs($this->admin)->patch(route('admin.compliance.cases.resolve', $case), ['outcome' => 'resolved', 'reason' => 'Listings removed after warning.'])
            ->assertSessionHasNoErrors();
        $this->assertSame('resolved', $case->fresh()->status);
        $this->assertNotNull($case->fresh()->resolved_at);

        // Closed cases accept no further changes.
        $this->actingAs($this->admin)->patch(route('admin.compliance.cases.resolve', $case), ['outcome' => 'dismissed', 'reason' => 'Trying to change the outcome.'])->assertStatus(409);
        $this->actingAs($this->admin)->post(route('admin.compliance.cases.notes', $case), ['note' => 'Late note.'])->assertStatus(409);

        $this->assertSame(
            [ComplianceAction::CASE_OPENED, ComplianceAction::NOTE, ComplianceAction::SEVERITY_CHANGED, ComplianceAction::CASE_RESOLVED],
            $case->actions()->pluck('action')->all(),
        );
    }

    // Warning recorded -----------------------------------------------------------------------

    public function test_warning_is_recorded_emailed_and_can_link_a_case(): void
    {
        $this->openCase();
        $case = ComplianceCase::sole();

        $this->actingAs($this->admin)->patch(route('admin.compliance.warn', $this->seller), [
            'warning' => 'Remove electronics listings within 3 days.', 'case_id' => $case->id,
        ])->assertSessionHasNoErrors();

        $warning = ComplianceAction::where('action', ComplianceAction::WARNING)->sole();
        $this->assertSame('Remove electronics listings within 3 days.', $warning->reason);
        $this->assertSame($case->id, $warning->compliance_case_id);
        Mail::assertSent(SellerWarningMail::class, fn ($mail) => $mail->hasTo($this->seller->email));
        $this->assertSame($warning->id, AuditLog::where('action', 'user.seller_warned')->sole()->metadata['compliance_action_id']);

        $this->actingAs($this->admin)->patch(route('admin.compliance.warn', $this->seller), ['warning' => 'short'])->assertSessionHasErrors('warning');
    }

    // Suspension recorded ----------------------------------------------------------------------

    public function test_suspension_and_reinstatement_are_recorded(): void
    {
        $this->openCase();
        $case = ComplianceCase::sole();

        $this->actingAs($this->admin)->patch(route('admin.compliance.suspend', $this->seller), ['reason' => 'Ignored the warning.'])
            ->assertSessionHasErrors('confirm');
        $this->assertSame('approved', $this->seller->fresh()->status);

        $this->actingAs($this->admin)->patch(route('admin.compliance.suspend', $this->seller), [
            'reason' => 'Ignored the warning about off-category listings.', 'confirm' => '1', 'case_id' => $case->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame('suspended', $this->seller->fresh()->status);
        $suspension = ComplianceAction::where('action', ComplianceAction::SUSPENSION)->sole();
        $this->assertSame($case->id, $suspension->compliance_case_id);
        $this->assertDatabaseHas('user_status_histories', ['user_id' => $this->seller->id, 'to_status' => 'suspended', 'reason' => 'Ignored the warning about off-category listings.']);
        $this->assertSame(Permission::SELLER_COMPLIANCE_MANAGE, AuditLog::where('action', 'user.status_changed')->sole()->permission);

        $this->actingAs($this->admin)->patch(route('admin.compliance.suspend', $this->seller), ['reason' => 'Suspending twice is invalid.', 'confirm' => '1'])->assertStatus(409);
        $this->actingAs($this->admin)->patch(route('admin.compliance.warn', $this->seller), ['warning' => 'Cannot warn a suspended seller.'])->assertForbidden();

        $this->actingAs($this->admin)->patch(route('admin.compliance.reinstate', $this->seller), ['reason' => 'Listings fixed and acknowledged.'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $this->seller->fresh()->status);
        $this->assertSame(1, ComplianceAction::where('action', ComplianceAction::REINSTATEMENT)->count());
        $this->assertSame(2, UserStatusHistory::where('user_id', $this->seller->id)->count());
    }

    // History stays accessible ---------------------------------------------------------------

    public function test_compliance_history_remains_accessible_and_append_only(): void
    {
        $this->openCase();
        $case = ComplianceCase::sole();
        $this->actingAs($this->admin)->patch(route('admin.compliance.warn', $this->seller), ['warning' => 'First and final warning.']);
        $this->actingAs($this->admin)->patch(route('admin.compliance.cases.resolve', $case), ['outcome' => 'dismissed', 'reason' => 'Category list was outdated.']);

        $this->actingAs($this->admin)->get(route('admin.compliance.seller', $this->seller))->assertOk()
            ->assertSeeInOrder(['Registered category', 'Product violations', 'Warnings', 'Suspensions', 'Compliance cases', 'Compliance history'])
            ->assertSee('First and final warning.')->assertSee('Case dismissed')->assertSee('Category list was outdated.');
        $this->actingAs($this->admin)->get(route('admin.compliance.cases', ['status' => 'dismissed']))->assertSee('#' . $case->id);
        $this->actingAs($this->admin)->get(route('admin.users.show', $this->seller))->assertSee('Warning issued')->assertSee('First and final warning.');

        $this->expectException(LogicException::class);
        ComplianceAction::first()->update(['reason' => 'tampered']);
    }

    public function test_risk_indicator_reflects_open_cases_and_warnings(): void
    {
        $this->actingAs($this->admin)->get(route('admin.compliance.seller', $this->seller))->assertSee('No flags');

        $this->openCase(['severity' => 'high']);                                                              // 3
        $this->actingAs($this->admin)->patch(route('admin.compliance.warn', $this->seller), ['warning' => 'Please fix your listings now.']); // +1
        $this->product(['name' => 'Charger', 'category' => 'Electronics']);                                  // +1

        $this->actingAs($this->admin)->get(route('admin.compliance.seller', $this->seller))->assertSee('risk-medium', false)
            ->assertSee('Open-case severity points')->assertSee('Warnings in 90 days');
        $this->openCase(['severity' => 'critical', 'type' => 'counterfeit', 'description' => 'Counterfeit branded bags reported by buyers.']); // +5
        $this->actingAs($this->admin)->get(route('admin.compliance'))->assertSee('risk-high', false);
    }

    public function test_view_only_access_and_non_sellers(): void
    {
        $buyer = $this->user('buyer');
        $this->actingAs($this->admin)->get(route('admin.compliance.seller', $buyer))->assertNotFound();

        config(['permissions.roles.admin' => [Permission::SELLER_COMPLIANCE_VIEW]]);
        $this->actingAs($this->admin)->get(route('admin.compliance.seller', $this->seller))->assertOk()
            ->assertDontSee('Open a compliance case')->assertDontSee('Issue warning')->assertDontSee('Suspend seller');
        $this->openCase()->assertForbidden();
        $this->actingAs($this->admin)->patch(route('admin.compliance.warn', $this->seller), ['warning' => 'Should not be allowed.'])->assertForbidden();
    }
}
