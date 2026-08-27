@extends('layouts.app')

@section('title', 'Supplier Hisab')

@include('layouts.partials.datatables-assets')
@include('layouts.partials.select2-assets')

@section('content')
    <x-page-title title="Supplier Hisab" />

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-sm-auto">
                            <label for="filter-date" class="form-label mb-1 fs-13">Date</label>
                            <input type="date" id="filter-date" class="form-control"
                                value="{{ $date->toDateString() }}">
                        </div>

                        @can('supplier_hisab.edit')
                            <div class="col-sm-auto">
                                <form method="POST" action="{{ route('supplier-hisabs.rate') }}"
                                    class="d-flex align-items-end gap-2">
                                    @csrf
                                    <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                                    <div>
                                        <label for="hisab_rate_per_10g" class="form-label mb-1 fs-13">
                                            Rate Today <span class="text-muted">(per 10 g)</span>
                                        </label>
                                        <input type="number" step="0.01" min="0" id="hisab_rate_per_10g"
                                            name="hisab_rate_per_10g" class="form-control"
                                            value="{{ old('hisab_rate_per_10g', $ratePer10g) }}" required>
                                    </div>
                                    <button type="submit" class="btn btn-warning">
                                        <i class="ri-save-line"></i> Save
                                    </button>
                                </form>
                            </div>
                        @else
                            <div class="col-sm-auto">
                                <label class="form-label mb-1 fs-13">Rate Today <span class="text-muted">(per 10 g)</span></label>
                                <input type="text" class="form-control bg-light" value="{{ $ratePer10g }}" readonly>
                            </div>
                        @endcan
                        @can('supplier_hisab.create')
                            <div class="col-auto">
                                <button type="button" class="btn btn-success" id="hisab-add">
                                    <i class="ri-add-line"></i> Add
                                </button>
                            </div>
                        @endcan

                        @can('supplier_hisab.print')
                            <div class="col-auto">
                                <button type="button" class="btn btn-info" id="print-selected" disabled>
                                    <i class="ri-file-pdf-2-line"></i> PDF (<span id="selected-count">0</span>)
                                </button>
                            </div>
                            <div class="col-auto">
                                <a class="btn btn-warning" id="summary-link" target="_blank"
                                    href="{{ route('supplier-hisabs.summary', ['date' => $date->toDateString()]) }}">
                                    <i class="ri-file-list-3-line"></i> Summary
                                </a>
                            </div>
                        @endcan

                        <div class="col-auto">
                            <button type="button" class="btn btn-danger" id="hisab-refresh">
                                <i class="ri-refresh-line"></i> Refresh
                            </button>
                        </div>

                        <div class="col-auto">
                            <button type="button" class="btn btn-link text-muted d-none" id="clear-selection">Clear</button>
                        </div>
                    </div>

                    <table id="hisabs-table" class="table table-centered table-hover dt-responsive nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 3%">
                                    <input type="checkbox" class="form-check-input" id="check-page"
                                        aria-label="Select all on this page">
                                </th>
                                <th>Supplier</th>
                                <th class="text-end">Gold Wt</th>
                                <th class="text-end">Amount</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tfoot>
                            <tr class="fw-bold">
                                <td></td>
                                <td></td>
                                <td class="text-end"><span id="total-fine">0</span> grm</td>
                                <td class="text-end"><span id="total-cash">0</span> RS</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>

                </div>
            </div>
        </div>
    </div>

    @can('supplier_hisab.print')
        {{-- POST because a day's worth of ticked ids will not fit a query string. --}}
        <form method="POST" action="{{ route('supplier-hisabs.print') }}" target="_blank" id="print-form">
            @csrf
            <div id="print-ids"></div>
        </form>
    @endcan

    @include('supplier-hisabs.partials._modal')
@endsection

@push('js')
    <script>
        $(function () {
            const listUrl = '{{ route('supplier-hisabs.index') }}';

            // Server-side, so ticked rows would vanish on paging. Hold the ids here
            // and re-apply them on every draw.
            const selected = new Set();

            function currentDate() {
                return $('#filter-date').val() || '{{ $date->toDateString() }}';
            }

            const table = window.appDataTable('#hisabs-table', {
                ajax: {
                    url: listUrl,
                    data: function (params) {
                        params.date = currentDate();
                    }
                },
                order: [[1, 'asc']],
                columns: [
                    { data: 'select', name: 'select', orderable: false, searchable: false, className: 'text-center' },
                    { data: 'supplier', name: 'supplier' },
                    { data: 'gold_wt', name: 'gold_wt', searchable: false, className: 'text-end' },
                    { data: 'amount', name: 'amount', searchable: false, className: 'text-end' },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                ],
                drawCallback: function (settings) {
                    const totals = (settings.json || {}).totals || { fine_baki: '0', cash_baki: '0.00' };
                    $('#total-fine').text(totals.fine_baki);
                    $('#total-cash').text(totals.cash_baki);
                }
            });

            function refreshControls() {
                $('#selected-count').text(selected.size);
                $('#print-selected').prop('disabled', selected.size === 0);
                $('#clear-selection').toggleClass('d-none', selected.size === 0);

                const boxes = $('#hisabs-table tbody .hisab-check');
                $('#check-page').prop('checked', boxes.length > 0 && boxes.filter(':checked').length === boxes.length);
            }

            table.on('draw', function () {
                $('#hisabs-table tbody .hisab-check').each(function () {
                    $(this).prop('checked', selected.has($(this).val()));
                });
                refreshControls();
            });

            $(document).on('change', '.hisab-check', function () {
                this.checked ? selected.add(this.value) : selected.delete(this.value);
                refreshControls();
            });

            $('#check-page').on('change', function () {
                const on = this.checked;
                $('#hisabs-table tbody .hisab-check').each(function () {
                    $(this).prop('checked', on);
                    on ? selected.add(this.value) : selected.delete(this.value);
                });
                refreshControls();
            });

            $('#clear-selection').on('click', function () {
                selected.clear();
                $('#hisabs-table tbody .hisab-check').prop('checked', false);
                refreshControls();
            });

            // Moving day changes what is on screen, so the old selection is stale.
            $('#filter-date').on('change', function () {
                selected.clear();
                refreshControls();
                $('#summary-link').attr('href', '{{ route('supplier-hisabs.summary') }}?date=' + currentDate());
                $('#hisab-date').val(currentDate());
                table.ajax.reload();
            });

            $('#hisab-refresh').on('click', () => table.ajax.reload(null, false));

            function submitPrint(ids) {
                const $box = $('#print-ids').empty();
                ids.forEach(id => $box.append($('<input type="hidden" name="ids[]">').val(id)));
                $('#print-form').trigger('submit');
            }

            $('#print-selected').on('click', () => submitPrint([...selected]));
            $(document).on('click', '.print-one', function () { submitPrint([$(this).data('id')]); });
        });
    </script>
@endpush
