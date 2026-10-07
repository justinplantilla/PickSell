<?php

namespace App\Http\Controllers;

use App\Mail\NewMessageMail;
use App\Models\Message;
use App\Models\Order;
use App\Services\Orders\OrderLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class CourierController extends Controller
{
    private function courier()
    {
        return auth()->user();
    }

    public function dashboard(Request $request)
    {
        $courier = $this->courier();
        $status = $request->get('status', 'all');
        $query = Order::where('courier_id', $courier->id)->with(['buyer', 'seller']);
        if ($status === 'delivered') {
            $query->whereIn('status', ['delivered', 'completed']);
        } elseif (in_array($status, ['assigned_to_rider', 'out_for_delivery', 'delivery_failed', 'returned'], true)) {
            $query->where('status', $status);
        } else {
            $status = 'all';
        }

        $orders = $query->latest()->paginate(15);
        $stats = [
            'assigned' => Order::where('courier_id', $courier->id)->whereIn('status', ['assigned_to_rider', 'out_for_delivery'])->count(),
            'in_transit' => Order::where('courier_id', $courier->id)->where('status', 'out_for_delivery')->count(),
            'delivered' => Order::where('courier_id', $courier->id)->whereIn('status', ['delivered', 'completed'])->count(),
            'earnings' => Order::where('courier_id', $courier->id)->where('status', 'completed')->sum('commission'),
        ];

        return view('courier.dashboard', compact('orders', 'status', 'stats'));
    }

    public function updateStatus(Request $request, Order $order, OrderLifecycleService $lifecycle)
    {
        $data = $request->validate([
            'status' => 'required|in:out_for_delivery,delivered,delivery_failed',
            'failure_reason' => 'required_if:status,delivery_failed|nullable|string|min:5|max:1000',
        ]);

        DB::transaction(function () use ($order, $data, $lifecycle): void {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->courier_id !== $this->courier()->id, 403);
            $trackingStatus = match ($data['status']) {
                'out_for_delivery' => 'Out for delivery',
                'delivered' => 'Delivered',
                'delivery_failed' => 'Delivery failed',
            };
            $lifecycle->transition(
                $locked,
                $data['status'],
                $this->courier()->id,
                'courier',
                $data['failure_reason'] ?? null,
                ['tracking_status' => $trackingStatus],
            );
            $order->setRawAttributes($locked->getAttributes(), true);
        });

        $message = match ($data['status']) {
            'delivered' => 'Your order #'.$order->order_number.' has been delivered.',
            'delivery_failed' => 'Delivery of your order #'.$order->order_number.' could not be completed. Reason: '.$data['failure_reason'],
            default => 'Your order #'.$order->order_number.' is now out for delivery.',
        };

        $order->buyer?->notifications()->create([
            'id' => \Illuminate\Support\Str::uuid(),
            'type' => 'App\\Notifications\\BuyerOrderUpdate',
            'data' => json_encode([
                'type' => 'order',
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'message' => $message,
            ]),
        ]);

        return back()->with('success', match ($data['status']) {
            'delivered' => 'Parcel marked as delivered.',
            'delivery_failed' => 'Delivery failure recorded for review.',
            default => 'Parcel marked as out for delivery.',
        });
    }

    public function reports(Request $request)
    {
        $from = $request->get('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->get('to', now()->format('Y-m-d'));
        $orders = Order::where('courier_id', $this->courier()->id)
            ->whereBetween('created_at', [$from, $to . ' 23:59:59'])
            ->latest()
            ->get();

        return view('courier.reports', [
            'from' => $from,
            'to' => $to,
            'orders' => $orders,
            'totalOrders' => $orders->count(),
            'deliveredOrders' => $orders->whereIn('status', ['delivered', 'completed'])->count(),
            'earnings' => $orders->where('status', 'completed')->sum('commission'),
        ]);
    }

    public function chat(Request $request)
    {
        $courier = $this->courier();
        $contactIds = Message::where(function ($query) use ($courier) {
            $query->where('sender_id', $courier->id)->orWhere('receiver_id', $courier->id);
        })->get()->map(fn ($message) => $message->sender_id === $courier->id ? $message->receiver_id : $message->sender_id)->unique();

        $contacts = \App\Models\User::whereIn('id', $contactIds)->get();
        $activeUserId = $request->get('user');
        $activeUser = $activeUserId ? \App\Models\User::find($activeUserId) : null;
        $messages = collect();

        if ($activeUser) {
            $messages = Message::where(function ($query) use ($courier, $activeUserId) {
                $query->where('sender_id', $courier->id)->where('receiver_id', $activeUserId);
            })->orWhere(function ($query) use ($courier, $activeUserId) {
                $query->where('sender_id', $activeUserId)->where('receiver_id', $courier->id);
            })->orderBy('created_at')->get();
        }

        return view('courier.chat', compact('contacts', 'activeUser', 'messages'));
    }

    public function sendMessage(Request $request)
    {
        $data = $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'message' => 'required|string|max:2000',
        ]);

        $message = Message::create([
            'sender_id' => $this->courier()->id,
            'receiver_id' => $data['receiver_id'],
            'body' => $data['message'],
            'read' => false,
        ]);

        try {
            Mail::to($message->receiver->email)->send(new NewMessageMail($message));
        } catch (\Throwable $exception) {
            report($exception);
        }

        return back()->with('success', 'Message sent.');
    }

    public function account()
    {
        return view('courier.account', ['courier' => $this->courier()]);
    }

    public function updateAccount(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'contact_no' => ['required', 'regex:/^09\d{9}$/'],
            'delivery_area' => 'required|string|max:255',
        ]);

        $this->courier()->update($data);
        return back()->with('success', 'Account details updated.');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        if (!Hash::check($data['current_password'], $this->courier()->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $this->courier()->update(['password' => Hash::make($data['password'])]);
        return back()->with('success', 'Password updated.');
    }
}
