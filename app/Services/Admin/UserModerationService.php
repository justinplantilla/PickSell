<?php

namespace App\Services\Admin;

use App\Auth\Permission;
use App\Mail\AccountStatusChangedMail;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Account status changes from the Admin portal (seller warnings live in ComplianceService). Callers authorize first
 * (UserPolicy); the transition is re-checked under a row lock. The status change, its history row
 * (written by User::booted) and the audit entry commit together; the email is sent after.
 *
 * Status model (unchanged): pending, approved, disapproved, suspended, deactivated.
 * pending → approved/disapproved belongs to Registrations, not to this service.
 */
class UserModerationService
{
    /** Allowed account-status transitions from the User Accounts module. */
    public const TRANSITIONS = [
        'approved' => ['suspended', 'deactivated'],
        'suspended' => ['approved', 'deactivated'],
        'deactivated' => ['approved'],
    ];

    /** Transitions that block sign-in, so they need a reason and an explicit confirmation. */
    public const REQUIRES_CONFIRMATION = ['suspended', 'deactivated'];

    public const ACTION_LABELS = [
        'approved' => 'Reactivate',
        'suspended' => 'Suspend',
        'deactivated' => 'Deactivate',
    ];

    /** Whether the last email attempt succeeded (false = change saved, email failed). */
    public bool $notified = true;

    public function __construct(private AuditLogger $audit) {}

    public static function allowedTransitions(string $from): array
    {
        return self::TRANSITIONS[$from] ?? [];
    }

    /**
     * @param  callable|null  $alsoRecord  runs inside the same transaction after the change, so a
     *                                     caller's own record (e.g. a compliance action) commits with it
     */
    public function changeStatus(User $user, User $actor, string $to, ?string $reason = null, ?callable $alsoRecord = null, string $permission = Permission::USERS_MANAGE): void
    {
        DB::transaction(function () use ($user, $actor, $to, $reason, $alsoRecord, $permission) {
            $current = User::whereKey($user->id)->lockForUpdate()->value('status');
            abort_unless(in_array($to, self::allowedTransitions((string) $current), true), 409, "This account can no longer move from {$current} to {$to}.");

            $user->statusChangeReason = $reason;
            $user->statusChangedBy = $actor->id;
            $user->update(['status' => $to]);

            $this->audit->record('user.status_changed', $user, ['status' => ['from' => $current, 'to' => $to]],
                array_filter(['reason' => $reason, 'history_id' => $user->statusHistories()->value('id')]), $permission);

            if ($alsoRecord) {
                $alsoRecord();
            }
        });

        try {
            Mail::to($user->email)->send(new AccountStatusChangedMail($user, $to));
            $this->notified = true;
        } catch (Throwable $exception) {
            $this->notified = false;
            Log::warning('Account status email could not be sent.', ['user_id' => $user->id, 'status' => $to, 'error' => $exception->getMessage()]);
        }
    }
}
