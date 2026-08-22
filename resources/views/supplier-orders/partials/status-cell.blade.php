@php($status = $order->statusLabel())

<span class="badge {{ ['Received' => 'bg-success', 'Overdue' => 'bg-warning text-dark'][$status] ?? 'bg-danger' }}">
    {{ $status }}
</span>
