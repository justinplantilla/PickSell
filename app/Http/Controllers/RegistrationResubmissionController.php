<?php

namespace App\Http\Controllers;

use App\Models\RegistrationReview;
use App\Models\User;
use App\Services\AdminNotificationService;
use App\Support\SellerTypes;
use Illuminate\Http\Request;

/**
 * A rejected seller corrects their documents and sends the application back for review.
 * They reach this page by logging in with their email and password (AuthController::login), which does not
 * sign them in; the application then returns to Pending Approval and reappears in the admin's pending list.
 */
class RegistrationResubmissionController extends Controller
{
    private const FILE_RULES = ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'];

    public function show(Request $request)
    {
        $applicant = $this->applicant($request);
        if (! $applicant) {
            return redirect('/login');
        }

        return view('auth.resubmit', [
            'applicant' => $applicant,
            'documents' => $this->documents($applicant),
            'reason' => RegistrationReview::where('user_id', $applicant->id)
                ->where('decision', RegistrationReview::DISAPPROVED)
                ->latest('reviewed_at')
                ->value('reason'),
        ]);
    }

    public function store(Request $request, AdminNotificationService $notifications)
    {
        $applicant = $this->applicant($request);
        if (! $applicant) {
            return redirect('/login')->withErrors(['email' => 'Log in again to resubmit your application.']);
        }

        $documents = $this->documents($applicant);
        $request->validate(
            array_map(fn () => self::FILE_RULES, $documents),
            collect(array_keys($documents))->flatMap(fn (string $field) => [
                "{$field}.mimes" => 'Only JPG, PNG and PDF files are accepted.',
                "{$field}.max" => 'Each file must be 5 MB or smaller.',
            ])->all(),
        );

        $uploads = collect(array_keys($documents))
            ->filter(fn (string $field) => $request->hasFile($field))
            ->mapWithKeys(fn (string $field) => [$field => $request->file($field)->store(
                $field === 'id_upload' ? 'uploads/ids' : 'uploads/permits',
                'public',
            )]);
        if ($uploads->isEmpty()) {
            return back()->withErrors(['documents' => 'Upload at least one corrected document before resubmitting.']);
        }

        $applicant->statusChangeReason = 'Resubmitted with corrected documents: '.$uploads->keys()->map(fn ($field) => $documents[$field])->join(', ').'.';
        $applicant->statusChangedBy = $applicant->id;
        $applicant->update([...$uploads->all(), 'status' => 'pending']);

        $notifications->notifyAdmins(
            'registration.pending',
            'Resubmitted registration',
            "{$applicant->first_name} {$applicant->last_name} resubmitted their {$applicant->role} registration with corrected documents.",
            route('admin.registrations.show', $applicant, false),
            subject: "registration:{$applicant->id}",
        );
        $request->session()->forget('resubmission_user_id');

        return redirect('/login')->with('success', 'Your application was resubmitted and is pending approval again. You will be notified via email.');
    }

    private function applicant(Request $request): ?User
    {
        return User::whereKey($request->session()->get('resubmission_user_id'))
            ->where('role', 'seller')
            ->where('status', 'disapproved')
            ->first();
    }

    /** Documents this applicant may replace, keyed by field. @return array<string, string> */
    private function documents(User $applicant): array
    {
        $fields = $applicant->seller_type
            ? SellerTypes::requiredDocuments($applicant->seller_type)
            : ['business_permit'];

        return ['id_upload' => 'Government-issued ID']
            + collect($fields)->mapWithKeys(fn (string $field) => [
                $field => SellerTypes::DOCUMENT_LABELS[$field] ?? 'Business permit',
            ])->all();
    }
}
