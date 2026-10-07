<?php

namespace App\Services\Admin;

use App\Auth\Permission;
use App\Mail\SellerWarningMail;
use App\Models\ComplianceAction;
use App\Models\ComplianceCase;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\AdminNotificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Seller compliance: cases, warnings, suspensions and reinstatements. Callers authorize first
 * (SellerCompliancePolicy). Every action is written to compliance_actions (with its reason and
 * evidence) and to the audit log in one transaction; emails go out after commit.
 *
 * Evidence files are stored on the private "local" disk and only served to compliance viewers.
 */
class ComplianceService
{
    public const EVIDENCE_DISK = 'local';

    /** Whether the last email attempt succeeded. */
    public bool $notified = true;

    public function __construct(
        private AuditLogger $audit,
        private UserModerationService $moderation,
        private AdminNotificationService $notifications,
    ) {}

    /** @param UploadedFile[] $evidence */
    public function openCase(User $seller, User $actor, array $data, array $evidence = []): ComplianceCase
    {
        return DB::transaction(function () use ($seller, $actor, $data, $evidence) {
            $case = ComplianceCase::create([
                'seller_id' => $seller->id,
                'opened_by' => $actor->id,
                'product_id' => $data['product_id'] ?? null,
                'type' => $data['type'],
                'severity' => $data['severity'],
                'status' => 'open',
                'description' => $data['description'],
            ]);
            $this->record($seller, $actor, ComplianceAction::CASE_OPENED, $data['description'], $case, $evidence,
                ['type' => $case->type, 'severity' => $case->severity, 'product_id' => $case->product_id]);
            $this->audit->record('compliance.case_opened', $case, [], ['seller_id' => $seller->id, 'type' => $case->type, 'severity' => $case->severity], Permission::SELLER_COMPLIANCE_MANAGE);
            $this->notifications->notifyAdmins(
                'compliance.violation',
                'Compliance case opened',
                "A {$case->severity}-severity compliance case was opened for {$seller->first_name} {$seller->last_name}.",
                route('admin.compliance.cases.show', ['case' => $case], false),
                priority: in_array($case->severity, ['high', 'critical'], true) ? 'critical' : 'normal',
            );

            return $case;
        });
    }

    /** Add a note and/or evidence; optionally move the case to investigating or change its severity. */
    public function addNote(ComplianceCase $case, User $actor, string $note, array $evidence = [], ?string $severity = null, bool $investigate = false): void
    {
        DB::transaction(function () use ($case, $actor, $note, $evidence, $severity, $investigate) {
            $locked = ComplianceCase::whereKey($case->id)->lockForUpdate()->first();
            abort_unless($locked->isOpen(), 409, 'This case is closed.');

            $this->record($case->seller, $actor, ComplianceAction::NOTE, $note, $case, $evidence);

            $changes = [];
            if ($investigate && $locked->status === 'open') {
                $changes['status'] = ['from' => 'open', 'to' => 'investigating'];
            }
            if ($severity && $severity !== $locked->severity) {
                $changes['severity'] = ['from' => $locked->severity, 'to' => $severity];
                $this->record($case->seller, $actor, ComplianceAction::SEVERITY_CHANGED, $note, $case, [], $changes['severity']);
            }
            if ($changes) {
                $case->update(collect($changes)->map(fn ($change) => $change['to'])->all());
                $this->audit->record('compliance.case_updated', $case, $changes, [], Permission::SELLER_COMPLIANCE_MANAGE);
            }
        });
    }

    public function resolveCase(ComplianceCase $case, User $actor, string $outcome, string $reason): void
    {
        DB::transaction(function () use ($case, $actor, $outcome, $reason) {
            $locked = ComplianceCase::whereKey($case->id)->lockForUpdate()->first();
            abort_unless($locked->isOpen(), 409, 'This case is already closed.');

            $case->update(['status' => $outcome, 'resolved_at' => now()]);
            $this->record($case->seller, $actor, $outcome === 'resolved' ? ComplianceAction::CASE_RESOLVED : ComplianceAction::CASE_DISMISSED, $reason, $case);
            $this->audit->record('compliance.case_' . $outcome, $case, ['status' => ['from' => $locked->status, 'to' => $outcome]],
                ['reason' => $reason], Permission::SELLER_COMPLIANCE_MANAGE);
        });
    }

    public function warn(User $seller, User $actor, string $warning, ?ComplianceCase $case = null): void
    {
        DB::transaction(function () use ($seller, $actor, $warning, $case) {
            $action = $this->record($seller, $actor, ComplianceAction::WARNING, $warning, $case);
            $this->audit->record('user.seller_warned', $seller, [], array_filter([
                'warning' => $warning, 'compliance_action_id' => $action->id, 'case_id' => $case?->id,
            ]), Permission::SELLER_COMPLIANCE_MANAGE);
        });

        try {
            Mail::to($seller->email)->send(new SellerWarningMail($seller, $warning));
            $this->notified = true;
        } catch (Throwable $exception) {
            $this->notified = false;
            Log::warning('Seller warning email could not be sent.', ['seller_id' => $seller->id, 'error' => $exception->getMessage()]);
        }
    }

    public function suspend(User $seller, User $actor, string $reason, ?ComplianceCase $case = null): void
    {
        $this->changeSellerStatus($seller, $actor, 'suspended', ComplianceAction::SUSPENSION, $reason, $case);
    }

    public function reinstate(User $seller, User $actor, string $reason, ?ComplianceCase $case = null): void
    {
        $this->changeSellerStatus($seller, $actor, 'approved', ComplianceAction::REINSTATEMENT, $reason, $case);
    }

    /**
     * Transparent risk indicator, not a verdict: open cases weighted by severity, recent warnings,
     * active listings outside the registered category, products held by Admin and open complaints.
     *
     * @return array{score: int, level: string, factors: array<string, int>}
     */
    public static function risk(int $openCaseWeight, int $recentWarnings, int $mismatchedProducts, int $heldProducts, int $openComplaints): array
    {
        $factors = [
            'Open-case severity points' => $openCaseWeight,
            'Warnings in 90 days' => $recentWarnings,
            'Listings outside registered category' => min($mismatchedProducts, 3),
            'Products archived by Admin' => min($heldProducts, 3),
            'Open complaints against seller' => min($openComplaints, 3),
        ];
        $score = array_sum($factors);

        return [
            'score' => $score,
            'level' => match (true) {
                $score >= 6 => 'high',
                $score >= 3 => 'medium',
                $score >= 1 => 'low',
                default => 'none',
            },
            'factors' => array_filter($factors),
        ];
    }

    private function changeSellerStatus(User $seller, User $actor, string $to, string $action, string $reason, ?ComplianceCase $case): void
    {
        $this->moderation->changeStatus($seller, $actor, $to, $reason,
            fn () => $this->record($seller, $actor, $action, $reason, $case, [], ['status' => $to]),
            Permission::SELLER_COMPLIANCE_MANAGE);
        $this->notified = $this->moderation->notified;
    }

    /** @param UploadedFile[] $evidence */
    private function record(User $seller, User $actor, string $action, ?string $reason, ?ComplianceCase $case = null, array $evidence = [], array $metadata = []): ComplianceAction
    {
        $attachments = collect($evidence)->map(fn (UploadedFile $file) => [
            'path' => $file->store("compliance/{$seller->id}", self::EVIDENCE_DISK),
            'name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ])->values()->all();

        return ComplianceAction::create([
            'seller_id' => $seller->id,
            'compliance_case_id' => $case?->id,
            'actor_id' => $actor->id,
            'action' => $action,
            'reason' => $reason,
            'attachments' => $attachments ?: null,
            'metadata' => array_filter($metadata, fn ($value) => $value !== null) ?: null,
        ]);
    }
}
