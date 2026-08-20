{{--
    A repeatable stone or diamond table, inside its own modal.

    The modal markup stays where it is rendered — inside the item <form> — because
    Bootstrap 5 does not relocate it in the DOM. The row inputs therefore post with
    the rest of the form exactly as they did when the table sat on the page.

    $section  — 'stone' | 'diamond'
    $title    — modal heading
    $masters  — StoneMaster collection for that kind
    $rows     — existing ItemStone rows
    $offset   — starting array index, so stones and diamonds never collide in stones[]
--}}
<div class="modal fade" id="{{ $section }}-modal" tabindex="-1"
    aria-labelledby="{{ $section }}-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content" data-section="{{ $section }}">

            <div class="modal-header py-2">
                <h5 class="modal-title" id="{{ $section }}-modal-title">{{ $title }}</h5>
                <div class="ms-auto d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-soft-primary add-stone-row"
                        data-section="{{ $section }}">
                        <i class="ri-add-line"></i> Add Row
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>

            <div class="modal-body">
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

                <p class="text-muted fs-12 mb-0 mt-2">
                    Rows are saved with the item — closing this window keeps them.
                    A row with no {{ $section }} selected is ignored.
                </p>
            </div>

            <div class="modal-footer py-2">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
            </div>

        </div>
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
