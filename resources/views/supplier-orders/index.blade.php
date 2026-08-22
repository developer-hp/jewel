@extends('layouts.app')

@section('title', 'Supplier Orders')

@include('layouts.partials.datatables-assets')

@section('content')
    <x-page-title title="Supplier Orders">
        <x-slot:actions>
            @can('supplier_order.delete')
                <a href="{{ route('supplier-orders.scan') }}" class="btn btn-dark">
                    <i class="ri-qr-scan-2-line"></i> Scan
                </a>
            @endcan
            @can('supplier_order.create')
                <a href="{{ route('supplier-orders.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Add
                </a>
            @endcan
        </x-slot:actions>
    </x-page-title>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-md-2">
                            <label for="filter-status" class="form-label mb-1 fs-13">Status</label>
                            <select id="filter-status" class="form-select">
                                <option value="">All</option>
                                <option value="pending" selected>Pending</option>
                                <option value="overdue">Overdue</option>
                                <option value="received">Received</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="filter-supplier" class="form-label mb-1 fs-13">Supplier</label>
                            <select id="filter-supplier" class="form-select">
                                <option value="">All suppliers</option>
                                @foreach ($suppliers as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label for="filter-from" class="form-label mb-1 fs-13">From</label>
                            <input type="date" id="filter-from" class="form-control">
                        </div>

                        <div class="col-md-2">
                            <label for="filter-to" class="form-label mb-1 fs-13">To</label>
                            <input type="date" id="filter-to" class="form-control">
                        </div>

                        <div class="col-auto">
                            <button type="button" id="filter-reset" class="btn btn-danger">
                                <i class="ri-refresh-line"></i> Reset
                            </button>
                        </div>

                        @can('supplier_order.print')
                            <div class="col-auto">
                                <button type="button" class="btn btn-secondary" id="print-selected" disabled>
                                    <i class="ri-printer-line"></i> Print (<span id="selected-count">0</span>)
                                </button>
                            </div>
                        @endcan

                        <div class="col-auto">
                            <button type="button" class="btn btn-link text-muted d-none" id="clear-selection">Clear</button>
                        </div>
                    </div>

                    <table id="supplier-orders-table" class="table table-centered table-hover dt-responsive nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 3%">
                                    <input type="checkbox" class="form-check-input" id="check-page"
                                        aria-label="Select all on this page">
                                </th>
                                <th>Form No</th>
                                <th>Date</th>
                                <th>Supplier</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Order Form No</th>
                                <th>Followup</th>
                                <th>Delivery</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                    </table>

                </div>
            </div>
        </div>
    </div>

    @can('supplier_order.print')
        {{-- POST because a day's worth of ticked ids will not fit a query string. --}}
        <form method="POST" action="{{ route('supplier-orders.print') }}" target="_blank" id="print-form">
            @csrf
            <div id="print-ids"></div>
        </form>
    @endcan
@endsection

@push('js')
    <script>
        $(function () {
            // Server-side, so ticked rows would vanish on paging. Hold the ids here
            // and re-apply them on every draw.
            const selected = new Set();

            const table = window.appDataTable('#supplier-orders-table', {
                ajax: {
                    url: '{{ route('supplier-orders.index') }}',
                    data: function (params) {
                        params.status = $('#filter-status').val();
                        params.supplier_id = $('#filter-supplier').val();
                        params.from = $('#filter-from').val();
                        params.to = $('#filter-to').val();
                    }
                },
                order: [[1, 'desc']],
                columns: [
                    { data: 'select', name: 'select', orderable: false, searchable: false, className: 'text-center' },
                    { data: 'form_no', name: 'form_no' },
                    { data: 'order_date', name: 'order_date', searchable: false },
                    { data: 'supplier', name: 'supplier' },
                    { data: 'type', name: 'type' },
                    { data: 'description', name: 'description' },
                    { data: 'order_form_ref', name: 'order_form_ref' },
                    { data: 'followup_date', name: 'followup_date', searchable: false },
                    { data: 'customer_delivery_date', name: 'customer_delivery_date', searchable: false },
                    { data: 'status', name: 'status', searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                ]
            });

            function refreshControls() {
                $('#selected-count').text(selected.size);
                $('#print-selected').prop('disabled', selected.size === 0);
                $('#clear-selection').toggleClass('d-none', selected.size === 0);

                const boxes = $('#supplier-orders-table tbody .order-check');
                $('#check-page').prop('checked', boxes.length > 0 && boxes.filter(':checked').length === boxes.length);
            }

            table.on('draw', function () {
                $('#supplier-orders-table tbody .order-check').each(function () {
                    $(this).prop('checked', selected.has($(this).val()));
                });
                refreshControls();
            });

            $(document).on('change', '.order-check', function () {
                this.checked ? selected.add(this.value) : selected.delete(this.value);
                refreshControls();
            });

            $('#check-page').on('change', function () {
                const on = this.checked;
                $('#supplier-orders-table tbody .order-check').each(function () {
                    $(this).prop('checked', on);
                    on ? selected.add(this.value) : selected.delete(this.value);
                });
                refreshControls();
            });

            $('#clear-selection').on('click', function () {
                selected.clear();
                $('#supplier-orders-table tbody .order-check').prop('checked', false);
                refreshControls();
            });

            $('#filter-status, #filter-supplier, #filter-from, #filter-to').on('change', () => table.ajax.reload());

            $('#filter-reset').on('click', function () {
                $('#filter-supplier, #filter-from, #filter-to').val('');
                $('#filter-status').val('pending');
                table.search('').ajax.reload();
            });

            function submitPrint(ids) {
                const $box = $('#print-ids').empty();
                ids.forEach(id => $box.append($('<input type="hidden" name="ids[]">').val(id)));
                $('#print-form').trigger('submit');
            }

            $('#print-selected').on('click', () => submitPrint([...selected]));
            $(document).on('click', '.print-one', function () { submitPrint([$(this).data('id')]); });

            // "Save & Print" lands back here and prints the slip just saved.
            @if (session('printAfterSave'))
                submitPrint(['{{ session('printAfterSave') }}']);
            @endif
        });
    </script>
@endpush
