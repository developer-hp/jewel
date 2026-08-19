@props(['active' => true])

<span class="badge {{ $active ? 'bg-success' : 'bg-danger' }}">{{ $active ? 'Active' : 'Inactive' }}</span>
