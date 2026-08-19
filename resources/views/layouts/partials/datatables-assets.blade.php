{{--
    Server-side DataTables assets shipped with the Jidox theme (DataTables 1.x + Bootstrap 5 styling).
    Include this from any listing page via @include, inside the css/js stacks.
--}}

@push('css')
    <link href="{{ asset('theme/assets/vendor/datatables.net-bs5/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet"
        type="text/css" />
    <link href="{{ asset('theme/assets/vendor/datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css') }}"
        rel="stylesheet" type="text/css" />
@endpush

@push('js')
    <script src="{{ asset('theme/assets/vendor/datatables.net/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('theme/assets/vendor/datatables.net-bs5/js/dataTables.bootstrap5.min.js') }}"></script>
    <script src="{{ asset('theme/assets/vendor/datatables.net-responsive/js/dataTables.responsive.min.js') }}"></script>
    <script src="{{ asset('theme/assets/vendor/datatables.net-responsive-bs5/js/responsive.bootstrap5.min.js') }}"></script>

    <script>
        // Shared defaults for every server-side listing in the app.
        window.appDataTable = function (selector, options) {
            return $(selector).DataTable($.extend(true, {
                processing: true,
                serverSide: true,
                responsive: true,
                pageLength: 15,
                lengthMenu: [10, 15, 25, 50, 100],
                order: [],
                language: {
                    paginate: { previous: '<i class="ri-arrow-left-s-line"></i>', next: '<i class="ri-arrow-right-s-line"></i>' },
                    processing: '<span class="spinner-border spinner-border-sm text-primary"></span>',
                    emptyTable: 'No records found.',
                    zeroRecords: 'No matching records found.'
                },
                dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                     "<'row'<'col-sm-12'tr>>" +
                     "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>"
            }, options || {}));
        };

        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });
    </script>
@endpush
