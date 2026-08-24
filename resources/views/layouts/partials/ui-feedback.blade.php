{{--
    Toasts and the delete confirmation, shared by every screen.

    Toasts come from the theme's own jquery-toast-plugin (the one ui-notifications.html
    demonstrates), so nothing new is introduced for them.

    The confirmation is SweetAlert2, served locally like every other library here rather
    than from a CDN — a dialog that silently stops appearing because a third party is
    unreachable is not a dialog you can put in front of a delete.
--}}

@push('css')
    <link href="{{ asset('theme/assets/vendor/jquery-toast-plugin/jquery.toast.min.css') }}" rel="stylesheet"
        type="text/css" />
@endpush

@push('js')
    <script src="{{ asset('theme/assets/vendor/jquery-toast-plugin/jquery.toast.min.js') }}"></script>
    <script src="{{ asset('theme/assets/vendor/sweetalert2/sweetalert2.all.min.js') }}"></script>

    <script>
        (function ($) {
            'use strict';

            // --- toasts --------------------------------------------------------
            // $.NotificationApp comes from the theme's demo.toastr.js, which is not
            // loaded on our pages, so the plugin is driven directly.
            window.appToast = function (type, message, heading) {
                if (!message || typeof $.toast !== 'function') {
                    return;
                }

                $.toast().reset('all');
                $.toast({
                    heading: heading || (type === 'error' ? 'Sorry' : 'Done'),
                    text: message,
                    position: 'top-right',
                    loaderBg: 'rgba(0,0,0,0.2)',
                    icon: type === 'error' ? 'error' : (type === 'warning' ? 'warning' : 'success'),
                    hideAfter: type === 'error' ? 6000 : 3500,
                    stack: 3,
                    showHideTransition: 'fade',
                });
            };

            // Anything the last request flashed.
            @if (session('success'))
                $(function () { window.appToast('success', @js(session('success'))); });
            @endif
            @if (session('error'))
                $(function () { window.appToast('error', @js(session('error'))); });
            @endif

            // --- tables to refresh after a delete -------------------------------
            // appDataTable registers every listing it builds, so a delete can put the
            // table back without reloading the page.
            window.appTables = window.appTables || [];

            window.appReloadTables = function () {
                window.appTables.forEach(function (table) {
                    // Keep the current page rather than jumping back to the first.
                    table.ajax.reload(null, false);
                });
            };

            // --- delete ---------------------------------------------------------
            function confirmThenDelete($trigger) {
                var url = $trigger.data('delete-url');
                // Each listing words its own question — "Delete this stone?",
                // "Delete order CF 159?" — so it is carried, not composed here.
                var question = $trigger.data('delete-confirm') || 'Delete this record?';

                if (!url) {
                    return;
                }

                // Without the library there is no confirmation to show, and deleting
                // on a bare click would be worse than doing nothing.
                if (typeof window.Swal === 'undefined') {
                    console.error('SweetAlert2 is not loaded — delete cancelled.');
                    window.appToast('error', 'Cannot confirm this delete. SweetAlert2 is missing.');

                    return;
                }

                window.Swal.fire({
                    title: question,
                    text: 'This cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete it',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#f1556c',
                    reverseButtons: true,
                }).then(function (result) {
                    if (!result.isConfirmed) {
                        return;
                    }

                    $trigger.prop('disabled', true);

                    $.ajax({
                        url: url,
                        method: 'POST',
                        // Laravel reads the verb from here; a bare DELETE would be
                        // turned away by some hosts.
                        data: { _method: 'DELETE' },
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        },
                    }).done(function (response) {
                        window.appToast('success', (response && response.message) || 'Deleted.');
                        window.appReloadTables();
                    }).fail(function (xhr) {
                        var body = xhr.responseJSON || {};

                        // A refusal — "still in use", "already in stock" — is an answer,
                        // not a crash, so it reads as a message rather than a failure.
                        window.appToast('error', body.message || 'That could not be deleted.');

                        // The row may have changed underneath; show what is really there.
                        window.appReloadTables();
                    }).always(function () {
                        $trigger.prop('disabled', false);
                    });
                });
            }

            $(document).on('click', '[data-delete-url]', function (e) {
                e.preventDefault();
                confirmThenDelete($(this));
            });
        })(window.jQuery);
    </script>
@endpush
