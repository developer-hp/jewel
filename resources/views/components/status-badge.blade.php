@props(['active' => true, 'labels' => ['Active', 'Inactive']])

<span class="badge {{ $active ? 'bg-success' : 'bg-danger' }}">{{ $active ? $labels[0] : $labels[1] }}</span>
