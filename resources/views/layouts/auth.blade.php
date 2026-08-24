<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8" />
    <title>@yield('title', 'Log In') | {{ $appSettings->app_name }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="shortcut icon" href="{{ asset('theme/assets/images/favicon.ico') }}">

    <script src="{{ asset('theme/assets/js/config.js') }}"></script>

    <link href="{{ asset('theme/assets/css/app.min.css') }}" rel="stylesheet" type="text/css" id="app-style" />
    <link href="{{ asset('theme/assets/css/icons.min.css') }}" rel="stylesheet" type="text/css" />

    <style>
        .authentication-bg {
            {!! $appSettings->sidebarUserCss() !!}
        }
    </style>

</head>

<body class="authentication-bg position-relative">
    
    <div class="account-pages pt-2 pt-sm-5 pb-4 pb-sm-5 position-relative">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xxl-4 col-lg-5">
                    <div class="card">

                        <div class="card-header pt-4 text-center">
                            <div class="auth-brand mb-0">
                                <a href="{{ url('/') }}" class="logo-dark">
                                    <span><img src="{{ $appSettings->logoUrl('logo_dark_path') }}"
                                            alt="{{ $appSettings->app_name }}" height="28"></span>
                                </a>
                                <a href="{{ url('/') }}" class="logo-light">
                                    <span><img src="{{ $appSettings->logoUrl('logo_path') }}"
                                            alt="{{ $appSettings->app_name }}" height="28"></span>
                                </a>
                            </div>
                        </div>

                        <div class="card-body p-4">
                            @yield('content')
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer footer-alt">
        <span class="text-white-50">{{ date('Y') }} &copy; {{ $appSettings->app_name }}</span>
    </footer>

    <script src="{{ asset('theme/assets/js/vendor.min.js') }}"></script>
    <script src="{{ asset('theme/assets/js/app.min.js') }}"></script>
</body>

</html>
