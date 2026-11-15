<?php

namespace App\Http\Controllers\BuyerApi;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Support\BuyerApi\BuyerPresenter;
use App\Support\BuyerApi\OrderStatus;
use Illuminate\Http\Request;

/**
 * The buyer's notifications: the same rows as the website's buyer bell (order updates from the
 * seller and Pickrush, new chat messages), so reading them on one side marks them read on both.
 */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $notifications = $user->notifications()->latest()->take(50)->get();
        $orders = Order::whereIn('id', $notifications->map(fn ($n) => $this->payload($n)['order_id'] ?? null)->filter())
            ->with('product')->get()->keyBy('id');

        return response()->json([
            'notifications' => $notifications->map(function ($n) use ($orders) {
                $data = $this->payload($n);
                $order = $orders->get($data['order_id'] ?? null);
                $status = $data['status'] ?? null;

                return [
                    'id'          => $n->id,
                    'type'        => $order ? 'order' : 'message',
                    'status'      => $status ? (OrderStatus::LEGACY[$status] ?? $status) : null,
                    'title'       => $status ? (OrderStatus::EVENTS[OrderStatus::LEGACY[$status] ?? $status][0] ?? 'Order update') : ($order ? 'Order update' : ($data['title'] ?? 'New message')),
                    'body'        => $data['message'] ?? 'New notification',
                    'order_id'    => $order?->id,
                    'image'       => BuyerPresenter::image($order?->product?->primary_image),
                    'occurred_at' => BuyerPresenter::date($n->created_at),
                    'unread'      => $n->read_at === null,
                ];
            })->values(),
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'All caught up.', 'unread_count' => 0]);
    }

    /**
     * GET /legal/{terms|privacy}: the same documents the website shows (App\Support\LegalDocuments).
     * body is plain text for the app's text view; html, version and url come along for newer builds.
     */
    public function legal(string $document)
    {
        $title = \App\Support\LegalDocuments::DOCUMENTS[$document] ?? abort(404);

        return response()->json([
            'title' => $title,
            'version' => config('legal.version'),
            'effective_date' => config('legal.effective_date'),
            'url' => route($document),
            'body' => "Version ".config('legal.version')."

".\App\Support\LegalDocuments::text($document),
            'html' => \App\Support\LegalDocuments::html($document),
        ]);
    }

    /** The website stores some notification data JSON-encoded twice; accept both. */
    private function payload($notification): array
    {
        $data = $notification->data;
        while (is_string($data)) {
            $decoded = json_decode($data, true);
            if ($decoded === null) break;
            $data = $decoded;
        }

        return is_array($data) ? $data : [];
    }
}
