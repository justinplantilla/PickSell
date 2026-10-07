<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BranchRider;
use App\Models\Complaint;
use App\Models\ComplianceCase;
use App\Models\FinancialTransaction;
use App\Models\LogisticsException;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\ParcelScan;
use App\Models\Refund;
use App\Models\RegistrationReview;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Models\UserStatusHistory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Tests\TestCase;

class ModelRelationshipBlueprintTest extends TestCase
{
    public function test_user_relationships_match_the_model_relationship_blueprint(): void
    {
        $user = new User;

        $this->assertHasMany($user->registrationReviews(), RegistrationReview::class, 'user_id');
        $this->assertHasMany($user->statusHistories(), UserStatusHistory::class, 'user_id');
        $this->assertHasMany($user->complianceCases(), ComplianceCase::class, 'seller_id');
        $this->assertHasMany($user->complaints(), Complaint::class, 'filed_by');
        $this->assertHasMany($user->ordersAsBuyer(), Order::class, 'buyer_id');
        $this->assertHasMany($user->ordersAsSeller(), Order::class, 'seller_id');
        $this->assertHasMany($user->ordersAsCourier(), Order::class, 'courier_id');
        $this->assertHasMany($user->courierAssignments(), BranchRider::class, 'user_id');
        $this->assertHasMany($user->branchAssignments(), BranchRider::class, 'user_id');
        $this->assertHasMany($user->auditLogs(), AuditLog::class, 'actor_id');
        $this->assertHasMany($user->financialTransactions(), FinancialTransaction::class, 'seller_id');
    }

    public function test_order_relationships_match_the_model_relationship_blueprint(): void
    {
        $order = new Order;

        $this->assertBelongsTo($order->buyer(), User::class, 'buyer_id');
        $this->assertBelongsTo($order->seller(), User::class, 'seller_id');
        $this->assertBelongsTo($order->courier(), User::class, 'courier_id');
        $this->assertHasMany($order->statusHistories(), OrderStatusHistory::class, 'order_id');
        $this->assertHasMany($order->parcelScans(), ParcelScan::class, 'order_id');
        $this->assertHasMany($order->logisticsExceptions(), LogisticsException::class, 'order_id');
        $this->assertHasMany($order->complaints(), Complaint::class, 'order_id');
        $this->assertHasOne($order->returns(), ReturnRequest::class, 'order_id');
        $this->assertHasOne($order->returnRequest(), ReturnRequest::class, 'order_id');
        $this->assertHasMany($order->refunds(), Refund::class, 'order_id');
        $this->assertHasMany($order->financialTransactions(), FinancialTransaction::class, 'order_id');
    }

    public function test_return_and_refund_relationships_resolve_their_linked_records(): void
    {
        $returnRequest = new ReturnRequest;
        $refund = new Refund;

        $this->assertBelongsTo($returnRequest->order(), Order::class, 'order_id');
        $this->assertBelongsTo($returnRequest->buyer(), User::class, 'buyer_id');
        $this->assertBelongsTo($returnRequest->seller(), User::class, 'seller_id');
        $this->assertHasMany($returnRequest->refunds(), Refund::class, 'return_request_id');
        $this->assertHasOne($returnRequest->refund(), Refund::class, 'return_request_id');
        $this->assertHasOne($returnRequest->latestRefund(), Refund::class, 'return_request_id');

        $this->assertBelongsTo($refund->order(), Order::class, 'order_id');
        $this->assertBelongsTo($refund->returnRequest(), ReturnRequest::class, 'return_request_id');
        $this->assertHasMany($refund->financialTransactions(), FinancialTransaction::class, 'reference_id');
        $discriminator = collect($refund->financialTransactions()->getQuery()->getQuery()->wheres)
            ->first(fn (array $where): bool => $where['type'] === 'Basic' && $where['column'] === 'reference_type');
        $this->assertSame('refund', $discriminator['value']);
    }

    private function assertHasMany(HasMany $relation, string $related, string $foreignKey): void
    {
        $this->assertInstanceOf($related, $relation->getRelated());
        $this->assertSame($foreignKey, $relation->getForeignKeyName());
    }

    private function assertHasOne(HasOne $relation, string $related, string $foreignKey): void
    {
        $this->assertInstanceOf($related, $relation->getRelated());
        $this->assertSame($foreignKey, $relation->getForeignKeyName());
    }

    private function assertBelongsTo(BelongsTo $relation, string $related, string $foreignKey): void
    {
        $this->assertInstanceOf($related, $relation->getRelated());
        $this->assertSame($foreignKey, $relation->getForeignKeyName());
    }
}
