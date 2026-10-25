{{--
    One dialog per account status change, shared by every row's ⋯ menu and the preview drawer. The button that opens it
    (data-row-dialog) supplies the form action and the account name; after a validation error the same dialog reopens.
--}}
@php
    $actionLabels = \App\Services\Admin\UserModerationService::ACTION_LABELS;
    $blocking = \App\Services\Admin\UserModerationService::REQUIRES_CONFIRMATION;
    $failedUser = old('user_id') && old('status') ? \App\Models\User::find(old('user_id')) : null;
@endphp
@can(\App\Auth\Permission::USERS_MANAGE)
    @foreach($actionLabels as $to => $label)
        @php
            $confirm = in_array($to, $blocking, true);
            $reopen = $failedUser && old('status') === $to;
        @endphp
        <dialog class="list-dialog" id="user-status-{{ $to }}" aria-labelledby="user-status-{{ $to }}-title"{{ $reopen ? ' data-open-on-load' : '' }}>
            <form method="POST" action="{{ $reopen ? route('admin.users.status', $failedUser) : '' }}" class="bulk-action-form" data-no-confirm>
                @csrf
                @method('PATCH')
                <input type="hidden" name="user_id" value="{{ $reopen ? $failedUser->id : '' }}" data-row-id>
                <input type="hidden" name="status" value="{{ $to }}">
                <h2 id="user-status-{{ $to }}-title">{{ $label }} <span data-row-name>{{ $reopen ? $failedUser->full_name : 'this account' }}</span></h2>
                @if($confirm)
                    <p class="list-dialog-warning"><span data-row-name>{{ $reopen ? $failedUser->full_name : 'This account' }}</span> will lose sign-in access until reactivated. The reason is kept in the account history.</p>
                @else
                    <p class="oversight-description">The account can sign in again right away.</p>
                @endif
                <label class="form-label" for="user-status-{{ $to }}-reason">Reason{{ $confirm ? ' (required)' : ' (optional)' }}</label>
                <textarea id="user-status-{{ $to }}-reason" name="reason" class="form-control" rows="3" maxlength="1000" @if($confirm) required minlength="10" @endif>{{ $reopen ? old('reason') : '' }}</textarea>
                @if($reopen)
                    @error('reason')<p class="list-dialog-error" data-row-error>{{ $message }}</p>@enderror
                    @error('confirm')<p class="list-dialog-error" data-row-error>{{ $message }}</p>@enderror
                @endif
                @if($confirm)
                    <label class="account-confirm"><input type="checkbox" name="confirm" value="1" required> Confirm {{ strtolower($label) }}.</label>
                @endif
                <div class="list-dialog-actions">
                    <button type="button" class="btn btn-outline btn-sm" data-dialog-cancel>Cancel</button>
                    <button type="submit" class="btn btn-sm {{ $confirm ? 'btn-danger' : 'btn-success' }}">{{ $label }} account</button>
                </div>
            </form>
        </dialog>
    @endforeach
@endcan
