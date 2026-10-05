@extends('admin.layout')
@section('styles')
@vite(['resources/css/views/admin-oversight.css', 'resources/css/views/admin-compliance.css'])
@endsection
@section('title', ($seller->business_name ?? $seller->full_name) . ' — Compliance')

@php
    $canWarn = auth()->user()->can('warnSeller', $seller);
    $canSuspend = auth()->user()->can('suspendSeller', $seller);
    $canReinstate = auth()->user()->can('reinstateSeller', $seller);
    $canOpen = auth()->user()->can('openComplianceCase', $seller);
@endphp

@section('content')
<p class="compliance-back"><a href="{{ route('admin.compliance') }}" class="btn btn-outline btn-sm">← Back to seller compliance</a></p>

<div class="compliance-layout">
    <section class="card compliance-summary" aria-labelledby="seller-heading">
        <div class="card-header">
            <span class="card-title" id="seller-heading">{{ $seller->business_name ?? $seller->full_name }}</span>
            <span>
                <span class="badge badge-{{ $seller->status }}">{{ $seller->status === 'approved' ? 'Active' : ucfirst($seller->status) }}</span>
                @include('admin.compliance._risk', ['risk' => $risk])
            </span>
        </div>
        <div class="compliance-pad">
            <p class="oversight-description">{{ $seller->full_name }} · {{ $seller->email }} · seller since {{ $seller->created_at->format('M Y') }}
                @can('viewAccount', $seller) · <a href="{{ route('admin.users.show', $seller) }}">Open account</a>@endcan</p>
            @if($risk['factors'])
                <ul class="risk-factors">
                    @foreach($risk['factors'] as $factor => $value)<li><strong class="oversight-number">{{ $value }}</strong> {{ $factor }}</li>@endforeach
                </ul>
            @endif
        </div>
    </section>

    {{-- 1. Registered category --}}
    <section class="card" aria-labelledby="category-heading">
        <div class="card-header">
            <span class="card-title" id="category-heading">Registered category</span>
            <span class="oversight-description">Registered line: <strong>{{ $seller->line_of_business ?? 'Not set' }}</strong></span>
        </div>
        @if($products->isEmpty())
            <p class="oversight-description compliance-pad">This seller has no listings.</p>
        @else
            <div class="oversight-table-wrap">
                <table>
                    <thead><tr><th>Product</th><th>Listed category</th><th>Matches registration</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @foreach($products as $product)
                        <tr>
                            <td>@can('view', $product)<a href="{{ route('admin.products.show', $product) }}">{{ $product->name }}</a>@else{{ $product->name }}@endcan</td>
                            <td>{{ $product->category ?? '—' }}</td>
                            <td>
                                @if($product->category_matches)
                                    <span class="match-yes">✓ Matches</span>
                                @else
                                    <span class="match-no"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg> Outside registered category</span>
                                @endif
                            </td>
                            <td><x-stock-status-badge :product="$product" /></td>
                            <td>
                                @if(! $product->category_matches && $canOpen)
                                    <a href="{{ route('admin.compliance.seller', ['user' => $seller, 'product' => $product->id]) }}#open-case" class="btn btn-outline btn-sm">Open case</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- 2. Product violations --}}
    <section class="card" aria-labelledby="violations-heading">
        <div class="card-header"><span class="card-title" id="violations-heading">Product violations</span></div>
        @if($mismatched->isEmpty() && $heldProducts->isEmpty())
            <p class="oversight-description compliance-pad">No active off-category listings and no products archived by Admin.</p>
        @else
            <ul class="compliance-list">
                @foreach($mismatched as $product)
                    <li><span><strong>{{ $product->name }}</strong> is listed under <em>{{ $product->category }}</em>, outside the registered <em>{{ $seller->line_of_business }}</em>.</span><span class="severity-badge severity-medium">Off-category</span></li>
                @endforeach
                @foreach($heldProducts as $product)
                    <li><span><strong>{{ $product->name }}</strong> archived by Admin on {{ $product->latestStatusModeration->created_at->format('M d, Y') }}@if($product->latestStatusModeration->reason) — {{ $product->latestStatusModeration->reason }}@endif</span><span class="severity-badge severity-high">Archived</span></li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- 3. Warnings --}}
    <section class="card" aria-labelledby="warnings-heading">
        <div class="card-header"><span class="card-title" id="warnings-heading">Warnings</span><span class="oversight-description">{{ $warnings->count() }} on record</span></div>
        @if($warnings->isNotEmpty())
            <ol class="compliance-timeline">
                @foreach($warnings as $warning)
                    <li>
                        <span class="oversight-meta">{{ $warning->created_at->format('M d, Y h:i A') }} · {{ $warning->actor?->full_name ?? 'Admin' }}@if($warning->complianceCase) · <a href="{{ route('admin.compliance.cases.show', $warning->complianceCase) }}">case #{{ $warning->compliance_case_id }}</a>@endif</span>
                        <p class="compliance-reason">{{ $warning->reason }}</p>
                    </li>
                @endforeach
            </ol>
        @else
            <p class="oversight-description compliance-pad">No warnings issued.</p>
        @endif
        @if($canWarn)
            <form method="POST" action="{{ route('admin.compliance.warn', $seller) }}" class="compliance-form">
                @csrf @method('PATCH')
                <label class="form-label" for="warning">Issue a warning <span class="oversight-meta">— emailed to the seller and kept on record</span></label>
                <textarea id="warning" name="warning" class="form-control" rows="2" required minlength="10" maxlength="1000">{{ old('warning') }}</textarea>
                @error('warning')<span class="compliance-error">{{ $message }}</span>@enderror
                @include('admin.compliance._case_select', ['field' => 'case_id', 'id' => 'warning-case'])
                <button type="submit" class="btn btn-outline btn-sm">Issue warning</button>
            </form>
        @endif
    </section>

    {{-- 4. Suspensions --}}
    <section class="card" aria-labelledby="suspensions-heading">
        <div class="card-header"><span class="card-title" id="suspensions-heading">Suspensions</span></div>
        @if($statusHistory->isNotEmpty())
            <ol class="compliance-timeline">
                @foreach($statusHistory as $change)
                    <li>
                        <span><strong>{{ $change->from_status === 'approved' ? 'Active' : ucfirst((string) $change->from_status) }} → {{ $change->to_status === 'approved' ? 'Active' : ucfirst($change->to_status) }}</strong>
                            <span class="oversight-meta">{{ $change->created_at->format('M d, Y h:i A') }} · {{ $change->changer?->full_name ?? 'Unknown' }}</span></span>
                        @if($change->reason)<p class="compliance-reason">{{ $change->reason }}</p>@endif
                    </li>
                @endforeach
            </ol>
        @else
            <p class="oversight-description compliance-pad">No suspensions on record.</p>
        @endif
        @if($canSuspend || $canReinstate)
            <form method="POST" action="{{ route($canSuspend ? 'admin.compliance.suspend' : 'admin.compliance.reinstate', $seller) }}" class="compliance-form {{ $canSuspend ? 'is-blocking' : '' }}">
                @csrf @method('PATCH')
                <h3>{{ $canSuspend ? 'Suspend seller' : 'Reinstate seller' }}</h3>
                @if($canSuspend)
                    <p class="compliance-warning">The seller loses access to PickSell and their listings can no longer be ordered until reinstated. They will be notified by email.</p>
                @endif
                <label class="form-label" for="status-reason">Reason</label>
                <textarea id="status-reason" name="reason" class="form-control" rows="2" required minlength="10" maxlength="1000">{{ old('reason') }}</textarea>
                @error('reason')<span class="compliance-error">{{ $message }}</span>@enderror
                @include('admin.compliance._case_select', ['field' => 'case_id', 'id' => 'status-case'])
                @if($canSuspend)
                    <label class="compliance-confirm"><input type="checkbox" name="confirm" value="1" required> I confirm I want to suspend this seller.</label>
                    @error('confirm')<span class="compliance-error">{{ $message }}</span>@enderror
                @endif
                <button type="submit" class="btn btn-sm {{ $canSuspend ? 'btn-danger' : 'btn-success' }}">{{ $canSuspend ? 'Suspend seller' : 'Reinstate seller' }}</button>
            </form>
        @endif
    </section>

    {{-- 5. Compliance cases --}}
    <section class="card" aria-labelledby="cases-heading">
        <div class="card-header"><span class="card-title" id="cases-heading">Compliance cases</span></div>
        @if($cases->isNotEmpty())
            <ul class="compliance-list">
                @foreach($cases as $case)
                    <li>
                        <span><a href="{{ route('admin.compliance.cases.show', $case) }}"><strong>#{{ $case->id }} {{ $case->type_label }}</strong></a>
                            <span class="oversight-meta">opened {{ $case->created_at->format('M d, Y') }} by {{ $case->opener?->full_name ?? 'Admin' }}{{ $case->resolved_at ? ' · closed ' . $case->resolved_at->format('M d, Y') : '' }}</span></span>
                        <span><span class="severity-badge severity-{{ $case->severity }}">{{ ucfirst($case->severity) }}</span> <span class="badge {{ $case->isOpen() ? 'badge-pending' : 'badge-approved' }}">{{ ucfirst($case->status) }}</span></span>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="oversight-description compliance-pad">No compliance cases.</p>
        @endif

        @if($canOpen)
            <details class="compliance-open" id="open-case" @if($prefillProduct || $errors->hasAny(['type', 'severity', 'description', 'product_id', 'evidence', 'evidence.*'])) open @endif>
                <summary>Open a compliance case</summary>
                <form method="POST" action="{{ route('admin.compliance.cases.store', $seller) }}" enctype="multipart/form-data" class="compliance-form">
                    @csrf
                    <div class="compliance-grid">
                        <div>
                            <label class="form-label" for="case-type">Type</label>
                            <select id="case-type" name="type" class="form-control" required>
                                @foreach(\App\Models\ComplianceCase::TYPES as $value => $label)
                                    <option value="{{ $value }}" @selected(old('type', $prefillProduct ? 'category_mismatch' : null) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="case-severity">Severity</label>
                            <select id="case-severity" name="severity" class="form-control" required>
                                @foreach(array_keys(\App\Models\ComplianceCase::SEVERITIES) as $value)
                                    <option value="{{ $value }}" @selected(old('severity', 'medium') === $value)>{{ ucfirst($value) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="case-product">Product (optional)</label>
                            <select id="case-product" name="product_id" class="form-control">
                                <option value="">Not about one product</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}" @selected((int) old('product_id', $prefillProduct) === $product->id)>{{ $product->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <label class="form-label" for="case-description">Description &amp; evidence summary</label>
                    <textarea id="case-description" name="description" class="form-control" rows="4" required minlength="20" maxlength="5000">{{ old('description') }}</textarea>
                    @foreach(['type', 'severity', 'description', 'product_id', 'evidence'] as $field)@error($field)<span class="compliance-error">{{ $message }}</span>@enderror @endforeach
                    @if($errors->has('evidence.*'))<span class="compliance-error">{{ $errors->first('evidence.*') }}</span>@endif
                    <label class="form-label" for="case-evidence">Evidence files <span class="oversight-meta">— up to 5 images or PDFs, 5MB each; stored privately</span></label>
                    <input id="case-evidence" type="file" name="evidence[]" class="form-control" accept="image/jpeg,image/png,image/webp,application/pdf" multiple>
                    <button type="submit" class="btn btn-coral btn-sm">Open case</button>
                </form>
            </details>
        @endif
    </section>

    {{-- Full compliance history --}}
    <section class="card" aria-labelledby="history-heading">
        <div class="card-header"><span class="card-title" id="history-heading">Compliance history</span></div>
        @if($history->isEmpty())
            <p class="oversight-description compliance-pad">No compliance actions on record.</p>
        @else
            @include('admin.compliance._timeline', ['actions' => $history, 'showCase' => true])
        @endif
    </section>
</div>
@endsection
