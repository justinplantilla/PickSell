{{-- The rider's photo at the buyer's door (OrderDelivery::proof). Expects $order. Shown once the parcel has been delivered. --}}
@php
    $proof = in_array($order->status, ['placed', 'confirmed', 'preparing', 'cancelled'], true) ? null : \App\Support\OrderDelivery::proof($order);
@endphp
@if($proof)
    <div class="delivery-proof" style="display:flex;align-items:center;gap:12px;margin:12px 0;padding:10px 12px;border:1px solid #eee;border-radius:10px;background:#fafafa">
        <a href="{{ route('buyer.orders.proof-of-delivery', $order) }}" target="_blank" rel="noopener" aria-label="Open the delivery photo">
            <img src="{{ route('buyer.orders.proof-of-delivery', $order) }}" alt="Proof of delivery photo" loading="lazy"
                 style="width:72px;height:72px;object-fit:cover;border-radius:8px;display:block;background:#eee">
        </a>
        <div style="min-width:0">
            <strong style="display:block;font-size:.9rem">Proof of delivery</strong>
            <span style="display:block;font-size:.8rem;color:#777">
                Photo taken by {{ $proof->rider?->full_name ?? 'the rider' }}@if($proof->completed_at) on {{ $proof->completed_at->format('M d, Y h:i A') }}@endif.
            </span>
            <a href="{{ route('buyer.orders.proof-of-delivery', $order) }}" target="_blank" rel="noopener" style="font-size:.8rem;color:#E8472A;text-decoration:none">View full photo</a>
        </div>
    </div>
@endif
