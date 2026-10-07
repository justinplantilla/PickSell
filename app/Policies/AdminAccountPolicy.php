<?php

namespace App\Policies;

use App\Auth\Permission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminAccountPolicy
{
    public function view(User $actor, User $account): bool
    {
        return $this->ownsAccount($actor, $account)
            && $actor->hasPermission(Permission::ACCOUNT_VIEW);
    }

    public function update(User $actor, User $account): bool
    {
        return $this->ownsAccount($actor, $account)
            && $actor->hasPermission(Permission::ACCOUNT_MANAGE);
    }

    public function changePassword(User $actor, User $account): bool
    {
        return $this->update($actor, $account);
    }

    public function delete(User $actor, User $account): bool
    {
        if (! $this->update($actor, $account)) {
            return false;
        }

        $anotherAdminExists = User::query()
            ->where('role', 'admin')
            ->where('status', 'approved')
            ->whereKeyNot($account->id)
            ->exists();

        return $anotherAdminExists && ! $this->hasHistoryReferences($account);
    }

    private function ownsAccount(User $actor, User $account): bool
    {
        return $actor->is($account) && $actor->hasPermission(Permission::ACCOUNT_VIEW);
    }

    private function hasHistoryReferences(User $account): bool
    {
        foreach ([
            'registration_reviews' => ['reviewed_by'],
            'setting_change_logs' => ['changed_by'],
            'user_status_histories' => ['changed_by'],
            'product_moderation_logs' => ['admin_id'],
            'compliance_cases' => ['opened_by'],
            'compliance_actions' => ['actor_id'],
            'logistics_exceptions' => ['opened_by'],
            'messages' => ['sender_id', 'receiver_id'],
            'complaints' => ['filed_by'],
            'orders' => ['buyer_id', 'seller_id'],
            'products' => ['seller_id'],
            'return_requests' => ['buyer_id', 'seller_id'],
            'financial_transactions' => ['created_by'],
            'refunds' => ['requested_by', 'approved_by'],
        ] as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)
                    && DB::table($table)->where($column, $account->id)->exists()) {
                    return true;
                }
            }
        }

        return false;
    }
}
