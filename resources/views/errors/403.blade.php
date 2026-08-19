@extends('layouts.auth')

@section('title', 'Access Denied')

@section('content')
    <div class="text-center">
        <i class="ri-shield-cross-fill text-danger" style="font-size: 64px;"></i>
        <h1 class="text-error mt-3">403</h1>
        <h4 class="text-uppercase text-danger mt-3">Access Denied</h4>
        <p class="text-muted mt-3">
            {{ $exception?->getMessage() ?: 'You do not have permission to access this page.' }}
        </p>
        <a class="btn btn-primary mt-3" href="{{ url()->previous() }}">
            <i class="ri-arrow-left-line"></i> Go Back
        </a>
    </div>
@endsection
