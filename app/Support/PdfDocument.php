<?php

namespace App\Support;

use Illuminate\Http\Response;
use niklasravnsborg\LaravelPdf\Facades\Pdf;

/**
 * Every PDF in the app is rendered through here.
 *
 * Two reasons this exists rather than the facade being called directly.
 *
 * The package's own stream() hands the file to mPDF's Output($name, 'I'), which
 * writes raw header() calls and echoes the bytes — it never builds a Laravel
 * response. That bypasses the middleware stack, cannot be asserted on in a test,
 * and throws outright if anything has already touched the output buffer. Taking
 * output() and wrapping it ourselves keeps a PDF an ordinary response.
 *
 * And mPDF measures pages in millimetres where dompdf measured them in points, so
 * the size helpers below are the one place that conversion lives.
 */
class PdfDocument
{
    /**
     * A4 upright — what almost every document in this app prints on.
     *
     * @return array<string, mixed>
     */
    public static function a4(string $orientation = 'P'): array
    {
        return ['format' => 'A4', 'orientation' => $orientation];
    }

    /**
     * A named stock size: 'A4', 'A3', 'A5'…
     *
     * @return array<string, mixed>
     */
    public static function paper(string $format, string $orientation = 'P'): array
    {
        return ['format' => $format, 'orientation' => $orientation];
    }

    /**
     * A cut-to-size page — stickers and item tags.
     *
     * Millimetres, not points: mPDF reads a format array as [width, height] in mm.
     *
     * @return array<string, mixed>
     */
    public static function size(float $widthMm, float $heightMm): array
    {
        return ['format' => [$widthMm, $heightMm]];
    }

    /**
     * Page margins in millimetres, to merge into a size.
     *
     * mPDF reserves space for a running header and footer even when there is
     * none, which shows up as an unexplained gap at the top of the page — so both
     * are zeroed unless a caller says otherwise.
     *
     * @return array<string, mixed>
     */
    public static function margins(float $all): array
    {
        return static::marginBox($all, $all, $all, $all);
    }

    /**
     * @return array<string, mixed>
     */
    public static function marginBox(float $top, float $right, float $bottom, float $left): array
    {
        return [
            'margin_top' => $top,
            'margin_right' => $right,
            'margin_bottom' => $bottom,
            'margin_left' => $left,
            'margin_header' => 0,
            'margin_footer' => 0,
        ];
    }

    /**
     * Render a view and return it as an inline PDF response.
     *
     * Inline, not an attachment: the counter prints from the browser's viewer
     * rather than fishing the file out of a downloads folder.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $config
     */
    public static function stream(string $view, array $data, string $filename, array $config = []): Response
    {
        return static::respond(static::render($view, $data, $config), $filename, 'inline');
    }

    /**
     * The same document as a download, for the exports that are filed rather than
     * read on screen.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $config
     */
    public static function download(string $view, array $data, string $filename, array $config = []): Response
    {
        return static::respond(static::render($view, $data, $config), $filename, 'attachment');
    }

    /**
     * The raw bytes, for callers that need to attach or store the file.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $config
     */
    public static function render(string $view, array $data, array $config = []): string
    {
        return Pdf::loadView($view, $data, [], $config)->output();
    }

    /**
     * Wrap bytes that were rendered earlier. Angadiya needs this: it stamps the
     * slips as printed only once the sheet has actually rendered, so the document
     * exists before the response does.
     */
    public static function inline(string $pdf, string $filename): Response
    {
        return static::respond($pdf, $filename, 'inline');
    }

    private static function respond(string $pdf, string $filename, string $disposition): Response
    {
        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
        ]);
    }
}
