@extends('layouts.app')

@section('title', 'Item ' . $item->code)

@section('content')
    <x-page-title :title="'Item — ' . $item->code">
        <x-slot:actions>
            @can('item.print')
                <a href="{{ route('items.label', $item) }}" target="_blank" class="btn btn-soft-primary">
                    <i class="ri-price-tag-3-line"></i> Print Tag
                </a>
            @endcan
            @can('item.edit')
                <a href="{{ route('items.edit', $item) }}" class="btn btn-primary">
                    <i class="ri-pencil-line"></i> Edit
                </a>
            @endcan
            <a href="{{ route('items.index') }}" class="btn btn-light">Back</a>
        </x-slot:actions>
    </x-page-title>

    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h4 class="mb-1">{{ $item->name }}</h4>
                            <code class="fs-14">{{ $item->code }}</code>
                        </div>
                        <x-status-badge :active="$item->is_active" />
                    </div>

                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <th style="width: 40%">HUID</th>
                                <td>{{ $item->huid ?: '—' }}</td>
                            </tr>
                            <tr>
                                <th>Item Group</th>
                                <td>{{ $item->itemGroup?->name ?? '—' }}</td>
                            </tr>
                            <tr>
                                <th>Supplier</th>
                                <td>
                                    @if ($item->supplier)
                                        {{ $item->supplier->label() }}
                                        @if ($item->supplier->city)
                                            <span class="text-muted">· {{ $item->supplier->city }}</span>
                                        @endif
                                    @else
                                        <span class="text-muted">In-house</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Metal Type</th>
                                <td>{{ $item->metalType?->name ?? '—' }}</td>
                            </tr>
                            <tr>
                                <th>Purity</th>
                                <td>
                                    {{ $item->purity?->name ?? '—' }}
                                    @if ($item->purity?->touch)
                                        <span class="text-muted">({{ rtrim(rtrim($item->purity->touch, '0'), '.') }}%)</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Making Charge</th>
                                <td>
                                    @if ($item->makingCharge)
                                        <code>{{ $item->makingCharge->code }}</code>
                                        <span class="text-muted">— {{ $item->makingCharge->summary() }}</span>
                                        <div class="text-muted fs-12">Applied when this item is quoted.</div>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                            @if ($item->description)
                                <tr>
                                    <th>Description</th>
                                    <td>{{ $item->description }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            @include('items.partials.photo-card')

            <div class="card">
                <div class="card-header py-2">
                    <h5 class="mb-0">Weights</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <td>Gross weight</td>
                                <td class="text-end">{{ number_format((float) $item->gross_weight, 3) }} g</td>
                            </tr>
                            <tr>
                                <td>Less: stones</td>
                                <td class="text-end text-danger">−{{ number_format((float) $item->stone_weight_grams, 3) }} g</td>
                            </tr>
                            <tr>
                                <td>Less: diamonds</td>
                                <td class="text-end text-danger">−{{ number_format((float) $item->diamond_weight_grams, 3) }} g</td>
                            </tr>
                            <tr>
                                <td>Less: other</td>
                                <td class="text-end text-danger">−{{ number_format((float) $item->other_deduction, 3) }} g</td>
                            </tr>
                            <tr class="table-active">
                                <th>Net weight</th>
                                <th class="text-end">{{ number_format((float) $item->net_weight, 3) }} g</th>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header py-2">
                    <h5 class="mb-0">Indicative Value</h5>
                </div>
                <div class="card-body">
                    @php($metalValue = $item->metalValueOn())
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <td>
                                    Metal value
                                    @if ($rate = $item->purity?->rateOn())
                                        <div class="text-muted fs-12">
                                            @ ₹{{ number_format((float) $rate->rate_per_gram, 2) }}/g
                                            · {{ $rate->effective_date->format('d M Y') }}
                                        </div>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if ($metalValue !== null)
                                        ₹{{ number_format($metalValue, 2) }}
                                    @else
                                        <span class="badge bg-warning-subtle text-warning">No rate set</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td>Stone + diamond value</td>
                                <td class="text-end">₹{{ number_format($item->stoneValue(), 2) }}</td>
                            </tr>
                            @foreach ($item->extraChargeLines() as $line)
                                <tr>
                                    <td>{{ $line['label'] }}</td>
                                    <td class="text-end">₹{{ number_format($line['amount'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="text-muted fs-12 mb-0 mt-2">
                        Excludes making charge, wastage and tax — the quotation computes the final price.
                    </p>
                </div>
            </div>
        </div>
    </div>

    @foreach ([['stone', 'Stones'], ['diamond', 'Diamonds']] as [$kind, $label])
        @php($rows = $item->itemStones->where('kind', $kind))
        @if ($rows->isNotEmpty())
            <div class="card">
                <div class="card-header py-2">
                    <h5 class="mb-0">{{ $label }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-centered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ Str::singular($label) }}</th>
                                    <th>Pcs</th>
                                    <th>Carat</th>
                                    <th>Grams</th>
                                    <th>Unit</th>
                                    <th class="text-end">Rate (₹)</th>
                                    <th class="text-end">Amount (₹)</th>
                                    <th class="text-center">Deducted</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr>
                                        <td>{{ $row->stoneMaster?->name ?? '—' }}</td>
                                        <td>{{ $row->pieces }}</td>
                                        <td>{{ number_format((float) $row->weight_carat, 3) }}</td>
                                        <td>{{ number_format((float) $row->weight_grams, 4) }}</td>
                                        <td><span class="badge bg-secondary-subtle text-secondary">{{ $row->rate_unit }}</span></td>
                                        <td class="text-end">{{ number_format((float) $row->rate, 2) }}</td>
                                        <td class="text-end fw-semibold">{{ number_format((float) $row->amount, 2) }}</td>
                                        <td class="text-center">
                                            @if ($row->deduct_from_gross)
                                                <i class="ri-check-line text-success fs-18"></i>
                                            @else
                                                <i class="ri-close-line text-muted fs-18"></i>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" class="text-end">Total</th>
                                    <th>{{ number_format((float) $rows->sum('weight_grams'), 4) }}</th>
                                    <th colspan="2"></th>
                                    <th class="text-end">₹{{ number_format((float) $rows->sum('amount'), 2) }}</th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
@endsection
