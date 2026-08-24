{{--
    The tag shell. Which layout renders inside it is decided by ItemLabelBuilder,
    which hands over $view — this file makes no decisions of its own.

    dompdf constraints that everything below depends on:
      * Tables only. There is no flexbox and no grid.
      * Margins live on @page, not as padding on a wrapper: dompdf honours these
        exactly, whereas box-sizing on a padded div is unreliable and pushed the tag
        onto a second page.
      * Every <img> needs display:block — inline images carry a descender gap that
        also spilled the tag onto page two.
      * Helvetica is a PDF core font, so nothing is embedded and the tag stays a few
        KB instead of ~900 KB with DejaVu. It has no rupee glyph, which is why
        amounts print bare.
--}}
@php
    $font = (float) $settings->font_size_pt;
    $margin = (float) $settings->margin_mm;
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>{{ $code }}</title>
    <style>
        @page {
            margin: {{ $margin }}mm;
        }

        html,
        body {
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: {{ $font }}pt;
            line-height: 1.25;
            color: #000;
        }

        .tag {
            width: 100%;
        }

        table.layout {
            width: 100%;
            border-collapse: collapse;
        }

        table.layout > tr > td,
        table.layout td {
            vertical-align: middle;
            padding: 0;
        }

        .qr {
            text-align: right;
            width: {{ $qrSizeMm }}mm;
        }

        .qr img {
            width: {{ $qrSizeMm }}mm;
            height: {{ $qrSizeMm }}mm;
            display: block;
        }

        /* --- standard ------------------------------------------------------ */

        .std-identity {
            text-align: center;
            padding-right: 2mm;
            white-space: nowrap;
        }

        .std-identity .code {
            font-size: {{ $font + 3 }}pt;
            font-weight: bold;
        }

        .std-identity .net {
            font-size: {{ $font + 1 }}pt;
            font-weight: bold;
        }

        .shop {
            font-size: {{ max(4, $font - 1.5) }}pt;
            text-transform: uppercase;
            letter-spacing: 0.2pt;
        }

        table.fields {
            border-collapse: collapse;
        }

        table.fields td {
            padding: 0 1.5mm 0 0;
            white-space: nowrap;
            font-size: {{ $font }}pt;
        }

        table.fields td.k {
            font-weight: bold;
            padding-right: 0.6mm;
        }

        table.fields td.v {
            text-align: right;
            padding-right: 3mm;
        }

        /* --- stone detail --------------------------------------------------- */

        /* Fixed layout, or dompdf widens on the longest amount and pushes the
           right-hand block off the tag. */
        table.sd-lines {
            border-collapse: collapse;
            table-layout: fixed;
            width: 100%;
        }

        table.sd-lines td {
            padding: 0 1mm 0 0;
            white-space: nowrap;
            font-size: {{ $font }}pt;
        }

        table.sd-lines td.c {
            font-weight: bold;
            width: 22%;
        }

        table.sd-lines td.w {
            text-align: right;
            width: 22%;
        }

        table.sd-lines td.p {
            text-align: right;
            width: 15%;
        }

        table.sd-lines td.r {
            text-align: right;
            width: 18%;
        }

        table.sd-lines td.a {
            text-align: right;
            width: 23%;
        }

        .sd-right {
            text-align: center;
            padding-left: 2mm;
            width: 42%;
        }

        .sd-right .code {
            font-size: {{ $font + 4 }}pt;
            font-weight: bold;
        }

        .sd-right .line {
            font-size: {{ $font + 1 }}pt;
            font-weight: bold;
        }

        /* A long description has to wrap, not stretch the page box. */
        .sd-right .desc {
            font-size: {{ $font }}pt;
            font-weight: bold;
        }

        /* --- diamond detail -------------------------------------------------- */

        .dd-identity {
            padding-right: 2mm;
            white-space: nowrap;
        }

        .dd-identity .code {
            font-size: {{ $font + 4 }}pt;
            font-weight: bold;
        }

        .dd-identity .line {
            font-size: {{ $font + 1 }}pt;
            font-weight: bold;
        }

        table.dd-lines {
            border-collapse: collapse;
            table-layout: fixed;
        }

        table.dd-lines td {
            padding: 0 2mm 0 0;
            white-space: nowrap;
            font-weight: bold;
            font-size: {{ $font }}pt;
        }
    </style>
</head>

<body>
    <div class="tag">
        @include($view)
    </div>
</body>

</html>
