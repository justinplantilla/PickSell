@extends('buyer.layout')
@section('styles')
@vite('resources/css/views/buyer-recommended.css')
@endsection
@section('title', 'Recommended For You')

@section('content')
<div class="rec-page">
    <div class="rec-header">
        <h1>Recommended For You</h1>
        <p>Fresh picks based on what's popular right now.</p>
    </div>

    <div class="product-grid" id="recGrid">
        @include('buyer.partials.product-cards', ['products' => $products->items()])
    </div>

    <div id="recSentinel" class="rec-sentinel" aria-hidden="true"></div>

    <div id="recSpinner" class="rec-spinner" style="display:none">
        <span></span><span></span><span></span>
    </div>

    <div id="recEnd" class="rec-end" style="display:none">You've seen everything! 🎉</div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    let page = {{ $products->currentPage() + 1 }};
    let hasMore = {{ $products->hasMorePages() ? 'true' : 'false' }};
    let loading = false;
    const grid = document.getElementById('recGrid');
    const spinner = document.getElementById('recSpinner');
    const endMsg = document.getElementById('recEnd');
    const sentinel = document.getElementById('recSentinel');

    function loadMore() {
        if (loading || !hasMore) return;
        loading = true;
        spinner.style.display = 'flex';
        fetch(`/buyer/recommended?page=${page}`, { headers: { Accept: 'application/json' } })
            .then(r => r.json())
            .then(data => {
                grid.insertAdjacentHTML('beforeend', data.html);
                hasMore = data.hasMore;
                page = data.nextPage;
                if (!hasMore) {
                    spinner.style.display = 'none';
                    endMsg.style.display = 'block';
                    observer.disconnect();
                }
            })
            .catch(() => {})
            .finally(() => {
                loading = false;
                spinner.style.display = hasMore ? 'none' : 'none';
            });
    }

    const observer = new IntersectionObserver(entries => {
        if (entries[0].isIntersecting) loadMore();
    }, { rootMargin: '200px' });

    if (hasMore) observer.observe(sentinel);
    else endMsg.style.display = 'block';
})();
</script>
@endsection
