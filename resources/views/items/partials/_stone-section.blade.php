{{--
    A repeatable stone or diamond table.

    $section  — 'stone' | 'diamond'
    $title    — card heading
    $masters  — StoneMaster collection for that kind
    $rows     — existing ItemStone rows
    $offset   — starting array index, so stones and diamonds never collide in stones[]
--}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center py-2">
        <h5 class="mb-0">{{ $title }}</h5>
        <button type="button" class="btn btn-sm btn-soft-primary add-stone-row" data-section="{{ $section }}">
            <i class="ri-add-line"></i> Add Row
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-centered mb-0" data-section="{{ $section }}">
                <thead class="table-light">
                    <tr>
                        <th style="width: 24%">{{ ucfirst($section) }}</th>
                        <th style="width: 9%">Pcs</th>
                        <th style="width: 12%">Carat</th>
                        <th style="width: 12%">Grams</th>
                        <th style="width: 10%">Unit</th>
                        <th style="width: 13%">Rate (₹)</th>
                        <th style="width: 12%" class="text-end">Amount (₹)</th>
                        <th style="width: 6%" class="text-center" title="Deduct weight from gross">Ded.</th>
                        <th style="width: 4%"></th>
                    </tr>
                </thead>
                <tbody class="stone-rows">
                    @foreach ($rows as $i => $row)
                        @include('items.partials._stone-row', [
                            'section' => $section,
                            'masters' => $masters,
                            'index' => $offset + $i,
                            'row' => $row,
                        ])
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" class="text-end">Section total</th>
                        <th><span class="section-grams">0.0000</span> g</th>
                        <th colspan="2"></th>
                        <th class="text-end">₹<span class="section-amount">0.00</span></th>
                        <th colspan="2"></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        @if ($rows->isEmpty())
            <p class="text-muted fs-13 mb-0 mt-2 empty-hint">No {{ $section }}s added.</p>
        @endif
    </div>
</div>

{{-- Cloned by the add-row handler; __INDEX__ is replaced with a running counter. --}}
<template id="{{ $section }}-row-template">
    @include('items.partials._stone-row', [
        'section' => $section,
        'masters' => $masters,
        'index' => '__INDEX__',
        'row' => null,
    ])
</template>
