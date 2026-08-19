<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8" />
    <title>@yield('title', 'Dashboard') | {{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="shortcut icon" href="{{ asset('theme/assets/images/favicon.ico') }}">

    {{-- Theme Config Js must load before the stylesheets (sets the saved colour mode) --}}
    <script src="{{ asset('theme/assets/js/config.js') }}"></script>

    <link href="{{ asset('theme/assets/css/app.min.css') }}" rel="stylesheet" type="text/css" id="app-style" />
    <link href="{{ asset('theme/assets/css/icons.min.css') }}" rel="stylesheet" type="text/css" />

    @stack('css')
</head>

<body>
    <div class="wrapper">

        @include('layouts.partials.topbar')
        @include('layouts.partials.sidebar')

        <div class="content-page">
            <div class="content">
                <div class="container-fluid">

                    @include('layouts.partials.alerts')

                    @yield('content')

                </div>
            </div>

            @include('layouts.partials.footer')
        </div>
    </div>

    <script src="{{ asset('theme/assets/js/vendor.min.js') }}"></script>
    <script src="{{ asset('theme/assets/js/app.min.js') }}"></script>

    @stack('js')
</body>

</html>
