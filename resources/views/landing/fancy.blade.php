{{--
    The "fancy" landing page: dark, purple-tinted, animated.

    Laid out after the talent-ai.io reference — near-black ground, drifting colour
    blobs behind a faint grid, glass cards with a hairline border, a pill label above
    every section heading — with that page's own class names and variables kept so the
    two can be compared side by side.

    Standalone on purpose: it extends no layout and pulls none of the Jidox admin
    bundle. A customer loading this should not be downloading app.min.css, jQuery and
    the sidebar.

    The content blocks come from landing/partials, shared with the simple layout, so a
    field added on the Appearance screen appears on both looks.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $settings->firm_name ?: $settings->app_name }}</title>
    <meta name="description" content="Today's rates, bank details and contact information.">

    <link rel="shortcut icon" href="{{ asset('theme/assets/images/favicon.ico') }}">

    {{-- Inter is the reference's typeface. Preconnected and linked, but every rule
         falls back to the system stack, so a counter with no internet still gets a
         page that renders instantly rather than one that waits. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">

    <link href="{{ asset('theme/assets/css/icons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/landing-fancy.css') }}" rel="stylesheet">
</head>

<body>
    <nav class="ta-nav" id="nav">
        <div class="shell nav-inner">
            <a class="brand" href="{{ url('/') }}">
                @if ($settings->hasCustomLogo('logo_path'))
                    <img src="{{ $settings->logoUrl('logo_path') }}"
                        alt="{{ $settings->firm_name ?: $settings->app_name }}">
                @else
                    {{-- No logo uploaded: a gradient wordmark reads far better on this
                         ground than the bundled theme placeholder. --}}
                    <span class="brand-text grad-text">{{ $settings->firm_name ?: $settings->app_name }}</span>
                @endif
            </a>

            <div class="nav-right">
                @include('landing.partials._socials')

                @if (($primary = $settings->landingPhones()[0] ?? null))
                    <a class="btn-glow" href="tel:{{ preg_replace('/[^0-9+]/', '', $primary) }}">
                        <i class="ri-phone-fill" aria-hidden="true"></i> Call Us
                    </a>
                @endif
            </div>
        </div>
    </nav>

    <header class="hero">
        <div class="hero-grid" aria-hidden="true"></div>
        <div class="hero-glow" aria-hidden="true"></div>
        <div class="blob blob-1" aria-hidden="true"></div>
        <div class="blob blob-2" aria-hidden="true"></div>
        <div class="blob blob-3" aria-hidden="true"></div>

        <div class="shell hero-inner">
            <p class="hero-pill">
                <span class="dot" aria-hidden="true"></span>
                @if (filled($settings->landing_announcement))
                    <i class="ri-megaphone-line" aria-hidden="true"></i>
                    {{ $settings->landing_announcement }}
                @else
                    Updated {{ now()->format('d M Y, h:i A') }}
                @endif
            </p>

            <h1 class="hero-h1"><span class="grad-text">Today's Rate</span></h1>

            <p class="hero-p">
                Live gold and silver rates, straight from the counter.
                <b>{{ now()->format('d M Y, h:i A') }}</b>
            </p>

            @include('landing.partials._rates')
        </div>
    </header>

    <main>
        @php($bank = $settings->bankRows())
        @php($qr = $settings->paymentQrUrl())

        @if ($bank !== [] || $qr)
            <section class="section section--d1">
                <div class="shell">
                    <div class="section-head sr">
                        <span class="sec-label">Payments</span>
                        <h2 class="sec-h">How to Pay Us</h2>
                        <p class="sec-p">Transfer to the account below, or scan the code with any UPI app.</p>
                    </div>

                    @include('landing.partials._payments')
                </div>
            </section>
        @endif

        @if ($settings->landingPhones() !== [])
            <section class="section section--d0">
                <div class="shell">
                    <div class="section-head sr">
                        <span class="sec-label">Get in Touch</span>
                        <h2 class="sec-h">Touch to Call</h2>
                        <p class="sec-p">Tap a number to ring the shop.</p>
                    </div>

                    @include('landing.partials._calls')
                </div>
            </section>
        @endif
    </main>

    <footer class="site-footer">
        <div class="shell">
            <div class="footer-top">
                <div>
                    <p class="footer-name grad-text">{{ $settings->firm_name ?: $settings->app_name }}</p>
                    <p class="quiet">Rates shown are for today only and change daily.</p>
                </div>

                @if (filled($settings->firm_address))
                    <p class="address">
                        <i class="ri-map-pin-line" aria-hidden="true"></i> {{ $settings->firm_address }}
                    </p>
                @endif
            </div>

            <div class="fbot">
                <p>&copy; {{ now()->year }} {{ $settings->firm_name ?: $settings->app_name }}</p>
            </div>
        </div>
    </footer>

    <script>
        // Three small behaviours from the reference, hand-rolled: no library is worth
        // loading on a public page for this much.
        (function () {
            var nav = document.getElementById('nav');

            function onScroll() {
                nav.classList.toggle('stuck', window.scrollY > 10);
            }

            window.addEventListener('scroll', onScroll, { passive: true });
            onScroll();

            // The blobs fade in rather than appearing mid-drift on first paint.
            requestAnimationFrame(function () {
                document.querySelectorAll('.blob').forEach(function (b) { b.classList.add('on'); });
            });

            var reveal = document.querySelectorAll('.sr');

            // Without IntersectionObserver everything is simply shown — the reveal is
            // decoration, and a page stuck at opacity 0 is not.
            if (!('IntersectionObserver' in window)) {
                reveal.forEach(function (el) { el.classList.add('on'); });
                return;
            }

            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('on');
                        io.unobserve(entry.target);
                    }
                });
            }, { rootMargin: '0px 0px -60px 0px' });

            reveal.forEach(function (el) { io.observe(el); });
        })();
    </script>
</body>

</html>
