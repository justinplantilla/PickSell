{{-- Report a problem with the logistics company carrying this order. Needs $order and $role ('buyer' | 'seller'). --}}
@php
    $reportable = in_array($order->status, \App\Http\Controllers\DeliveryProblemController::REPORTABLE, true);
    $types = $role === 'buyer' ? \App\Models\LogisticsIssue::BUYER_REPORTS : \App\Models\LogisticsIssue::SELLER_REPORTS;
    $myReports = \App\Models\LogisticsIssue::where('order_id', $order->id)->where('reported_by', auth()->id())->latest()->get();
    $statusLabels = ['open' => 'PickSell is reviewing', 'escalated' => 'Waiting on the logistics company', 'resolved' => 'Resolved'];
@endphp
@if($reportable || $myReports->isNotEmpty())
@vite('resources/css/components/delivery-problem.css')
<details class="delivery-problem" @if($errors->has('delivery_problem') || $errors->has('type') || $errors->has('description')) open @endif>
    <summary>Problem with the delivery?</summary>
    @foreach($myReports as $report)
        <p class="delivery-problem-status"><strong>{{ $report->label() }}</strong> · {{ $statusLabels[$report->status] ?? $report->status }} · {{ $report->created_at->format('M d, Y') }}
            @if($report->status === 'resolved' && $report->resolution_notes)<br><span>{{ $report->resolution_notes }}</span>@endif</p>
    @endforeach
    @if($reportable)
        <p class="delivery-problem-help">Tell PickSell what went wrong with {{ $order->carrierName() }}. We follow it up with the company and keep you posted here.</p>
        @error('delivery_problem')<div class="alert alert-error">{{ $message }}</div>@enderror
        <form method="POST" action="{{ route($role.'.orders.delivery-problem', $order) }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label class="form-label" for="delivery-problem-type">What happened? *</label>
                <select id="delivery-problem-type" name="type" class="form-control" required>
                    @foreach($types as $type)
                        <option value="{{ $type }}" @selected(old('type') === $type)>{{ \App\Models\LogisticsIssue::TYPES[$type] }}</option>
                    @endforeach
                </select>
                @error('type')<div class="account-danger-text">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="delivery-problem-description">Details *</label>
                <textarea id="delivery-problem-description" name="description" class="form-control" rows="3" minlength="10" maxlength="2000" required>{{ old('description') }}</textarea>
                @error('description')<div class="account-danger-text">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="delivery-problem-evidence">Photo (optional)</label>
                <input id="delivery-problem-evidence" type="file" name="evidence" accept="image/*" class="form-control">
                @error('evidence')<div class="account-danger-text">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-outline btn-sm">Send report</button>
        </form>
    @endif
</details>
@endif
@if($role === 'buyer')
@vite('resources/css/components/delivery-problem.css')
<details class="delivery-problem" @if($errors->has('off_platform') || $errors->has('details')) open @endif>
    <summary>Seller asked you to pay or ship outside PickSell?</summary>
    <p class="delivery-problem-help">Payments, chats and couriers outside PickSell aren't protected. Tell us and we'll review the seller; they won't see who reported.</p>
    @error('off_platform')<div class="alert alert-error">{{ $message }}</div>@enderror
    <form method="POST" action="{{ route('buyer.orders.report-off-platform', $order) }}">
        @csrf
        <div class="form-group">
            <label class="form-label" for="off-platform-details">What did the seller ask? *</label>
            <textarea id="off-platform-details" name="details" class="form-control" rows="3" minlength="10" maxlength="2000" required placeholder="e.g. Asked me to pay through GCash and ship with LBC instead.">{{ old('details') }}</textarea>
            @error('details')<div class="account-danger-text">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-outline btn-sm">Report seller</button>
    </form>
</details>
@endif
