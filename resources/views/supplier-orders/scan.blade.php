@extends('layouts.app')

@section('title', 'Scan to Close Order')

@push('js')
    {{-- Vendored into public/js rather than a CDN: a shop till should not need the
         internet to close an order. --}}
    <script src="{{ asset('js/html5-qrcode.min.js') }}"></script>
@endpush

@section('content')
    <x-page-title title="Scan to Close Order">
        <x-slot:actions>
            <a href="{{ route('supplier-orders.index') }}" class="btn btn-light">
                <i class="ri-arrow-left-line"></i> Back to orders
            </a>
        </x-slot:actions>
    </x-page-title>

    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header py-2">
                    <h5 class="mb-0">Camera</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted fs-13">
                        Hold the office copy up to the camera. The order is removed the moment its
                        code is read — there is nothing to press. A wrong scan is undone from the
                        list beside this.
                    </p>

                    <div id="scan-reader" class="border rounded"></div>

                    <div id="scan-camera-error" class="alert alert-warning mt-3 d-none fs-13"></div>

                    <hr>

                    {{-- A keyboard-wedge scanner types the code and sends Enter, which
                         submits this. Same endpoint as the camera. --}}
                    <label for="scan-manual" class="form-label">Or scan with a handheld reader</label>
                    <input type="text" id="scan-manual" class="form-control" autocomplete="off"
                        placeholder="The code lands here…" autofocus>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header py-2">
                    <h5 class="mb-0">Closed in this session</h5>
                </div>
                <div class="card-body">
                    <div id="scan-empty" class="text-muted fs-13">Nothing scanned yet.</div>
                    <ul class="list-group list-group-flush" id="scan-log"></ul>
                </div>
            </div>
        </div>
    </div>

    {{-- One form per Undo, posted normally so the page reloads with the flash. --}}
    <form method="POST" id="undo-form" class="d-none">@csrf</form>
@endsection

@push('js')
    <script>
        $(function () {
            const endpoint = '{{ route('supplier-orders.scan.destroy') }}';

            // A scanner can fire the same code several times a second while the slip
            // is in frame; without this the second read would report "no such order"
            // for one already gone.
            const seen = new Set();
            let busy = false;

            function log(ok, message, undoUrl) {
                $('#scan-empty').addClass('d-none');

                const $row = $('<li class="list-group-item d-flex justify-content-between align-items-center">')
                    .append($('<span>').addClass(ok ? '' : 'text-danger').text(message));

                if (undoUrl) {
                    $row.append(
                        $('<button type="button" class="btn btn-sm btn-link">Undo</button>')
                            .on('click', function () {
                                $('#undo-form').attr('action', undoUrl).trigger('submit');
                            })
                    );
                }

                $('#scan-log').prepend($row);
            }

            function submit(token) {
                token = (token || '').trim();

                if (!token || busy || seen.has(token)) {
                    return;
                }

                busy = true;
                seen.add(token);

                $.post(endpoint, { token: token })
                    .done(response => log(true, response.message, response.undo_url))
                    .fail(function (xhr) {
                        // Let a rejected code be tried again — it may have been misread.
                        seen.delete(token);
                        log(false, (xhr.responseJSON || {}).message || 'That code was not recognised.');
                    })
                    .always(() => { busy = false; });
            }

            $('#scan-manual').on('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    submit($(this).val());
                    $(this).val('');
                }
            });

            // The camera needs a secure context; over plain http the browser never
            // prompts, so say so rather than leaving an empty box.
            if (!window.isSecureContext) {
                $('#scan-camera-error')
                    .removeClass('d-none')
                    .text('The camera needs https. Use the handheld reader box below, or open this page over https.');

                return;
            }

            const reader = new Html5Qrcode('scan-reader');

            reader.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 240, height: 240 } },
                decoded => submit(decoded),
                () => {} // Fires constantly for every frame without a code; ignore.
            ).catch(function (error) {
                $('#scan-camera-error')
                    .removeClass('d-none')
                    .text('Could not start the camera: ' + error + ' Use the handheld reader box below.');
            });
        });
    </script>
@endpush
