@extends('layouts.app')
@section('title', 'Privacy Policy')

@section('styles')
@vite('resources/css/views/pages-legal.css')
@endsection

@section('content')
{{-- The same text the buyer app and the rider app show (resources/views/legal/privacy.blade.php). --}}
<article class="legal-page">
    <h1>Privacy Policy</h1>
    <p class="legal-updated">Version {{ config('legal.version') }} · Effective {{ \Illuminate\Support\Carbon::parse(config('legal.effective_date'))->format('F j, Y') }}</p>
    @include('legal.privacy')
</article>
@endsection
