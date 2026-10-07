<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendAdminMessageRequest;
use App\Models\Message;
use App\Models\User;
use App\Services\MessagingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminChatController extends Controller
{
    public function index(Request $request): View
    {
        $admin = $request->user();
        $contacts = User::query()
            ->whereIn('role', ['buyer', 'seller', 'courier', 'logistics'])
            ->where('status', 'approved')
            ->withCount([
                'sentMessages as unread' => fn ($query) => $query
                    ->where('receiver_id', $admin->id)
                    ->where('read', false),
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $activeUser = null;
        $messages = collect();
        if ($request->query->has('user')) {
            $requestedUserId = $request->query('user');
            abort_unless(is_string($requestedUserId) && ctype_digit($requestedUserId), 404);

            $activeUser = User::query()->findOrFail((int) $requestedUserId);
            Gate::authorize('viewConversation', [Message::class, $activeUser]);

            Message::query()
                ->where('sender_id', $activeUser->id)
                ->where('receiver_id', $admin->id)
                ->where('read', false)
                ->update(['read' => true]);

            $messages = Message::query()
                ->where(function ($query) use ($admin, $activeUser): void {
                    $query->where('sender_id', $admin->id)
                        ->where('receiver_id', $activeUser->id);
                })
                ->orWhere(function ($query) use ($admin, $activeUser): void {
                    $query->where('sender_id', $activeUser->id)
                        ->where('receiver_id', $admin->id);
                })
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();
        }

        return view('admin.chat', [
            'users' => $contacts,
            'activeUser' => $activeUser,
            'messages' => $messages,
        ]);
    }

    public function send(SendAdminMessageRequest $request, MessagingService $messaging): RedirectResponse
    {
        $data = $request->validated();
        $recipient = User::query()->findOrFail($data['receiver_id']);
        $messaging->sendAdminMessage($request->user(), $recipient, $data['body']);

        return redirect()->route('admin.chat', ['user' => $recipient->id])
            ->with('success', 'Message sent.');
    }
}
