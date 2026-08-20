@extends('layouts.app')

@section('title', 'Security')

@section('content')
    <x-page-title title="Security" />

    <form method="POST" action="{{ route('security-settings.update') }}">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header py-2">
                        <h5 class="mb-0">Single Device Sign-in</h5>
                    </div>
                    <div class="card-body">
                        @unless ($driverSupported)
                            <div class="alert alert-warning">
                                <i class="ri-error-warning-line me-1"></i>
                                The session driver is <code>{{ $sessionDriver }}</code>. Single-device sign-in needs
                                <code>database</code> sessions to know which sessions belong to which user — set
                                <code>SESSION_DRIVER=database</code> in <code>.env</code>. The setting below has no
                                effect until then.
                            </div>
                        @endunless

                        <div class="form-check form-switch mb-2">
                            <input type="hidden" name="single_device_login" value="0">
                            <input class="form-check-input" type="checkbox" id="single_device_login"
                                name="single_device_login" value="1"
                                @checked(old('single_device_login', $settings->single_device_login))>
                            <label class="form-check-label" for="single_device_login">
                                Allow only one device signed in per user
                            </label>
                        </div>

                        <p class="text-muted fs-13 mb-0">
                            When someone signs in while their account is already active elsewhere, they are asked
                            whether to sign the other device out or abandon this sign-in. Several tabs in the same
                            browser count as one device.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header py-2">
                        <h5 class="mb-0">Idle Timeout</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="idle_timeout_minutes" class="form-label">
                                    Sign out after (minutes) <span class="text-danger">*</span>
                                </label>
                                <input type="number" id="idle_timeout_minutes" name="idle_timeout_minutes"
                                    class="form-control @error('idle_timeout_minutes') is-invalid @enderror"
                                    value="{{ old('idle_timeout_minutes', $settings->idle_timeout_minutes) }}"
                                    min="0" max="1440" required>
                                <small class="text-muted">0 turns idle sign-out off.</small>
                                @error('idle_timeout_minutes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="idle_warning_seconds" class="form-label">
                                    Warn this many seconds first <span class="text-danger">*</span>
                                </label>
                                <input type="number" id="idle_warning_seconds" name="idle_warning_seconds"
                                    class="form-control @error('idle_warning_seconds') is-invalid @enderror"
                                    value="{{ old('idle_warning_seconds', $settings->idle_warning_seconds) }}"
                                    min="10" max="600" required>
                                <small class="text-muted">A countdown appears with this long to go.</small>
                                @error('idle_warning_seconds')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <div class="alert alert-light border mb-0 fs-13" id="idle-summary"></div>
                            </div>
                        </div>

                        <p class="text-muted fs-13 mb-0">
                            Mouse movement, key presses, clicks, scrolling and touch all count as activity. The
                            countdown runs in the browser, and the server independently refuses to resume a session
                            that has been idle longer than the limit.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        @can('app_setting.edit')
            <div class="mb-4">
                <button type="submit" class="btn btn-primary">
                    <i class="ri-save-line"></i> Save Security Settings
                </button>
            </div>
        @endcan
    </form>
@endsection

@push('js')
    <script>
        $(function () {
            const sessionLifetime = {{ $sessionLifetime }};

            function refresh() {
                const minutes = parseInt($('#idle_timeout_minutes').val(), 10) || 0;
                const warning = parseInt($('#idle_warning_seconds').val(), 10) || 0;
                const box = $('#idle-summary');

                if (minutes <= 0) {
                    box.html('<i class="ri-information-line me-1"></i> Idle sign-out is <strong>off</strong>. ' +
                        'Sessions still expire after ' + sessionLifetime + ' minutes of no requests.');
                    return;
                }

                let html = '<i class="ri-time-line me-1"></i> Warning after <strong>' +
                    Math.max(0, (minutes * 60 - warning) / 60).toFixed(1) + ' min</strong> idle, ' +
                    'signed out at <strong>' + minutes + ' min</strong>.';

                if (minutes > sessionLifetime) {
                    html += '<div class="text-warning mt-1"><i class="ri-alert-line me-1"></i>' +
                        'Longer than SESSION_LIFETIME (' + sessionLifetime + ' min), so the session cookie ' +
                        'expires first and the timeout never fires.</div>';
                }

                box.html(html);
            }

            $('#idle_timeout_minutes, #idle_warning_seconds').on('input', refresh);
            refresh();
        });
    </script>
@endpush
