@extends('layouts.app')

@section('title', 'Activity Log')

@include('layouts.partials.datatables-assets')

@section('content')
    <x-page-title title="Activity Log">
        <x-slot:actions>
            @can('activity_log.delete')
                <button type="button" class="btn btn-soft-danger" data-bs-toggle="modal" data-bs-target="#pruneModal">
                    <i class="ri-delete-bin-line"></i> Prune
                </button>
            @endcan
        </x-slot:actions>
    </x-page-title>

    @if ($pending > 0)
        {{-- Buffered rows are not in the table yet. Opening this screen flushes a
             couple of batches; a bigger backlog is the scheduled command's job, and
             saying so is better than showing a list that quietly stops short. --}}
        <div class="alert alert-info">
            <i class="ri-information-line"></i>
            {{ number_format($pending) }} row(s) are still buffered and will appear once
            <code>activity:flush</code> next runs.
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <div class="row g-2 mb-3">
                        <div class="col-md-2">
                            <select id="filter-log" class="form-select">
                                <option value="">All types</option>
                                @foreach ($logs as $key => [$label, $class])
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select id="filter-causer" class="form-select">
                                <option value="">All users</option>
                                @foreach ($users as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="date" id="filter-from" class="form-control" aria-label="From">
                        </div>
                        <div class="col-md-2">
                            <input type="date" id="filter-to" class="form-control" aria-label="To">
                        </div>
                        <div class="col-md-auto">
                            <button type="button" class="btn btn-link text-muted" id="clear-filters">Clear</button>
                        </div>
                    </div>

                    <table id="activity-table" class="table table-centered table-hover dt-responsive nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 14%">When</th>
                                <th style="width: 12%">User</th>
                                <th style="width: 8%">Type</th>
                                <th style="width: 8%">Action</th>
                                <th style="width: 14%">Subject</th>
                                <th>Summary</th>
                                <th class="text-end" style="width: 6%">View</th>
                            </tr>
                        </thead>
                    </table>

                </div>
            </div>
        </div>
    </div>

    {{-- Detail --}}
    <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Activity</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="detail-body">
                    <p class="text-muted mb-0">Loading…</p>
                </div>
            </div>
        </div>
    </div>

    @can('activity_log.delete')
        <div class="modal fade" id="pruneModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('activity-log.prune') }}"
                    data-confirm="Delete every activity row before the date chosen? This cannot be undone.">
                    @csrf
                    @method('DELETE')
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Prune the log</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted fs-13">
                                Nothing is deleted automatically &mdash; the log is kept until somebody
                                says otherwise. Everything recorded <strong>before</strong> this date goes.
                            </p>
                            <label for="before" class="form-label">Delete rows before</label>
                            <input type="date" id="before" name="before" class="form-control"
                                max="{{ now()->toDateString() }}" value="{{ now()->subYear()->toDateString() }}" required>
                            @error('before')
                                <div class="text-danger fs-13 mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Delete</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endcan
@endsection

@push('js')
    <script>
        $(function () {
            const table = window.appDataTable('#activity-table', {
                ajax: {
                    url: '{{ route('activity-log.index') }}',
                    data: function (params) {
                        params.log = $('#filter-log').val();
                        params.causer = $('#filter-causer').val();
                        params.from = $('#filter-from').val();
                        params.to = $('#filter-to').val();
                    }
                },
                order: [[0, 'desc']],
                columns: [
                    { data: 'created_at', name: 'created_at' },
                    { data: 'user', name: 'user' },
                    { data: 'type', name: 'type' },
                    { data: 'event', name: 'event' },
                    { data: 'subject', name: 'subject', orderable: false, searchable: false },
                    { data: 'summary', name: 'summary' },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                ]
            });

            $('#filter-log, #filter-causer, #filter-from, #filter-to').on('change', () => table.ajax.reload());

            $('#clear-filters').on('click', function () {
                $('#filter-log, #filter-causer, #filter-from, #filter-to').val('');
                table.ajax.reload();
            });

            $(document).on('click', '.activity-detail', function () {
                const $body = $('#detail-body').html('<p class="text-muted mb-0">Loading…</p>');

                $.get($(this).data('url'))
                    .done(html => $body.html(html))
                    .fail(() => $body.html('<p class="text-danger mb-0">Could not load that row.</p>'));
            });
        });
    </script>
@endpush
