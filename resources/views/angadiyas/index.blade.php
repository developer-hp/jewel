@extends('layouts.app')

@section('title', 'Angadiya')

@include('layouts.partials.datatables-assets')

@section('content')
    <x-page-title title="Angadiya">
        <x-slot:actions>
            @can('angadiya.create')
                <a href="{{ route('angadiyas.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> New Slip
                </a>
            @endcan
        </x-slot:actions>
    </x-page-title>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <div class="row g-2 mb-3 align-items-center">
                        <div class="col-md-2">
                            <select id="filter-printed" class="form-select">
                                <option value="">All slips</option>
                                <option value="no">Not printed</option>
                                <option value="yes">Printed</option>
                            </select>
                        </div>

                        @can('angadiya.print')
                            <div class="col-md-auto">
                                <button type="button" class="btn btn-primary" id="print-selected" disabled>
                                    <i class="ri-printer-line"></i> Print Selected (<span id="selected-count">0</span>)
                                </button>
                            </div>
                            <div class="col-md-auto">
                                <button type="button" class="btn btn-warning" id="print-list">
                                    <i class="ri-file-list-line"></i> Export
                                </button>
                            </div>
                            <div class="col-md-auto">
                                <button type="button" class="btn btn-link text-muted d-none" id="clear-selection">
                                    Clear
                                </button>
                            </div>
                        @endcan
                    </div>

                    <table id="angadiyas-table" class="table table-centered table-hover dt-responsive nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 3%">
                                    <input type="checkbox" class="form-check-input" id="check-page"
                                        aria-label="Select all on this page">
                                </th>
                                <th>Date</th>
                                <th>Recipient</th>
                                <th class="text-end">Insurance</th>
                                <th>Remark</th>
                                <th>Printed</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                    </table>

                </div>
            </div>
        </div>
    </div>

    {{-- Posts the chosen ids and opens the sheet in a new tab; POST because it also
         stamps printed_at. --}}
    @can('angadiya.print')
        <form method="POST" action="{{ route('angadiyas.print') }}" target="_blank" id="print-form">
            @csrf
            <div id="print-ids"></div>
        </form>

        {{-- The despatch list. Same selection, different document — and unlike the
             slip sheet it does not stamp printed_at, so nothing needs reloading after. --}}
        <form method="POST" action="{{ route('angadiyas.print-list') }}" target="_blank" id="print-list-form">
            @csrf
            <div id="print-list-ids"></div>
        </form>
    @endcan
@endsection

@push('js')
    <script>
        $(function () {
            // The table is server-side, so ticked rows would vanish on paging. Hold
            // the ids here and re-apply them every draw.
            const selected = new Set();

            const table = window.appDataTable('#angadiyas-table', {
                ajax: {
                    url: '{{ route('angadiyas.index') }}',
                    data: function (params) {
                        params.printed = $('#filter-printed').val();
                    }
                },
                order: [[1, 'desc']],
                columns: [
                    { data: 'select', name: 'select', orderable: false, searchable: false, className: 'text-center' },
                    { data: 'created_at', name: 'created_at', searchable: false },
                    { data: 'recipient', name: 'recipient' },
                    { data: 'insurance_amount', name: 'insurance_amount', className: 'text-end' },
                    { data: 'remark', name: 'remark' },
                    { data: 'printed', name: 'printed', searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                ]
            });

            function refreshControls() {
                $('#selected-count').text(selected.size);
                $('#print-selected').prop('disabled', selected.size === 0);
                $('#clear-selection').toggleClass('d-none', selected.size === 0);

                const boxes = $('#angadiyas-table tbody .slip-check');
                $('#check-page').prop('checked', boxes.length > 0 && boxes.filter(':checked').length === boxes.length);
            }

            table.on('draw', function () {
                $('#angadiyas-table tbody .slip-check').each(function () {
                    $(this).prop('checked', selected.has($(this).val()));
                });
                refreshControls();
            });

            $(document).on('change', '.slip-check', function () {
                this.checked ? selected.add(this.value) : selected.delete(this.value);
                refreshControls();
            });

            $('#check-page').on('change', function () {
                const on = this.checked;
                $('#angadiyas-table tbody .slip-check').each(function () {
                    $(this).prop('checked', on);
                    on ? selected.add(this.value) : selected.delete(this.value);
                });
                refreshControls();
            });

            $('#clear-selection').on('click', function () {
                selected.clear();
                $('#angadiyas-table tbody .slip-check').prop('checked', false);
                refreshControls();
            });

            // Pull every unprinted id, not just the ones on this page.
            $('#select-unprinted').on('click', function () {
                $.getJSON('{{ route('angadiyas.index') }}', { printed: 'no', length: -1, draw: 1 })
                    .done(function (response) {
                        (response.data || []).forEach(row => selected.add(String(row.id)));
                        table.draw(false);
                    });
            });

            function submitPrint(ids) {
                const $box = $('#print-ids').empty();
                ids.forEach(id => $box.append($('<input type="hidden" name="ids[]">').val(id)));
                $('#print-form').trigger('submit');

                // The sheet marks them printed, so refresh the badges behind it.
                setTimeout(() => table.ajax.reload(null, false), 1200);
            }

            $('#print-selected').on('click', () => submitPrint([...selected]));

            $('#print-list').on('click', function () {
                $('#print-list-form').trigger('submit');
            });
            $(document).on('click', '.print-one', function () { submitPrint([$(this).data('id')]); });

            $('#filter-printed').on('change', () => table.ajax.reload());

            // "Save & Print" lands back here and prints the slip just saved.
            @if (session('printAfterSave'))
                submitPrint(['{{ session('printAfterSave') }}']);
            @endif
        });
    </script>
@endpush
