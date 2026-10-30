@extends('buyer.layout')
@section('styles')
@vite('resources/css/views/buyer-recommended.css')
@endsection
@section('title', 'My Wishlist')

@section('content')
<div class="rec-page">
    <div class="rec-header">
        <h1>My Wishlist</h1>
        <p>Products you saved for later, on the website and in the PickSell app.</p>
    </div>

    @if($products->isEmpty())
        <p class="wishlist-empty">Your wishlist is empty. Tap <strong>Add to wishlist</strong> on any product to save it here.</p>
        <a href="{{ route('buyer.home') }}" class="btn btn-coral">Browse products</a>
    @else
        <div class="product-grid">
            @foreach($products as $product)
            <div class="wishlist-entry">
                @include('buyer.partials.product-cards', ['products' => [$product]])
                <form method="POST" action="{{ route('buyer.wishlist.destroy', $product) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="wishlist-remove-btn">Remove from wishlist</button>
                </form>
            </div>
            @endforeach
        </div>
        {{ $products->links() }}
    @endif
</div>
@endsection
