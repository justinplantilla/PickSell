{{--
    PickSell pagination (admin, seller, buyer). Set as the default view in AppServiceProvider; Pickrush pages pass
    Laravel's tailwind view explicitly because the console theme styles that markup. Links keep every query string.
--}}
@if ($paginator->hasPages())
    @once
        @vite('resources/css/views/partials-pagination-styles.css')
    @endonce
    <nav class="ps-pager" role="navigation" aria-label="Pagination">
        <p class="ps-pager-info">
            @if ($paginator->firstItem())
                <span class="ps-pager-info-word">Showing</span> <b>{{ number_format($paginator->firstItem()) }}</b>–<b>{{ number_format($paginator->lastItem()) }}</b> of <b>{{ number_format($paginator->total()) }}</b>
            @else
                <span class="ps-pager-info-word">Showing</span> <b>{{ $paginator->count() }}</b> of <b>{{ number_format($paginator->total()) }}</b>
            @endif
        </p>

        <div class="ps-pager-controls">
            @if ($paginator->onFirstPage())
                <span class="ps-pager-step is-disabled" aria-disabled="true">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                    <span class="ps-pager-step-label">Prev</span>
                </span>
            @else
                <a class="ps-pager-step" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                    <span class="ps-pager-step-label">Prev</span>
                </a>
            @endif

            {{-- A short window: first, last, and one page either side of the current one ("1 … 5 6 7 … 15"). --}}
            @php
                $current = $paginator->currentPage();
                $last = $paginator->lastPage();
                $shown = collect([1, $last, $current - 1, $current, $current + 1])
                    ->when($current <= 3, fn ($pages) => $pages->merge([2, 3]))
                    ->when($current >= $last - 2, fn ($pages) => $pages->merge([$last - 1, $last - 2]))
                    ->filter(fn ($page) => $page >= 1 && $page <= $last)->unique()->sort()->values();
            @endphp
            <ol class="ps-pager-pages">
                @foreach ($shown as $i => $page)
                    @if ($i > 0 && $page - $shown[$i - 1] > 1)
                        <li class="ps-pager-gap" aria-hidden="true">…</li>
                    @endif
                    <li>
                        @if ($page == $current)
                            <span class="ps-pager-page is-current" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="ps-pager-page" href="{{ $paginator->url($page) }}" aria-label="Page {{ $page }}">{{ $page }}</a>
                        @endif
                    </li>
                @endforeach
            </ol>
            <span class="ps-pager-compact">Page <b>{{ $paginator->currentPage() }}</b> of <b>{{ $paginator->lastPage() }}</b></span>

            @if ($paginator->hasMorePages())
                <a class="ps-pager-step" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page">
                    <span class="ps-pager-step-label">Next</span>
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
                </a>
            @else
                <span class="ps-pager-step is-disabled" aria-disabled="true">
                    <span class="ps-pager-step-label">Next</span>
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
                </span>
            @endif
        </div>
    </nav>
@endif
