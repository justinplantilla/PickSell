<?php

namespace App\Policies;

use App\Auth\Permission;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MessagePolicy
{
    public function viewConversation(User $user, User $contact): Response
    {
        if (! $user->hasPermission(Permission::MESSAGING_VIEW)) {
            return Response::deny('You do not have permission to view conversations.');
        }

        return $this->isApprovedContact($user, $contact)
            ? Response::allow()
            : Response::deny('You are not authorized to access this conversation.');
    }

    public function sendMessage(User $user, User $recipient): Response
    {
        if (! $user->hasPermission(Permission::MESSAGING_MANAGE)) {
            return Response::deny('You do not have permission to send messages.');
        }

        return $this->isApprovedContact($user, $recipient)
            ? Response::allow()
            : Response::deny('You cannot message this recipient.');
    }

    private function isApprovedContact(User $user, User $contact): bool
    {
        return $contact->id !== $user->id
            && $contact->status === 'approved'
            && in_array($contact->role, ['buyer', 'seller', 'courier', 'logistics'], true);
    }
}
