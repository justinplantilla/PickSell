@extends('pickrush.layouts.app')
@section('title', 'Rates & policies')

@section('content')
<div class="pr-page-head">
    <div>
        <h1>Rates &amp; policies</h1>
        <p>Rules every hub follows. Changes apply to new actions straight away and are recorded in the audit log.{{ $canEdit ? '' : ' Only a super admin can change them.' }}</p>
    </div>
</div>

<form method="POST" action="{{ route('pickrush.hq.settings.update') }}">
    @csrf @method('PUT')
    @foreach($groups as $group => $fields)
        <section class="pr-card pr-section">
            <h2>{{ $group }}</h2>
            <div class="pr-form-grid">
                @foreach($fields as $f)
                    @php $name = str_replace('.', '__', $f->key); @endphp
                    <div class="pr-field">
                        <label for="{{ $name }}">{{ $f->label }}</label>
                        @if($f->select)
                            <select id="{{ $name }}" name="{{ $name }}" class="pr-select" @disabled(! $canEdit)>
                                @foreach($f->select as $option)<option value="{{ $option }}" @selected(old($name, $f->value) === $option)>{{ ucfirst(str_replace('_', ' ', $option)) }}</option>@endforeach
                            </select>
                        @else
                            <input id="{{ $name }}" name="{{ $name }}" type="number" step="any" class="pr-input" value="{{ old($name, $f->value) }}" @disabled(! $canEdit) required>
                        @endif
                        @if($f->help)<small class="pr-muted">{{ $f->help }}</small>@endif
                        @error($name)<span class="pr-field-error">{{ $message }}</span>@enderror
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
    @if($canEdit)<div class="pr-sticky-bar"><button type="submit" class="pr-btn pr-btn-primary">Save changes</button></div>@endif
</form>
@endsection
