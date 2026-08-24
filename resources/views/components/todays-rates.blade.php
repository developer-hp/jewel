{{--
    "Today's Rates" — a button and the popup behind it, for any form where the
    counter needs the morning's figures to hand without leaving what they are typing.

    The table is fetched on open (rates.snapshot), not rendered with the page, so it
    is current even on a form left sitting open since before the rates were entered.
    Refetched on every open for the same reason.

    Nothing renders at all without metal_rate.view — the endpoint enforces it too.
--}}
@props(['label' => "Today's Rates"])

@can('metal_rate.view')
    <button type="button" class="btn btn-sm btn-soft-info" data-bs-toggle="modal" data-bs-target="#todaysRatesModal">
        <i class="ri-exchange-funds-fill"></i> {{ $label }}
    </button>

    <div class="modal fade" id="todaysRatesModal" tabindex="-1" aria-labelledby="todaysRatesLabel" aria-hidden="true"
        data-rates-url="{{ route('rates.snapshot') }}">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="todaysRatesLabel">Today's Rates</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body" id="todaysRatesBody">
                    <div class="text-center py-4 text-muted">
                        <div class="spinner-border spinner-border-sm me-1" role="status"></div> Loading…
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('js')
        <script>
            (function ($) {
                'use strict';

                var modalEl = document.getElementById('todaysRatesModal');
                var body = document.getElementById('todaysRatesBody');

                if (!modalEl) {
                    return;
                }

                modalEl.addEventListener('show.bs.modal', function () {
                    body.innerHTML = '<div class="text-center py-4 text-muted">'
                        + '<div class="spinner-border spinner-border-sm me-1" role="status"></div> Loading…</div>';

                    $.get(modalEl.dataset.ratesUrl)
                        .done(function (html) {
                            body.innerHTML = html;
                        })
                        .fail(function () {
                            body.innerHTML = '<div class="alert alert-danger mb-0">'
                                + 'Could not load the rates. Try again in a moment.</div>';
                        });
                });
            })(window.jQuery);
        </script>
    @endpush
@endcan
