{{--
    Stones and diamonds for one row of the lot entry screen.

    Opened with F4 while typing a row, or from a queued row's stone badge. Rows are
    held in JavaScript and written into the form as hidden inputs on submit, so this
    modal sits outside the <form> deliberately — nothing in it posts directly.
--}}
<div class="modal fade" id="row-stone-modal" tabindex="-1" aria-labelledby="row-stone-title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header py-2">
                <h5 class="modal-title" id="row-stone-title">
                    Stones &amp; Diamonds
                    <small class="text-muted">— <span id="ms-target">the row being entered</span></small>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                @foreach ([['stone', 'Stones'], ['diamond', 'Diamonds']] as [$kind, $label])
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <h6 class="mb-0">{{ $label }}</h6>
                        <button type="button" class="btn btn-sm btn-primary ms-add" data-kind="{{ $kind }}">
                            <i class="ri-add-line"></i> Add {{ Str::singular($label) }}
                        </button>
                    </div>

                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-centered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 24%">{{ Str::singular($label) }}</th>
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
                            <tbody id="ms-{{ $kind }}-body"></tbody>
                        </table>
                    </div>
                @endforeach

                <div class="d-flex justify-content-end gap-4 fs-13">
                    <div>Deducted weight: <strong><span id="ms-total-grams">0.0000</span> g</strong></div>
                    <div>Total value: <strong>₹<span id="ms-total-amount">0.00</span></strong></div>
                </div>
            </div>

            <div class="modal-footer py-2">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="ms-apply">Apply to Row</button>
            </div>

        </div>
    </div>
</div>
