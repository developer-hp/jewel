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
           so the plain layout table these sit inside strips the rules off them too.
           Put them back explicitly; without this the whole document prints flat. */
        table.lines > thead > tr > th,
        table.lines > tbody > tr > td,
        table.lines > tfoot > tr > td,
        table.signoff > tbody > tr > th,
        table.signoff > tbody > tr > td,
        table.signoff > tr > th,
        table.signoff > tr > td {
            border: 0.8pt solid #000 !important;
        }

        table.lines > thead > tr > th,
        table.signoff th {
            background: #cdcdcd !important;
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
    </style>
