<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    {{--
        pdf.css is read off disk and inlined, never linked. A <link> points at an
        http:// URL, which means the PDF renderer has to go back out over the
        network to fetch its own stylesheet — it fails quietly when it cannot, and
        the document then prints with no borders at all. Reading the file is also
        simply faster. public/css/pdf.css stays the one place to edit these
        utilities.
    --}}
    <style type="text/css">
        {!! file_get_contents(public_path('css/pdf.css')) !!}
    </style>

    <style type="text/css">
        body {
            margin: 0;
            padding: 0;
            /* DejaVu carries glyphs the core PDF fonts lack — the rupee sign among
               them — at the cost of embedding the font in every document. */
            font-family: 'DejaVuSansCondensed', sans-serif;
        }
    </style>

    @yield('styles')
</head>
<body>
@yield('content')
</body>
</html>
