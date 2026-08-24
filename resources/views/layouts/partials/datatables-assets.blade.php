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
            const $table = $(selector);

            // Bordered + compact styling is global (see css/app-custom.css); these
            // classes only cover what DataTables itself keys off.
            $table.addClass('table-bordered');

            const table = $table.DataTable($.extend(true, {
                processing: true,
                serverSide: true,
                responsive: true,
                pageLength: 15,
                lengthMenu: [10, 15, 25, 50, 100],
                order: [],
                language: {
                    // The Bootstrap 5 integration wraps these controls in labels with
                    // literal "Show _MENU_ entries" and "Search:" text, which reads
                    // badly in a compact toolbar. Trimmed to the control itself.
                    lengthMenu: '_MENU_',
                    search: '',
                    info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                    infoEmpty: 'Showing 0 entries',
                    infoFiltered: '(filtered from _MAX_ total)',
                    paginate: { previous: '<i class="ri-arrow-left-s-line"></i>', next: '<i class="ri-arrow-right-s-line"></i>' },
                    processing: '<span class="spinner-border spinner-border-sm text-primary"></span>',
                    emptyTable: 'No records found.',
                    zeroRecords: 'No matching records found.'
                },
                dom: "<'dt-toolbar row g-2 align-items-center'<'col-auto'l><'col'f>>" +
                     "<'row'<'col-12'tr>>" +
                     "<'dt-footer row g-2 align-items-center'<'col-sm-auto'i><'col'p>>"
            }, options || {}));

            // DataTables 1.11.4 has no language.searchPlaceholder, so the empty
            // search label is given its placeholder here instead.
            $table.closest('.dataTables_wrapper')
                .find('.dataTables_filter input')
                .attr('placeholder', (options && options.searchPlaceholder) || 'Search…')
                .addClass('form-control');

            // Registered so an ajax delete can put every listing back without a
            // page reload; see layouts/partials/ui-feedback.
            window.appTables = window.appTables || [];
            window.appTables.push(table);

            return table;
        };

        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });
    </script>
@endpush
