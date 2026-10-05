<?php

namespace App\Services\Admin;

use App\Auth\Permission;
use App\Models\Product;
use App\Models\ProductModerationLog;
use App\Models\User;
use App\Notifications\ProductModeratedNotification;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Admin product moderation. Callers authorize first (ProductPolicy); state is re-checked under a
 * row lock. Product change, moderation log and audit entry commit together; the seller is
 * notified after commit.
 */
class ProductModerationService
{
    public function __construct(private AuditLogger $audit) {}

    public function setStatus(Product $product, User $admin, string $status, ?string $reason): ProductModerationLog
    {
        $log = DB::transaction(function () use ($product, $admin, $status, $reason) {
            $current = Product::whereKey($product->id)->lockForUpdate()->first(['status', 'is_featured']);
            abort_if($current->status === $status, 409, "This product is already {$status}.");

            $values = ['status' => $status];
            // An archived product cannot stay in the featured shelf.
            if ($status === 'archived' && $current->is_featured) {
                $values['is_featured'] = false;
            }
            $changes = AuditLogger::diff($product, $values, ['status', 'is_featured']);
            $product->update($values);

            $log = $product->moderationLogs()->create([
                'admin_id' => $admin->id,
                'action' => $status === 'archived' ? ProductModerationLog::ARCHIVED : ProductModerationLog::RESTORED,
                'from_status' => $current->status,
                'to_status' => $status,
                'reason' => $reason,
            ]);
            $this->audit->record('product.status_changed', $product, $changes,
                array_filter(['reason' => $reason, 'moderation_log_id' => $log->id]), Permission::PRODUCTS_MODERATE);

            return $log;
        });

        $this->notifySeller($product, $log);

        return $log;
    }

    public function toggleFeatured(Product $product, User $admin, ?string $reason): ProductModerationLog
    {
        $log = DB::transaction(function () use ($product, $admin, $reason) {
            $current = Product::whereKey($product->id)->lockForUpdate()->first(['status', 'is_featured']);
            $feature = ! $current->is_featured;
            abort_if($feature && $current->status !== 'active', 409, 'Only active products can be featured.');

            $values = ['is_featured' => $feature];
            $changes = AuditLogger::diff($product, $values, ['is_featured']);
            $product->update($values);

            $log = $product->moderationLogs()->create([
                'admin_id' => $admin->id,
                'action' => $feature ? ProductModerationLog::FEATURED : ProductModerationLog::UNFEATURED,
                'from_status' => $current->status,
                'to_status' => $current->status,
                'reason' => $reason,
            ]);
            $this->audit->record($feature ? 'product.featured' : 'product.unfeatured', $product, $changes,
                array_filter(['reason' => $reason, 'moderation_log_id' => $log->id]), Permission::PRODUCTS_MODERATE);

            return $log;
        });

        $this->notifySeller($product, $log);

        return $log;
    }

    private function notifySeller(Product $product, ProductModerationLog $log): void
    {
        try {
            $product->seller?->notify(new ProductModeratedNotification($product, $log));
        } catch (Throwable $exception) {
            Log::warning('Seller could not be notified about product moderation.', ['product_id' => $product->id, 'error' => $exception->getMessage()]);
        }
    }
}
