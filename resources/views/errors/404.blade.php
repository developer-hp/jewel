@extends('layouts.auth')

@section('title', 'Page Not Found')

@section('content')
    <div class="text-center">
        <i class="ri-search-eye-line text-primary" style="font-size: 64px;"></i>
        <h1 class="text-error mt-3">404</h1>
        <h4 class="text-uppercase text-danger mt-3">Page Not Found</h4>
        <p class="text-muted mt-3">The page you are looking for does not exist.</p>
        <a class="btn btn-primary mt-3" href="{{ url('/') }}">
            <i class="ri-home-4-line"></i> Return Home
        </a>
    </div>
@endsection
