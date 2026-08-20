@extends('layouts.app')

@section('title', 'Lot ' . $lot->code)

@section('content')
    <x-page-title :title="'Lot — ' . $lot->code">
        <x-slot:actions>
            @can('item.create')
                @if ($lot->remainingTags() > 0)
                    <a href="{{ route('lots.items.create', $lot) }}" class="btn btn-success">
                        <i class="ri-add-box-line"></i> Add Items ({{ $lot->remainingTags() }} left)
                    </a>
                @endif
            @endcan
            @can('item_lot.edit')
                <a href="{{ route('lots.edit', $lot) }}" class="btn btn-primary">
                    <i class="ri-pencil-line"></i> Edit
                </a>
            @endcan
            <a href="{{ route('lots.index') }}" class="btn btn-light">Back</a>
        </x-slot:actions>
    </x-page-title>

    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h4 class="mb-1">{{ $lot->supplier?->label() ?? 'In-house' }}</h4>
                            <code class="fs-14">{{ $lot->code }}</code>
                            <span class="text-muted ms-2">{{ $lot->lot_date->format('d M Y') }}</span>
                        </div>
                        <span class="badge bg-{{ $lot->statusVariant() }} fs-13">{{ $lot->statusLabel() }}</span>
                    </div>

                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <th style="width: 40%">Metal / Purity</th>
                                <td>
                                    {{ $lot->metalType?->name ?? '—' }}
                                    @if ($lot->purity)
                                        <span class="text-muted">· {{ $lot->purity->name }}</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Making Charge</th>
                                <td>
                                    @if ($lot->makingCharge)
                                        <code>{{ $lot->makingCharge->code }}</code>
                                        <span class="text-muted">— {{ $lot->makingCharge->summary() }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                            @if ($lot->notes)
                                <tr>
                                    <th>Notes</th>
                                    <td>{{ $lot->notes }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header py-2">
                    <h5 class="mb-0">Progress by Group</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-centered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Group</th>
                                    <th>Pieces</th>
                                    <th>Tags</th>
                                    <th>Entered</th>
                                    <th>Remaining</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lot->lines as $line)
                                    @php($left = $remaining[$line->item_group_id] ?? 0)
                                    <tr>
                                        <td>{{ $line->itemGroup?->name ?? '—' }}</td>
                                        <td>{{ $line->pieces }}</td>
                                        <td>{{ $line->tags }}</td>
                                        <td><strong>{{ $line->tags - $left }}</strong></td>
                                        <td>
                                            @if ($left > 0)
                                                <span class="badge bg-warning-subtle text-warning">{{ $left }} left</span>
                                            @else
                                                <span class="badge bg-success-subtle text-success">Complete</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th>Total</th>
                                    <th>{{ $lot->piecesExpected() }}</th>
                                    <th>{{ $lot->tagsExpected() }}</th>
                                    <th>{{ $lot->tagsUsed() }}</th>
                                    <th>{{ $lot->remainingTags() }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header py-2">
                    <h5 class="mb-0">Weight vs Declared</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <td>Gross entered</td>
                                <td class="text-end">{{ number_format($lot->grossEntered(), 3) }} g</td>
                            </tr>
                            <tr>
                                <td>Gross declared</td>
                                <td class="text-end">
                                    {{ $lot->total_gross_weight !== null ? number_format((float) $lot->total_gross_weight, 3) . ' g' : '—' }}
                                </td>
                            </tr>
                            <tr>
                                <td>Net entered</td>
                                <td class="text-end">{{ number_format($lot->netEntered(), 3) }} g</td>
                            </tr>
                            <tr>
                                <td>Net declared</td>
                                <td class="text-end">
                                    {{ $lot->total_net_weight !== null ? number_format((float) $lot->total_net_weight, 3) . ' g' : '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    @if ($lot->exceedsGrossTarget())
                        <div class="alert alert-warning py-2 fs-13 mb-0 mt-2">
                            <i class="ri-alert-line me-1"></i>
                            Items entered weigh more than the declared gross. Nothing is blocked — check the
                            declared total or the entered weights.
                        </div>
                    @endif
                </div>
            </div>

            @if ($lot->photoUrl())
                <div class="card">
                    <div class="card-header py-2">
                        <h5 class="mb-0">Photo</h5>
                    </div>
                    <div class="card-body text-center">
                        <img src="{{ $lot->photoUrl() }}" alt="{{ $lot->code }}" class="img-fluid rounded"
                            style="max-height: 220px;">
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header py-2">
            <h5 class="mb-0">Items in this Lot ({{ $items->count() }})</h5>
        </div>
        <div class="card-body">
            @if ($items->isEmpty())
                <p class="text-muted mb-0">No items entered yet.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-centered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Code</th>
                                <th>Group</th>
                                <th>Name</th>
                                <th>HUID</th>
                                <th class="text-end">Gross</th>
                                <th class="text-end">Net</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $item)
                                <tr>
                                    <td><code>{{ $item->code }}</code></td>
                                    <td>{{ $item->itemGroup?->name ?? '—' }}</td>
                                    <td>{{ $item->name }}</td>
                                    <td>{{ $item->huid ?: '—' }}</td>
                                    <td class="text-end">{{ number_format((float) $item->gross_weight, 3) }}</td>
                                    <td class="text-end">{{ number_format((float) $item->net_weight, 3) }}</td>
                                    <td class="text-end">
                                        <div class="row-actions">
                                            @can('item.view')
                                                <a href="{{ route('items.show', $item) }}"
                                                    class="btn btn-sm btn-soft-secondary btn-icon" title="View">
                                                    <i class="ri-eye-fill"></i>
                                                </a>
                                            @endcan
                                            @can('item.print')
                                                <a href="{{ route('items.label', $item) }}" target="_blank"
                                                    class="btn btn-sm btn-soft-info btn-icon" title="Print tag">
                                                    <i class="ri-price-tag-3-fill"></i>
                                                </a>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-end">Total</th>
                                <th class="text-end">{{ number_format($lot->grossEntered(), 3) }}</th>
                                <th class="text-end">{{ number_format($lot->netEntered(), 3) }}</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
