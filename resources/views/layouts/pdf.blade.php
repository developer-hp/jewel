<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    {{--
        Inlined rather than linked: dompdf ships with enable_remote => false, so an
        asset() URL (http://…) is silently dropped and the document renders unstyled.
        public/css/pdf.css stays the one place to edit these utilities.
    --}}
    <style type="text/css">
        {!! file_get_contents(public_path('css/pdf.css')) !!}

        /* The standard page margin for every document built on this layout, leaving
           180mm of content on A4 portrait. Note dompdf's own default is 12mm, so a
           rule of 12mm here would look like no margin at all.

           Set on @page rather than on a wrapping element — padding on a div is what
           put the item tag onto a second page. Override from @section('styles'). */
        

        /* Deliberately NOT `html { margin: 0 }` — dompdf folds the root element's
           style into the page style, so zeroing it wipes the @page margin above. */
        body {
            margin: 0;
            padding: 0;
        }

        body {
            /* DejaVu carries the glyphs the core PDF fonts lack — the rupee sign
               among them — at the cost of embedding the font in every document. */
            font-family: 'DejaVuSansCondensed', sans-serif;
        }
    </style>

    @yield('styles')
</head>
<body>
@yield('content')
</body>
</html>
