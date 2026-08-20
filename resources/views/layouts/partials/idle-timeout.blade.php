{{--
    Idle sign-out countdown. Included from layouts/app only when a timeout is set.

    This is the courtesy half of the feature: EnforceIdleTimeout on the server is
    what actually ends the session, so a paused laptop or a killed tab cannot
    extend anything. Here we warn the user before it happens and keep the server's
    idle clock in step with real activity.
--}}
@php($idleTimeout = $appSettings->idleTimeoutSeconds())
@php($idleWarning = $appSettings->idleWarningSeconds())

<div class="modal fade" id="idle-warning-modal" tabindex="-1" aria-labelledby="idle-warning-title"
    aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <i class="ri-time-line text-warning" style="font-size: 2.5rem;"></i>
                <h5 class="mt-2 mb-1" id="idle-warning-title">Still there?</h5>
                <p class="text-muted mb-3">
                    You will be signed out in
                    <strong class="text-danger" id="idle-countdown">{{ $idleWarning }}</strong> seconds.
                </p>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-primary" id="idle-stay">Stay signed in</button>
                    <button type="button" class="btn btn-link text-muted" id="idle-logout-now">Sign out now</button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('js')
    <script>
        (function () {
            const TIMEOUT_MS = {{ $idleTimeout }} * 1000;
            const WARNING_MS = {{ $idleWarning }} * 1000;
            const HEARTBEAT_MS = 60 * 1000;
            const HEARTBEAT_URL = @js(route('session.heartbeat'));

            let lastActivity = Date.now();
            let lastHeartbeat = Date.now();
            let activitySinceHeartbeat = false;
            let warningOpen = false;
            let signingOut = false;

            const modalEl = document.getElementById('idle-warning-modal');
            const modal = new bootstrap.Modal(modalEl);
            const countdownEl = document.getElementById('idle-countdown');

            function markActivity() {
                // Once the warning is up, only the buttons dismiss it. Otherwise a
                // stray mouse nudge would silently cancel it and the warning would
                // never be actionable.
                if (warningOpen || signingOut) {
                    return;
                }

                lastActivity = Date.now();
                activitySinceHeartbeat = true;
            }

            ['mousemove', 'mousedown', 'keydown', 'wheel', 'scroll', 'touchstart', 'click']
                .forEach(function (event) {
                    window.addEventListener(event, markActivity, { passive: true });
                });

            function resume() {
                warningOpen = false;
                lastActivity = Date.now();
                modal.hide();
                sendHeartbeat();
            }

            function signOut() {
                if (signingOut) {
                    return;
                }
                signingOut = true;
                document.getElementById('logout-form').submit();
            }

            function sendHeartbeat() {
                lastHeartbeat = Date.now();
                activitySinceHeartbeat = false;

                $.post(HEARTBEAT_URL).fail(function (xhr) {
                    // 401 means the server already ended it — follow it to login.
                    if (xhr.status === 401) {
                        window.location = (xhr.responseJSON && xhr.responseJSON.redirect) || '{{ route('login') }}';
                    }
                });
            }

            document.getElementById('idle-stay').addEventListener('click', resume);
            document.getElementById('idle-logout-now').addEventListener('click', signOut);

            setInterval(function () {
                if (signingOut) {
                    return;
                }

                const idle = Date.now() - lastActivity;

                if (idle >= TIMEOUT_MS) {
                    signOut();
                    return;
                }

                if (idle >= TIMEOUT_MS - WARNING_MS) {
                    if (!warningOpen) {
                        warningOpen = true;
                        modal.show();
                    }
                    countdownEl.textContent = Math.max(0, Math.ceil((TIMEOUT_MS - idle) / 1000));
                    return;
                }

                // Only ping when the user has actually done something, so an
                // abandoned tab never keeps its own session alive.
                if (activitySinceHeartbeat && Date.now() - lastHeartbeat >= HEARTBEAT_MS) {
                    sendHeartbeat();
                }
            }, 1000);
        })();
    </script>
@endpush
