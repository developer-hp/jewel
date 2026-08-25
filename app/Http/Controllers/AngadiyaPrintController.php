<?php

namespace App\Http\Controllers;

use App\Models\Angadiya;
use App\Models\AppSetting;
use App\Support\PdfDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Prints one or many angadiya slips onto an A4 sheet.
 *
 * POST rather than GET because it stamps printed_at; the listing form targets a new
 * tab so the sheet opens in the browser's PDF viewer.
 */
class AngadiyaPrintController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:angadiya.print')];
    }

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', 'exists:angadiyas,id'],
        ]);

        // Ordered by id so the sheet matches the order they were entered.
        $slips = Angadiya::whereIn('id', $validated['ids'])->orderBy('id')->get();

        if ($slips->isEmpty()) {
            return back()->with('error', 'Those slips no longer exist.');
        }

        $settings = AppSetting::current();
        $columns = max(1, (int) $settings->angadiya_columns);

        $pdf = PdfDocument::render('angadiyas.sheet', [
            'rows' => $slips->chunk($columns),
            'columns' => $columns,
            'slipHeightMm' => (float) $settings->angadiya_slip_height_mm,
            'from' => $slips->first()->fromBlock(),
        ], PdfDocument::a4());

        // Only stamp once the sheet actually rendered.
        $slips->each->markPrinted();

        return PdfDocument::inline($pdf, 'angadiya-'.now()->format('Y-m-d-His').'.pdf');
    }
}
