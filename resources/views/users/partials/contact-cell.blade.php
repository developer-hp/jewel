<div>{{ $user->email ?: '—' }}</div>
@if ($user->phone)
    <small class="text-muted">{{ $user->phone }}</small>
@endif
