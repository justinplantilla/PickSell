@if($openCases->isNotEmpty())
    <label class="form-label" for="{{ $id }}">Link to case (optional)</label>
    <select id="{{ $id }}" name="{{ $field }}" class="form-control">
        <option value="">No case</option>
        @foreach($openCases as $openCase)
            <option value="{{ $openCase->id }}" @selected((int) old($field) === $openCase->id)>#{{ $openCase->id }} {{ $openCase->type_label }} ({{ $openCase->severity }})</option>
        @endforeach
    </select>
    @error($field)<span class="compliance-error">{{ $message }}</span>@enderror
@endif
