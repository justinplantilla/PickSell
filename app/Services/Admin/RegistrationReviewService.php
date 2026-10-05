<?php

namespace App\Services\Admin;

use App\Auth\Permission;
use App\Models\RegistrationReview;
use App\Models\User;
use App\Notifications\RegistrationApprovedNotification;
use App\Notifications\RegistrationDisapprovedNotification;
use App\Services\AuditLogger;
use App\Support\RegistrationChecklist;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records registration decisions. Callers authorize first (RegistrationPolicy); this service
 * re-checks the application under a row lock so two reviewers cannot both decide it. The status
 * change, the review record and the audit entry commit together; the applicant is notified after.
 */
class RegistrationReviewService
{
    /** Whether the last notification attempt reached the mailer (false = decision saved, email failed). */
    public bool $notified = true;

    public function __construct(private AuditLogger $audit) {}

    /** @param array<string, bool> $checklist confirmed checklist items */
    public function approve(User $applicant, User $reviewer, array $checklist, ?string $note = null): RegistrationReview
    {
        $review = $this->decide($applicant, $reviewer, RegistrationReview::APPROVED, $note, $checklist);
        $this->notify($applicant, new RegistrationApprovedNotification());

        return $review;
    }

    /** @param array<string, bool> $checklist items the reviewer had confirmed (optional) */
    public function disapprove(User $applicant, User $reviewer, string $reason, array $checklist = []): RegistrationReview
    {
        $review = $this->decide($applicant, $reviewer, RegistrationReview::DISAPPROVED, $reason, $checklist);
        $this->notify($applicant, new RegistrationDisapprovedNotification($reason));

        return $review;
    }

    private function decide(User $applicant, User $reviewer, string $decision, ?string $reason, array $checklist): RegistrationReview
    {
        return DB::transaction(function () use ($applicant, $reviewer, $decision, $reason, $checklist) {
            $locked = User::whereKey($applicant->id)->lockForUpdate()->first();
            abort_unless($locked && $locked->status === 'pending', 409, 'This application has already been decided.');

            $applicant->statusChangeReason = $reason;
            $applicant->statusChangedBy = $reviewer->id;
            $applicant->update(['status' => $decision]);

            $review = RegistrationReview::create([
                'user_id' => $applicant->id,
                'reviewed_by' => $reviewer->id,
                'decision' => $decision,
                'reason' => $reason,
                'verification_snapshot' => $this->snapshot($applicant, $checklist),
                'reviewed_at' => now(),
            ]);

            $this->audit->record(
                $decision === RegistrationReview::APPROVED ? 'user.registration_approved' : 'user.registration_disapproved',
                $applicant,
                ['status' => ['from' => 'pending', 'to' => $decision]],
                array_filter(['review_id' => $review->id, 'reason' => $reason]),
                Permission::REGISTRATIONS_MANAGE,
            );

            return $review;
        });
    }

    /** What the reviewer saw and confirmed, frozen at decision time. */
    private function snapshot(User $applicant, array $checklist): array
    {
        return [
            'checklist' => collect(RegistrationChecklist::for($applicant))->map(fn ($item, $key) => [
                'label' => $item['label'],
                'confirmed' => (bool) ($checklist[$key] ?? false),
                'document_available' => $item['available'],
            ])->all(),
            'documents' => collect(RegistrationChecklist::documents($applicant))->map(fn ($document) => $document['path'])->all(),
            'applicant' => array_filter([
                'name' => $applicant->full_name,
                'email' => $applicant->email,
                'role' => $applicant->role,
                'business_name' => $applicant->business_name,
                'submitted_at' => $applicant->created_at?->toIso8601String(),
            ]),
        ];
    }

    private function notify(User $applicant, $notification): void
    {
        try {
            $applicant->notify($notification);
            $this->notified = true;
        } catch (Throwable $exception) {
            // The decision stands; surface the delivery failure instead of rolling back.
            $this->notified = false;
            Log::warning('Registration decision notification could not be sent.', [
                'user_id' => $applicant->id,
                'notification' => class_basename($notification),
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
