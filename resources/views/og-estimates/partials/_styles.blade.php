{{--
    The OG estimate's print styles.

    Its own partial because the item estimate can attach an OG document as a further
    page, and that page needs these rules too. One definition, so the two documents
    cannot drift apart.
--}}
        body {
            font-size: 12px;
        }

        .form-page {
            page-break-after: always;
        }

        .form-page.last {
            page-break-after: auto;
        }

        table.lines {
            width: 100%;
            table-layout: fixed;
            margin-top: 2mm;
        }

        table.lines td {
            height: 5mm;
        }

        /* pdf.css kills borders with a DESCENDANT selector — `.no-border th, td` —
           so any plain layout table these sit inside strips the rules off them too.
           Put them back explicitly; without this the whole document prints flat.
           (The layout wrapper below now uses .layout rather than .no-border for the
           same reason, but this stays as the belt to that pair of braces.) */
        /* Only the header and the TOTAL row are ruled. The body stays open: those
           rows are written into by hand, and a full grid boxes the writing in. */
        table.lines > thead > tr > th,
        table.lines > tfoot > tr > td {
            border: 1px solid #000 !important;
        }

        table.lines > tbody > tr > td {
            border: none !important;
        }

        /* The wrapper that holds the lines table beside the grand total is pure
           layout. It must NOT be .no-border, whose descendant selector reaches into
           the lines table; only the two cells carrying .og-cell lose their rules.

           A child selector through tbody does not work here either: the markup has no
           explicit <tbody> and mPDF, unlike a browser, does not synthesise one — so
           both wrapper cells kept their pdf.css border and drew a second box just
           outside the lines table. That was the doubled rule. */
        table.layout {
            border: none;
        }

        td.og-cell {
            border: none !important;
        }

        /* Breathing room between the customer copy and the office copy. */
        .copy-gap {
            height: 15mm;
            font-size: 1pt;
            line-height: 1;
        }

        table.lines > thead > tr > th,
        table.signoff th {
            background: #cdcdcd !important;
        }

        /* The grand total used to sit as bare text floating off the right edge of
           the table with nothing holding it. Boxed, and pulled level with the
           header row. */
        .grand .grandbox {
            border: 1px solid #000;
            padding: 2mm 1mm;
            text-align: center;
            font-size: 15px;
            font-weight: bold;
        }

        .who {
            width: 100%;
            margin-bottom: 1mm;
        }

        .who td {
            padding: 0;
            vertical-align: top;
        }

        .copy-title {
            font-size: 15px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 2mm;
        }

        .grand {
            font-size: 15px;
            font-weight: bold;
            padding-left: 3mm;
        }

        table.signoff {
            width: 100%;
            table-layout: fixed;
            margin-top: 6mm;
        }

        table.signoff td {
            height: 14mm;
            vertical-align: top;
        }
        .no-border th{
            background: transparent;
            border-top: 1px solid black;
            border-bottom: 1px solid black;
        }
{{-- No closing </style> here: both callers wrap this include in their own <style>
     block, so emitting one made every page carry a stray orphan tag. --}}
