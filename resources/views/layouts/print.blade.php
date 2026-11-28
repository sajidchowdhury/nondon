<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Print')</title>
    <style>
        /*
         | Print label styles — minimal layout, no app navigation or sidebar.
         | Each label format gets a named @page with its exact physical size
         | so the browser print dialog produces correctly-sized labels
         | (Session 2.7 deliverable 3, per docs/13).
         |
         | Note: only the @page rule matching an element's `page:` property
         | applies to that element's page — all four rules can coexist here.
         */
        @page hang-tag-small { size: 50mm 30mm; margin: 0; }
        @page hang-tag-large { size: 75mm 50mm; margin: 0; }
        @page sticker-small  { size: 25.4mm 38.1mm; margin: 0; }
        @page sticker-large  { size: 25.4mm 66.7mm; margin: 0; }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: #ffffff;
            color: #000000;
            font-family: Arial, Helvetica, sans-serif;
        }

        /* Each label is its own printed page */
        .label-block {
            page-break-after: always;
            break-after: page;
            overflow: hidden;
            text-align: center;
        }
        .label-block:last-child {
            page-break-after: auto;
            break-after: auto;
        }

        .label-block.hang-tag-small { page: hang-tag-small; width: 50mm; height: 30mm; }
        .label-block.hang-tag-large { page: hang-tag-large; width: 75mm; height: 50mm; }
        .label-block.sticker-small  { page: sticker-small;  width: 25.4mm; height: 38.1mm; }
        .label-block.sticker-large  { page: sticker-large;  width: 25.4mm; height: 66.7mm; }

        .label-barcode {
            display: block;
            margin: 2mm auto 1mm;
            max-width: 96%;
        }
        .label-serial { font-weight: bold; letter-spacing: 0.5px; }
        .label-name { margin-top: 1mm; }
        .label-size { display: inline-block; font-weight: bold; margin-top: 1mm; }
        .label-price { margin-top: 1mm; font-weight: bold; }

        /* On screen (before the print dialog), show label boundaries */
        @media screen {
            body { padding: 16px; }
            .label-block { border: 1px dashed #999999; margin: 0 auto 12px; }
        }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
