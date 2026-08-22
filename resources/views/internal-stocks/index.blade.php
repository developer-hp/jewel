@extends('layouts.app')

@section('title', 'Internal Stock Master')

@include('layouts.partials.datatables-assets')

@section('content')
    <x-page-title title="Internal Stock Master">
        <x-slot:actions>
            @can('internal_stock.create')
                <a href="{{ route('internal-stocks.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Add
                </a>
            @endcan
        </x-slot:actions>
    </x-page-title>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted fs-13">
                        The pots the shop keeps gold in. What each holds is worked out from its
                        entries under <strong>Stock &rsaquo; Internal Stock</strong>, so a pot with
                        movements against it cannot be removed.
                    </p>

                    <table id="internal-stocks-table" class="table table-centered table-hover dt-responsive nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Reset Stock on Opening</th>
                                <th>Sort Order</th>
                                <th>Entries</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script>
        $(function () {
            window.appDataTable('#internal-stocks-table', {
                ajax: '{{ route('internal-stocks.index') }}',
                order: [[2, 'asc']],
                columns: [
                    { data: 'name', name: 'name' },
                    { data: 'reset', name: 'reset', searchable: false, className: 'text-center' },
                    { data: 'sort_order', name: 'sort_order', searchable: false },
                    { data: 'entries_count', name: 'entries_count', searchable: false },
                    { data: 'status', name: 'status', searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                ]
            });

            // Saves on the spot; the row is not reloaded, so a failure has to say so
            // rather than leaving a radio showing something that was never stored.
            $(document).on('change', '.reset-toggle input[type=radio]', function () {
                const $group = $(this).closest('.reset-toggle');
                const chosen = $(this).val();

                $.post($group.data('url'), { reset_on_opening: chosen })
                    .fail(function () {
                        window.alert('That could not be saved. Reload and try again.');
                        $group.find('input[value="' + (chosen === '1' ? '0' : '1') + '"]').prop('checked', true);
                    });
            });
        });
    </script>
@endpush
