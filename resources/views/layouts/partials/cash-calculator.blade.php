{{--
    The till calculator, reachable from the topbar on any page.

    Two things side by side: what the books say should be in the drawers, and what the
    person counting has actually found. The difference between them is the point of
    the screen.

    The note counts are saved per user, so a count interrupted by a customer is still
    there afterwards. Nothing derived is stored — every total on screen is worked out
    from the counts, on both sides, from the same rule.

    Only rendered for someone who can see the cash listing: the drawer position is the
    shop's money.
--}}
@can('cash_entry.view')
    <div class="modal fade" id="cashCalculatorModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="ri-calculator-line me-1"></i> Cash Calculator
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    {{-- What the books say. --}}
                    <div class="row g-2 mb-3">
                        <div class="col-sm-4">
                            <div class="border rounded p-2 text-center h-100">
                                <p class="text-muted mb-1 fs-12 text-uppercase">Drawer Cash</p>
                                <h4 class="mb-0" id="calc-expected-cash">—</h4>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="border rounded p-2 text-center h-100">
                                <p class="text-muted mb-1 fs-12 text-uppercase">Gold (g)</p>
                                <h4 class="mb-0" id="calc-expected-gold">—</h4>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="border rounded p-2 text-center h-100">
                                <p class="text-muted mb-1 fs-12 text-uppercase">Counted &minus; Drawer</p>
                                <h4 class="mb-0" id="calc-difference">—</h4>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0" id="calc-table">
                            <thead class="table-light">
                                <tr>
                                    @foreach (App\Models\CashCalculator::COLUMNS as $key => $label)
                                        <th class="text-center" style="width: 12%">Note</th>
                                        <th class="text-center">{{ $label }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (App\Models\CashCalculator::DENOMINATIONS as $note)
                                    <tr>
                                        @foreach (array_keys(App\Models\CashCalculator::COLUMNS) as $key)
                                            <td class="text-center fw-semibold">{{ $note }}</td>
                                            <td>
                                                {{-- inputmode=numeric so a phone offers the number pad;
                                                     .whole-number is the app-wide integer coercion. --}}
                                                <input type="number" class="form-control form-control-sm calc-count whole-number"
                                                    data-column="{{ $key }}" data-note="{{ $note }}"
                                                    min="0" step="1" inputmode="numeric" placeholder="0">
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="table-light">
                                    @foreach (array_keys(App\Models\CashCalculator::COLUMNS) as $key)
                                        <td class="text-center fw-bold">TOTAL</td>
                                        <td class="text-end fw-bold calc-column-total" data-column="{{ $key }}">0</td>
                                    @endforeach
                                </tr>
                                <tr>
                                    <td colspan="{{ count(App\Models\CashCalculator::COLUMNS) * 2 - 1 }}"
                                        class="fw-bold text-uppercase">
                                        Final Total
                                    </td>
                                    <td class="text-end fw-bold fs-18" id="calc-final-total">0</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <p class="text-muted fs-12 mb-0 mt-2" id="calc-saved-at"></p>
                </div>

                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-light" id="calc-clear">Clear</button>
                    <span class="text-muted fs-13" id="calc-status"></span>
                </div>
            </div>
        </div>
    </div>

    @push('js')
        <script>
            $(function () {
                const showUrl = '{{ route('cash-calculator.show') }}';
                const saveUrl = '{{ route('cash-calculator.store') }}';
                const money = n => Number(n || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 });

                let expectedCash = 0;
                let loaded = false;

                function grid() {
                    const counts = {};

                    $('.calc-count').each(function () {
                        const column = $(this).data('column');
                        counts[column] = counts[column] || {};
                        counts[column][$(this).data('note')] = parseInt($(this).val(), 10) || 0;
                    });

                    return counts;
                }

                // The same rule as CashCalculator::totals() on the server. Restated
                // here so the figures move as the notes are typed; the server's copy
                // is what any stored total is worked out from.
                function recalc() {
                    let final = 0;

                    $('.calc-column-total').each(function () {
                        const column = $(this).data('column');
                        let sum = 0;

                        $('.calc-count[data-column="' + column + '"]').each(function () {
                            sum += (parseInt($(this).data('note'), 10) || 0) * (parseInt($(this).val(), 10) || 0);
                        });

                        $(this).text(money(sum));
                        final += sum;
                    });

                    $('#calc-final-total').text(money(final));

                    const difference = final - expectedCash;

                    $('#calc-difference')
                        .text((difference > 0 ? '+' : '') + money(difference))
                        .removeClass('text-success text-danger')
                        // Only colour a real gap: an exact count is the normal case and
                        // should not read as an alarm.
                        .addClass(difference === 0 ? '' : (difference > 0 ? 'text-success' : 'text-danger'));
                }

                function fill(data) {
                    expectedCash = Number(data.expected.cash || 0);

                    $('#calc-expected-cash').text(money(expectedCash));
                    $('#calc-expected-gold').text(Number(data.expected.gold || 0).toFixed(3));
                    $('#calc-saved-at').text(data.saved_at ? 'Last saved ' + data.saved_at : '');

                    $('.calc-count').each(function () {
                        const value = (data.counts[$(this).data('column')] || {})[$(this).data('note')] || 0;
                        $(this).val(value === 0 ? '' : value);
                    });

                    recalc();
                }

                // Loaded when the modal opens, not on every page load: this is one
                // extra query and most pages never open it. Re-fetched each time so
                // the drawer figure is never a stale one from an hour ago.
                $('#cashCalculatorModal').on('show.bs.modal', function () {
                    // loaded stays false until the fetch lands, so filling the boxes
                    // from the server cannot trigger a save of what was just read.
                    loaded = false;

                    $.getJSON(showUrl)
                        .done(function (data) {
                            fill(data);
                            loaded = true;
                        })
                        .fail(() => window.appToast('error', 'Could not read the drawer position.'));
                });

                $(document).on('input', '.calc-count', recalc);

                $('#calc-clear').on('click', function () {
                    $('.calc-count').val('');
                    recalc();
                    save();
                });

                // --- autosave ------------------------------------------------------
                // No save button: the count is written as it is typed. Debounced so a
                // six-digit number is one request rather than six, and serialised on a
                // single in-flight flag so a slow reply cannot land after a newer one
                // and put an older count back.
                let timer = null;
                let inFlight = false;
                let pending = false;

                function save() {
                    if (!loaded) {
                        return;
                    }

                    if (inFlight) {
                        pending = true;

                        return;
                    }

                    inFlight = true;
                    $('#calc-status').removeClass('text-danger').text('Saving…');

                    $.ajax({
                        url: saveUrl,
                        method: 'POST',
                        data: { _token: '{{ csrf_token() }}', counts: grid() },
                    })
                        .done(function (data) {
                            $('#calc-saved-at').text('Last saved ' + data.saved_at);
                            $('#calc-status').removeClass('text-danger').text('Saved');
                        })
                        .fail(function () {
                            // Said in the modal rather than as a toast: the box is open
                            // and in front of the user, and a toast behind it would be
                            // the wrong place to report that their count is not stored.
                            $('#calc-status').addClass('text-danger').text('Not saved — check your connection');
                        })
                        .always(function () {
                            inFlight = false;

                            if (pending) {
                                pending = false;
                                save();
                            }
                        });
                }

                function queueSave() {
                    clearTimeout(timer);
                    $('#calc-status').removeClass('text-danger').text('');
                    timer = setTimeout(save, 600);
                }

                $(document).on('input', '.calc-count', queueSave);

                // Anything still queued goes now rather than being lost with the modal.
                $('#cashCalculatorModal').on('hide.bs.modal', function () {
                    if (timer) {
                        clearTimeout(timer);
                        timer = null;
                        save();
                    }
                });
            });
        </script>
    @endpush
@endcan
