@extends('layouts.app')
@section('title', 'Terms of Service')

@section('styles')
@vite('resources/css/views/pages-legal.css')
@endsection

@section('content')
{{-- The same text the buyer app and the rider app show (resources/views/legal/terms.blade.php). --}}
<article class="legal-page">
    <h1>Terms of Service</h1>
    <p class="legal-updated">Version {{ config('legal.version') }} · Effective {{ \Illuminate\Support\Carbon::parse(config('legal.effective_date'))->format('F j, Y') }}</p>
    @include('legal.terms')
</article>
@endsection
