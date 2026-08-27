{{--
    The "simple" landing page: light, plain, no motion.

    The same content as the fancy layout, in the same shared partials, wrapped in
    quieter chrome — a white ground, sections alternating with a faint tint, one
    accent colour and bordered cards. For a shop that wants the information and not
    the show, and it prints and screenshots far better.

    Standalone on purpose: it extends no layout and pulls none of the Jidox admin
    bundle.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $settings->firm_name ?: $settings->app_name }}</title>
    <meta name="description" content="Today's rates, bank details and contact information.">

    <link rel="shortcut icon" href="{{ asset('theme/assets/images/favicon.ico') }}">
    <link href="{{ asset('theme/assets/css/icons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/landing-simple.css') }}" rel="stylesheet">
</head>

<body>
    <header class="site-header">
        <div class="shell header-inner">
            <a class="brand" href="{{ url('/') }}">
                @if ($settings->hasCustomLogo('logo_dark_path') || $settings->hasCustomLogo('logo_path'))
                    {{-- The light ground wants the dark logo; fall back to the other
                         slot rather than the theme placeholder. --}}
                    <img src="{{ $settings->logoUrl($settings->hasCustomLogo('logo_dark_path') ? 'logo_dark_path' : 'logo_path') }}"
                        alt="{{ $settings->firm_name ?: $settings->app_name }}">
                @else
                    <span class="brand-text">{{ $settings->firm_name ?: $settings->app_name }}</span>
                @endif
            </a>

            <div class="header-right">
                @include('landing.partials._socials')

                @if (($primary = $settings->landingPhones()[0] ?? null))
                    <a class="btn-glow" href="tel:{{ preg_replace('/[^0-9+]/', '', $primary) }}">
                        <i class="ri-phone-fill" aria-hidden="true"></i> Call Us
                    </a>
                @endif
            </div>
        </div>
    </header>

    @if (filled($settings->landing_announcement))
        <div class="ribbon">
            <div class="shell ribbon-inner">
                <i class="ri-megaphone-line" aria-hidden="true"></i>
                <span>{{ $settings->landing_announcement }}</span>
            </div>
        </div>
    @endif

    <main>
        <section class="section hero-section">
            <div class="shell">
                <span class="sec-label">Live Rates</span>

                @if (filled($settings->firm_name))
                    <h1 class="hero-h1">{{ $settings->firm_name }}</h1>
                @endif

                <p class="hero-p">Today's Rate <b>&middot; {{ now()->format('d-m-Y, h:i A') }}</b></p>

                @include('landing.partials._rates')
            </div>
        </section>

        @php($bank = $settings->bankRows())
        @php($qr = $settings->paymentQrUrl())

        @if ($bank !== [] || $qr)
            <section class="section section--tint">
                <div class="shell">
                    <div class="section-head">
                        <span class="sec-label">Payments</span>
                        <h2 class="sec-h">How to Pay Us</h2>
                    </div>

                    @include('landing.partials._payments')
                </div>
            </section>
        @endif

        @if ($settings->landingPhones() !== [])
            <section class="section">
                <div class="shell">
                    <div class="section-head">
                        <span class="sec-label">Get in Touch</span>
                        <h2 class="sec-h">Touch to Call</h2>
                    </div>

                    @include('landing.partials._calls')
                </div>
            </section>
        @endif
    </main>

    <footer class="site-footer">
        <div class="shell footer-inner">
            <p class="footer-name">&copy; {{ now()->year }} {{ $settings->firm_name ?: $settings->app_name }}</p>

            @if (filled($settings->firm_address))
                <p class="address">
                    <i class="ri-map-pin-line" aria-hidden="true"></i> {{ $settings->firm_address }}
                </p>
            @endif
        </div>
    </footer>
</body>

</html>
