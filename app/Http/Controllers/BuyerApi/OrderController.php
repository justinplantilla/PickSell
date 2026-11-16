<?php

namespace App\Http\Controllers\BuyerApi;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Http\Controllers\BuyerReturnRequestController;
use App\Http\Controllers\DeliveryProblemController;
use App\Models\Complaint;
use App\Models\LogisticsIssue;
use App\Models\Order;
use App\Models\ProductReview;
use App\Models\ProductReviewPhoto;
use App\Models\ReturnRequest;
use App\Services\Logistics\LogisticsIssueDesk;
use App\Services\OffPlatform\OffPlatformMonitor;
use App\Services\Orders\OrderCancellationService;
use App\Services\Orders\OrderRating;
use App\Services\Orders\OrderReceipt;
use App\Services\Returns\ReturnFlow;
use App\Support\BuyerApi\BuyerPresenter;
use App\Support\BuyerApi\OrderStatus;
use App\Support\OrderDelivery;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** The buyer's orders, read from and written to the website's tables with the website's rules. */
class OrderController extends Controller
{
    /** POST /buyer/orders/{order}/received — the buyer confirms it reached them; the order completes and can be rated. */
    public function received(Request $request, Order $order, OrderReceipt $receipt)
    {
        $receipt->confirm($this->find($request, $order), $request->user());

        return $this->detail($order);
    }

    /** GET /buyer/orders/{order}/proof-of-delivery — the rider's photo at the door (private; the buyer only). */
    public function proofOfDelivery(Request $request, Order $order)
    {
        $proof = OrderDelivery::proof($this->find($request, $order));
        abort_unless($proof && Storage::disk('local')->exists($proof->proof_photo_path), 404, 'No delivery photo for this order yet.');

        return Storage::disk('local')->response($proof->proof_photo_path, "delivery-{$order->order_number}.jpg", ['Cache-Control' => 'private, max-age=3600']);
    }

    private function find(Request $request, Order $order): Order
    {
        abort_if($order->buyer_id !== $request->user()->id, 404, 'Order not found.');

        return $order;
    }

    private function detail(Order $order)
    {
        $order->refresh()->load('seller', 'product', 'buyer', 'returnRequest.refund', 'statusHistories');

        return response()->json(['order' => BuyerPresenter::orderDetail($order)]);
    }

    /** GET /buyer/orders?tab=to_ship|in_transit|out_for_delivery|to_rate|completed|cancelled|all */
    public function index(Request $request)
    {
        $data = $request->validate(['tab' => ['nullable', Rule::in(['all', ...array_keys(OrderStatus::TABS)])]]);
        $orders = Order::where('buyer_id', $request->user()->id)->with('seller', 'product', 'items.product', 'payment')->latest()->get();

        $counts = collect(array_keys(OrderStatus::TABS))->mapWithKeys(fn ($tab) => [$tab => 0])->all();
        $orders->each(function (Order $o) use (&$counts) { $counts[OrderStatus::tab($o)]++; });

        $tab = $data['tab'] ?? 'all';
        if ($tab !== 'all') {
            $orders = $orders->filter(fn (Order $o) => OrderStatus::tab($o) === $tab);
        }

        return response()->json([
            'orders' => $orders->map(fn ($o) => BuyerPresenter::orderCard($o))->values(),
            'counts' => $counts,
        ]);
    }

    public function show(Request $request, Order $order)
    {
        return $this->detail($this->find($request, $order));
    }

    /** POST /buyer/orders/{id}/cancel {reason?} — website rule: Placed or Confirmed only (not once the seller is preparing). */
    public function cancel(Request $request, Order $order, OrderCancellationService $cancellations)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $cancellations->cancel($this->find($request, $order), $request->user(), 'buyer', $data['reason'] ?? null);

        return $this->detail($order);
    }

    /**
     * POST /buyer/orders/{id}/rate {rating (product), seller_rating, delivery_rating, feedback?, photos[]?}
     * Photos are multipart, up to 3. Once the seller has confirmed delivery (completed) the
     * product rating is also the product review, as on the website.
     */
    public function rate(Request $request, Order $order, OrderRating $ratings)
    {
        $this->find($request, $order);
        // The product, the seller's service and the delivery are all rated, as on the website's rating form.
        $data = $request->validate([
            'rating'          => 'required|integer|min:1|max:5',
            'seller_rating'   => 'required|integer|min:1|max:5',
            'delivery_rating' => 'required|integer|min:1|max:5',
            'feedback'        => 'nullable|string|max:1000',
            'photos'          => 'nullable|array|max:'.ProductReviewPhoto::MAX_PER_REVIEW,
            'photos.*'        => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ], [
            'seller_rating.required'   => 'Rate the seller’s service.',
            'delivery_rating.required' => 'Rate the delivery.',
        ]);
        abort_unless(in_array(OrderStatus::of($order), OrderStatus::RATEABLE, true), 422, 'Tap Order Received first, then rate your order.');
        abort_if($order->rating !== null, 422, 'You already rated this order.');

        $ratings->rate($order, $data['rating'], $data['seller_rating'], $data['delivery_rating'], $data['feedback'] ?? null, $request->file('photos', []));

        return $this->detail($order);
    }

    /** POST /buyer/orders/{id}/report {category, details, evidence[]?} — becomes a complaint for admins. */
    public function report(Request $request, Order $order)
    {
        $this->find($request, $order);
        $data = $request->validate([
            'category'   => ['required', Rule::in(config('buyer-api.report_categories'))],
            'details'    => 'required|string|min:10|max:2000',
            'evidence'   => 'nullable|array|max:5',
            'evidence.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);
        abort_if(in_array(OrderStatus::of($order), ['placed', 'cancelled'], true), 422, 'You can report a problem once the seller is handling your order.');

        // The website's complaint page shows one evidence file.
        $evidence = $request->file('evidence', [])[0] ?? null;
        $complaint = Complaint::create([
            'filed_by'        => $request->user()->id,
            'against_user_id' => $order->seller_id,
            'order_id'        => $order->id,
            'subject'         => "{$data['category']} — order {$order->order_number}",
            'details'         => $data['details'],
            'evidence_path'   => $evidence?->store('complaints', 'public'),
            'status'          => 'open',
        ]);

        return response()->json([
            'message' => 'Thanks, we received your report. PickSell support will review it.',
            'ticket'  => $this->ticket($complaint->load('order')),
        ], 201);
    }

    /**
     * POST /buyer/orders/{id}/delivery-problem {type, description, evidence?} — the website's
     * "Report a delivery problem" (DeliveryProblemController): goes to the logistics company
     * carrying the parcel as a logistics issue, not to the seller.
     */
    public function deliveryProblem(Request $request, Order $order, LogisticsIssueDesk $desk)
    {
        $this->find($request, $order);
        abort_unless(in_array($order->status, DeliveryProblemController::REPORTABLE, true), 422, 'You can report a delivery problem once the order is with the logistics company.');
        $data = $request->validate([
            'type'        => ['required', Rule::in(LogisticsIssue::BUYER_REPORTS)],
            'description' => 'required|string|min:10|max:2000',
            'evidence'    => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ], ['description.min' => 'Describe what happened (at least 10 characters).']);

        try {
            $desk->report($request->user(), $order, $data['type'], $data['description'], $request->file('evidence'));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Thanks, PickSell received your report and will follow it up with the logistics company.'], 201);
    }

    /**
     * POST /buyer/orders/{id}/report-off-platform {details} — the website's "Seller asked you to pay
     * or ship outside PickSell?" (OffPlatformReportController): a flag for admins to review the
     * seller; the seller isn't told who reported.
     */
    public function reportOffPlatform(Request $request, Order $order, OffPlatformMonitor $monitor)
    {
        $this->find($request, $order);
        $data = $request->validate(['details' => 'required|string|min:10|max:2000'], ['details.min' => 'Tell us what the seller asked (at least 10 characters).']);

        try {
            $monitor->buyerReport($request->user(), $order, $data['details']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Thank you. PickSell will review this seller. Keep paying and chatting on PickSell so your order stays protected.'], 201);
    }

    /** GET /buyer/orders/{id}/return/waybill — the Pickrush return label PDF to print and stick on the package. */
    public function returnWaybill(Request $request, Order $order)
    {
        $this->find($request, $order);

        return BuyerReturnRequestController::waybillFor($order);
    }

    /**
     * POST /buyer/orders/{id}/return {reason, details, quantity?, gcash_number, gcash_name, evidence[]?}
     * — the website's return request (ReturnFlow): the seller reviews it first.
     */
    public function requestReturn(Request $request, Order $order, ReturnFlow $flow)
    {
        $this->find($request, $order);

        // The app may send the label it showed; store the website's code.
        $request->merge(['reason' => array_search($request->input('reason'), ReturnRequest::REASONS, true) ?: $request->input('reason')]);
        // Same limits as the website: up to 5 photos or videos, 10 MB each ("evidence" from the app).
        $fileRules = ['nullable', 'array', 'max:5'];
        $eachFile = 'file|mimes:jpg,jpeg,png,webp,mp4,webm|max:10240';
        $data = $request->validate(BuyerReturnRequestController::rules($order) + [
            'evidence'      => $fileRules,
            'evidence.*'    => $eachFile,
            'attachments'   => $fileRules,
            'attachments.*' => $eachFile,
        ], BuyerReturnRequestController::messages());
        $files = [...$request->file('evidence', []), ...$request->file('attachments', [])];
        abort_if(count($files) > 5, 422, 'Attach up to 5 photos or videos.');

        $returnRequest = $flow->request($order, $request->user(), $data, $files);

        return response()->json([
            'message'        => 'Return/refund request sent to the seller.',
            'return_request' => BuyerPresenter::returnRequest($returnRequest),
        ], 201);
    }

    /** POST /buyer/orders/{id}/reorder — puts the product back in the cart. */
    public function reorder(Request $request, Order $order)
    {
        $this->find($request, $order);
        $cart = Cart::firstOrCreate(['buyer_id' => $request->user()->id]);
        $ids = app(\App\Services\Orders\Reorder::class)->add($order, $cart);
        return response()->json([
            'message' => 'Order items are back in your cart.', 'cart_item_id' => $ids[0], 'cart_item_ids' => $ids,
            'cart_count' => (int) CartItem::where('cart_id', $cart->id)->count(),
        ]);
    }

    /** GET /buyer/reorder — products from past orders, one entry each. */
    public function reorderCandidates(Request $request)
    {
        $orders = Order::where('buyer_id', $request->user()->id)->whereNotNull('product_id')
            ->whereIn('status', ['delivered', 'completed', 'cancelled', 'returned'])
            ->with('product.seller', 'seller')->latest()->get()->unique('product_id')->take(20);

        return response()->json(['items' => $orders->map(function (Order $o) {
            $product = $o->product;
            $last = round($o->amount / max((int) $o->quantity, 1), 2);
            $current = $product ? BuyerPresenter::money($product->effective_price) : null;

            return [
                'order_id'      => $o->id,
                'product'       => $product ? BuyerPresenter::product($product) : null,
                'product_name'  => $o->product_name,
                'seller_name'   => $o->seller?->business_name ?: $o->seller?->full_name,
                'last_price'    => $last,
                'current_price' => $current,
                'price_changed' => $current !== null && abs($current - $last) >= 0.01,
                'available'     => $product && $product->isPurchasable() && $product->stock > 0,
            ];
        })->values()]);
    }

    /** GET /buyer/support/tickets — the buyer's complaints and their status on the website. */
    public function tickets(Request $request)
    {
        $tickets = Complaint::where('filed_by', $request->user()->id)->with('order')->latest()->get();

        return response()->json(['tickets' => $tickets->map(fn ($c) => $this->ticket($c))->values()]);
    }

    public function options()
    {
        return response()->json([
            'cancel_reasons'     => config('buyer-api.cancel_reasons'),
            'report_categories'  => config('buyer-api.report_categories'),
            'delivery_problem_types' => collect(LogisticsIssue::BUYER_REPORTS)
                ->map(fn ($t) => ['value' => $t, 'label' => LogisticsIssue::TYPES[$t]])->values(),
            // Seller-fault reasons only (ReturnRequest::BUYER_REASONS).
            'return_reasons'     => array_values(array_intersect_key(ReturnRequest::REASONS, array_flip(ReturnRequest::BUYER_REASONS))),
            'refund_methods'     => [],
        ]);
    }

    private function ticket(Complaint $c): array
    {
        return [
            'id'           => $c->id,
            'reference'    => 'PS-C'.str_pad((string) $c->id, 5, '0', STR_PAD_LEFT),
            'subject'      => $c->subject,
            'status'       => $c->status,
            'order_number' => $c->order?->order_number,
            'order_id'     => $c->order_id,
            'admin_notes'  => $c->admin_notes,
            'created_at'   => BuyerPresenter::date($c->created_at),
            'updated_at'   => BuyerPresenter::date($c->updated_at),
        ];
    }
}
