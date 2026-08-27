@extends('layouts.app')

@section('title', 'My Profile')

@section('content')
    <x-page-title title="My Profile" />

    <div class="row">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body text-center">
                    <img src="{{ $user->avatar ? asset('storage/' . $user->avatar) : asset('theme/assets/images/users/avatar-1.jpg') }}"
                        class="rounded-circle avatar-lg img-thumbnail mb-2" alt="avatar">
                    <h4 class="mb-0">{{ $user->name }}</h4>
                    <p class="text-muted mb-2">{{ '@' . $user->username }}</p>

                    <div>
                        @forelse ($user->roles as $role)
                            <span class="badge bg-info-subtle text-info">{{ $role->name }}</span>
                        @empty
                            <span class="badge bg-secondary">No role</span>
                        @endforelse
                    </div>

                    <hr>

                    <div class="text-start">
                        <p class="mb-1"><strong>Email:</strong> {{ $user->email ?: '—' }}</p>
                        <p class="mb-1"><strong>Phone:</strong> {{ $user->phone ?: '—' }}</p>
                        <p class="mb-0"><strong>Member since:</strong> {{ $user->created_at->format('d-m-Y') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="header-title mb-3">Account Details</h5>

                    <form method="POST" action="{{ route('profile.update') }}">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" id="name" name="name" class="form-control"
                                    value="{{ old('name', $user->name) }}" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" value="{{ $user->username }}" disabled>
                                <small class="text-muted">Only an administrator can change your username.</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" id="email" name="email" class="form-control"
                                    value="{{ old('email', $user->email) }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" id="phone" name="phone" class="form-control"
                                    value="{{ old('phone', $user->phone) }}">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="ri-save-line"></i> Save Changes
                        </button>
                    </form>
                </div>
            </div>

            <div class="card" id="change-password">
                <div class="card-body">
                    <h5 class="header-title mb-3">Change Password</h5>

                    <form method="POST" action="{{ route('profile.password') }}">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="current_password" class="form-label">
                                    Current Password <span class="text-danger">*</span>
                                </label>
                                <input type="password" id="current_password" name="current_password"
                                    class="form-control" autocomplete="current-password" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="new_password" class="form-label">
                                    New Password <span class="text-danger">*</span>
                                </label>
                                <input type="password" id="new_password" name="password" class="form-control"
                                    autocomplete="new-password" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="password_confirmation" class="form-label">
                                    Confirm New Password <span class="text-danger">*</span>
                                </label>
                                <input type="password" id="password_confirmation" name="password_confirmation"
                                    class="form-control" autocomplete="new-password" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="ri-lock-password-line"></i> Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
