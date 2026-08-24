@extends('layouts.app')

@section('title', 'OG Estimates')

@include('layouts.partials.datatables-assets')

@section('content')
    <x-page-title title="OG Estimates">
        <x-slot:actions>
            @can('og_estimate.create')
                <a href="{{ route('og-estimates.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Add
                </a>
            @endcan
        </x-slot:actions>
    </x-page-title>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    @can('og_estimate.print')
                        <div class="row g-2 align-items-center mb-3">
                            <div class="col-auto">
                                <button type="button" class="btn btn-secondary" id="print-selected" disabled>
                                    <i class="ri-printer-line"></i> Print (<span id="selected-count">0</span>)
                                </button>
                            </div>
                            <div class="col-auto">
                                <button type="button" class="btn btn-link text-muted d-none" id="clear-selection">Clear</button>
                            </div>
                        </div>
                    @endcan

                    <table id="estimates-table" class="table table-centered table-hover dt-responsive nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 3%">
                                    <input type="checkbox" class="form-check-input" id="check-page"
                                        aria-label="Select all on this page">
                                </th>
                                <th>Ref No</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Mobile</th>
                                <th>Order No</th>
                                <th class="text-end">Fine Wt</th>
                                <th class="text-end">Value</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                    </table>

                </div>
            </div>
        </div>
    </div>

    @can('og_estimate.print')
        <form method="POST" action="{{ route('og-estimates.print') }}" target="_blank" id="print-form">
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

            const table = window.appDataTable('#estimates-table', {
                ajax: '{{ route('og-estimates.index') }}',
                order: [[1, 'desc']],
                columns: [
                    { data: 'select', name: 'select', orderable: false, searchable: false, className: 'text-center' },
                    { data: 'ref', name: 'ref' },
                    { data: 'estimate_date', name: 'estimate_date', searchable: false },
                    { data: 'customer', name: 'customer' },
                    { data: 'contact', name: 'contact' },
                    { data: 'order_ref', name: 'order_ref', orderable: false, searchable: false },
                    { data: 'fine', name: 'fine', orderable: false, searchable: false, className: 'text-end' },
                    { data: 'value', name: 'value', orderable: false, searchable: false, className: 'text-end' },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                ]
            });

            function refreshControls() {
                $('#selected-count').text(selected.size);
                $('#print-selected').prop('disabled', selected.size === 0);
                $('#clear-selection').toggleClass('d-none', selected.size === 0);

                const boxes = $('#estimates-table tbody .estimate-check');
                $('#check-page').prop('checked', boxes.length > 0 && boxes.filter(':checked').length === boxes.length);
            }

            table.on('draw', function () {
                $('#estimates-table tbody .estimate-check').each(function () {
                    $(this).prop('checked', selected.has($(this).val()));
                });
                refreshControls();
            });

            $(document).on('change', '.estimate-check', function () {
                this.checked ? selected.add(this.value) : selected.delete(this.value);
                refreshControls();
            });

            $('#check-page').on('change', function () {
                const on = this.checked;
                $('#estimates-table tbody .estimate-check').each(function () {
                    $(this).prop('checked', on);
                    on ? selected.add(this.value) : selected.delete(this.value);
                });
                refreshControls();
            });

            $('#clear-selection').on('click', function () {
                selected.clear();
                $('#estimates-table tbody .estimate-check').prop('checked', false);
                refreshControls();
            });

            function submitPrint(ids) {
                const $box = $('#print-ids').empty();
                ids.forEach(id => $box.append($('<input type="hidden" name="ids[]">').val(id)));
                $('#print-form').trigger('submit');
            }

            $('#print-selected').on('click', () => submitPrint([...selected]));
            $(document).on('click', '.print-one', function () { submitPrint([$(this).data('id')]); });

            @if (session('printAfterSave'))
                submitPrint(['{{ session('printAfterSave') }}']);
            @endif
        });
    </script>
@endpush
