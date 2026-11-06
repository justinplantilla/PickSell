@props(['seller'])
@php $following = auth()->user()?->followedSellers()->whereKey($seller->id)->exists(); @endphp
<form method="POST" action="{{ route('buyer.seller.follow', $seller) }}" data-no-confirm class="follow-seller-form">
    @csrf
    <button type="submit" class="btn btn-sm {{ $following ? 'btn-outline' : 'btn-coral' }}" aria-pressed="{{ $following ? 'true' : 'false' }}">{{ $following ? 'Following' : '+ Follow' }}</button>
</form>
