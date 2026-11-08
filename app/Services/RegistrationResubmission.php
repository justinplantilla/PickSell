<?php

namespace App\Services;

use App\Models\RegistrationReview;
use App\Models\User;
use App\Support\SellerTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * A rejected applicant (seller or buyer) corrects their documents and sends the same account back
 * for review: it returns to Pending Approval and reappears in the admin's pending list, with the
 * history kept. Used by the website's resubmit page and the buyer app.
 */
class RegistrationResubmission
{
    private const FILE_RULES = ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'];

    public function __construct(private AdminNotificationService $notifications) {}

    public static function canResubmit(?User $user): bool
    {
        return $user !== null && in_array($user->role, ['seller', 'buyer'], true) && $user->status === 'disapproved';
    }

    /** Why the latest review turned the application down. */
    public function reason(User $applicant): ?string
    {
        return RegistrationReview::where('user_id', $applicant->id)
            ->where('decision', RegistrationReview::DISAPPROVED)
            ->latest('reviewed_at')
            ->value('reason');
    }

    /** Documents this applicant may replace, keyed by field: a buyer's ID; a seller's ID and business papers. @return array<string, string> */
    public function documents(User $applicant): array
    {
        if ($applicant->role === 'buyer') {
            return ['id_upload' => 'Government-issued ID'];
        }
        $fields = $applicant->seller_type
            ? SellerTypes::requiredDocuments($applicant->seller_type)
            : ['business_permit'];

        return ['id_upload' => 'Government-issued ID']
            + collect($fields)->mapWithKeys(fn (string $field) => [
                $field => SellerTypes::DOCUMENT_LABELS[$field] ?? 'Business permit',
            ])->all();
    }

    /**
     * Stores the corrected documents and puts the application back in the queue.
     *
     * @return list<string> the documents replaced
     *
     * @throws ValidationException on a bad file, or "documents" when nothing was uploaded
     */
    public function submit(User $applicant, Request $request): array
    {
        $documents = $this->documents($applicant);
        Validator::make($request->allFiles(), array_map(fn () => self::FILE_RULES, $documents),
            collect(array_keys($documents))->flatMap(fn (string $field) => [
                "{$field}.mimes" => 'Only JPG, PNG and PDF files are accepted.',
                "{$field}.max" => 'Each file must be 5 MB or smaller.',
            ])->all(),
        )->validate();

        $uploads = collect(array_keys($documents))
            ->filter(fn (string $field) => $request->hasFile($field))
            ->mapWithKeys(fn (string $field) => [$field => $request->file($field)->store(
                $field === 'id_upload' ? 'uploads/ids' : 'uploads/permits',
                'public',
            )]);
        if ($uploads->isEmpty()) {
            throw ValidationException::withMessages(['documents' => 'Upload at least one corrected document before resubmitting.']);
        }

        $replaced = $uploads->keys()->map(fn ($field) => $documents[$field])->all();
        $applicant->statusChangeReason = 'Resubmitted with corrected documents: '.implode(', ', $replaced).'.';
        $applicant->statusChangedBy = $applicant->id;
        $applicant->update([...$uploads->all(), 'status' => 'pending']);

        $this->notifications->notifyAdmins(
            'registration.pending',
            'Resubmitted registration',
            "{$applicant->first_name} {$applicant->last_name} resubmitted their {$applicant->role} registration with corrected documents.",
            route('admin.registrations.show', $applicant, false),
            subject: "registration:{$applicant->id}",
        );

        return $replaced;
    }
}
