<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8" />
    <title>@yield('title', 'Dashboard') | {{ $appSettings->app_name }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="shortcut icon" href="{{ asset('theme/assets/images/favicon.ico') }}">

    {{-- Theme Config Js must load before the stylesheets (sets the saved colour mode) --}}
    <script src="{{ asset('theme/assets/js/config.js') }}"></script>

    <link href="{{ asset('theme/assets/css/app.min.css') }}" rel="stylesheet" type="text/css" id="app-style" />
    <link href="{{ asset('theme/assets/css/icons.min.css') }}" rel="stylesheet" type="text/css" />


    {{-- Branding: the theme paints the sidebar user panel with a photo, so this
         has to override background-image, not just the colour. --}}
    <style>
        {{-- Written as custom properties, not rules: this block sits above the
             app-custom.css link, so a plain rule here would lose to it. Variable
             lookup ignores source order. Values are validated hex. --}}
        :root {
            @foreach ($appSettings->cssVariables() as $name => $value)
                {{ $name }}: {{ $value }};
            @endforeach
        }

        .leftbar-user {
            {!! $appSettings->sidebarUserCss() !!}
        }

        .leftbar-user .text-reset,
        .leftbar-user span {
            color: {{ $appSettings->sidebar_user_text_color }} !important;
        }
    </style>

    {{-- Pushes onto both stacks, so it has to run before the first of them renders. --}}
    @include('layouts.partials.ui-feedback')

    @stack('css')

    {{-- Project overrides load last so they beat both the theme and the vendor
         DataTables stylesheet, which are equal in specificity. --}}
    <link href="{{ asset('css/app-custom.css') }}" rel="stylesheet" type="text/css" />
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

        @if ($appSettings->idleTimeoutEnabled())
            @include('layouts.partials.idle-timeout')
        @endif
        </div>
    </div>

    {{-- Ctrl+M. Below the page so its markup never sits inside anything, and it
         pushes only scripts, which the stack below still picks up. --}}
    @include('layouts.partials.command-palette')
    @include('layouts.partials.cash-calculator')

    <script src="{{ asset('theme/assets/js/vendor.min.js') }}"></script>
    <script src="{{ asset('theme/assets/js/app.min.js') }}"></script>

    @stack('js')
</body>

</html>
