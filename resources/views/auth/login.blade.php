@extends('layouts.auth')

@section('title', 'Log In')

@section('content')
    <div class="text-center w-75 m-auto">
        <h4 class="text-dark-50 text-center pb-0">Sign In</h4>
        <p class="text-muted mb-4">Enter your username and password to access the admin panel.</p>
    </div>

    @if (session('status'))
        <div class="alert alert-info">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input class="form-control @error('username') is-invalid @enderror" type="text" id="username"
                name="username" value="{{ old('username') }}" placeholder="Enter your username" autofocus required>
            @error('username')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <div class="input-group input-group-merge">
                <input type="password" id="password" name="password"
                    class="form-control @error('password') is-invalid @enderror" placeholder="Enter your password"
                    required>
                <div class="input-group-text" data-password="false">
                    <span class="password-eye"></span>
                </div>
            </div>
            @error('password')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="remember" name="remember"
                    {{ old('remember') ? 'checked' : '' }}>
                <label class="form-check-label" for="remember">Remember me</label>
            </div>
        </div>

        <div class="mb-0 text-center">
            <button class="btn btn-primary" type="submit"> Log In </button>
        </div>
    </form>
@endsection
