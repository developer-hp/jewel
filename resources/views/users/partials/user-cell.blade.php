<div class="d-flex align-items-center">
    <img src="{{ $user->avatar ? asset('storage/' . $user->avatar) : asset('theme/assets/images/users/avatar-1.jpg') }}"
        class="rounded-circle me-2" height="36" width="36" alt="avatar">
    <span class="fw-semibold">{{ $user->name }}</span>
</div>
