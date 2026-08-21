@extends('layouts.app')

@section('title', 'Order Forms')

@include('layouts.partials.datatables-assets')

@section('content')
    <x-page-title title="Order Forms">
        <x-slot:actions>
            @can('order_form.create')
                <a href="{{ route('order-forms.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Add
                </a>
            @endcan
        </x-slot:actions>
    </x-page-title>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <div class="row g-2 align-items-center mb-3">
                        <div class="col-md-2">
                            <select id="filter-status" class="form-select">
                                <option value="">All</option>
                                <option value="pending">Pending</option>
                                <option value="ready">Ready</option>
                            </select>
                        </div>

                        @can('order_form.print')
                            {{-- Orders print one at a time from the row button; only
                                 stickers are worth batching. --}}
                            <div class="col-auto">
                                <button type="button" class="btn btn-info" id="sticker-selected" disabled>
                                    <i class="ri-price-tag-3-line"></i> Print Sticker (<span id="selected-count">0</span>)
                                </button>
                            </div>
                            <div class="col-auto">
                                <a href="{{ route('order-forms.sticker-by-ref') }}" class="btn btn-warning">
                                    <i class="ri-search-line"></i> Sticker by Ref No
                                </a>
                            </div>
                        @endcan

                        <div class="col-auto">
                            <button type="button" class="btn btn-link text-muted d-none" id="clear-selection">Clear</button>
                        </div>

                        <div class="col-auto ms-auto">
                            <span class="badge bg-success-subtle text-success">Green — every piece held, awaiting delivery</span>
                        </div>
                    </div>

                    <table id="orders-table" class="table table-centered table-hover dt-responsive nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 3%">
                                    <input type="checkbox" class="form-check-input" id="check-page"
                                        aria-label="Select all on this page">
                                </th>
                                <th>Ref No</th>
                                <th>Name</th>
                                <th>Contact No</th>
                                <th>Date</th>
                                <th>Delivery Date</th>
                                <th>Other Orders</th>
                                <th>Ready</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                    </table>

                </div>
            </div>
        </div>
    </div>

    @can('order_form.print')
        <form method="POST" target="_blank" id="print-form">
            @csrf
            <div id="print-ids"></div>
        </form>
    @endcan
@endsection

@push('js')
    <script>
        $(function () {
            const formUrl = '{{ route('order-forms.print') }}';
            const stickerUrl = '{{ route('order-forms.stickers') }}';

            // Server-side, so ticked rows would vanish on paging. Hold the ids here
            // and re-apply them on every draw.
            const selected = new Set();

            const table = window.appDataTable('#orders-table', {
                ajax: {
                    url: '{{ route('order-forms.index') }}',
                    data: function (params) {
                        params.status = $('#filter-status').val();
                    }
                },
                order: [[1, 'desc']],
                columns: [
                    { data: 'select', name: 'select', orderable: false, searchable: false, className: 'text-center' },
                    { data: 'ref', name: 'ref' },
                    { data: 'customer', name: 'customer' },
                    { data: 'contact', name: 'contact' },
                    { data: 'form_date', name: 'form_date', searchable: false },
                    { data: 'delivery_date', name: 'delivery_date', searchable: false },
                    { data: 'other_orders', name: 'other_orders', orderable: false, searchable: false },
                    { data: 'progress', name: 'progress', orderable: false, searchable: false },
                    { data: 'status', name: 'status', orderable: false, searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                ]
            });

            function refreshControls() {
                $('#selected-count').text(selected.size);
                $('#sticker-selected').prop('disabled', selected.size === 0);
                $('#clear-selection').toggleClass('d-none', selected.size === 0);

                const boxes = $('#orders-table tbody .order-check');
                $('#check-page').prop('checked', boxes.length > 0 && boxes.filter(':checked').length === boxes.length);
            }

            table.on('draw', function () {
                $('#orders-table tbody .order-check').each(function () {
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
                $('#orders-table tbody .order-check').each(function () {
                    $(this).prop('checked', on);
                    on ? selected.add(this.value) : selected.delete(this.value);
                });
                refreshControls();
            });

            $('#clear-selection').on('click', function () {
                selected.clear();
                $('#orders-table tbody .order-check').prop('checked', false);
                refreshControls();
            });

            $('#filter-status').on('change', () => table.ajax.reload());

            function submitPrint(url, ids) {
                const $box = $('#print-ids').empty();
                ids.forEach(id => $box.append($('<input type="hidden" name="ids[]">').val(id)));
                $('#print-form').attr('action', url).trigger('submit');
            }

            $('#sticker-selected').on('click', () => submitPrint(stickerUrl, [...selected]));
            $(document).on('click', '.print-one', function () { submitPrint(formUrl, [$(this).data('id')]); });
            $(document).on('click', '.sticker-one', function () { submitPrint(stickerUrl, [$(this).data('id')]); });

            // "Save & Print" lands back here and prints the order just saved.
            @if (session('printAfterSave'))
                submitPrint(formUrl, ['{{ session('printAfterSave') }}']);
            @endif
        });
    </script>
@endpush
