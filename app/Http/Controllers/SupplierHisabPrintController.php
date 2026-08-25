<?php

namespace App\Http\Controllers;

use App\Models\SupplierHisab;
use App\Support\PdfDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;

/**
 * The two printed outputs: the per-supplier slips, and the day summary.
 *
 * Neither stamps anything, but the slips arrive by POST because the listing sends a
 * set of ticked ids that would not survive a query string at 200 rows.
 */
class SupplierHisabPrintController extends Controller implements HasMiddleware
{
    /**
     * Slips per A4 row. Fixed at two: the slip carries a full working column and
     * does not read at three across.
     */
    private const COLUMNS = 2;

    public static function middleware(): array
    {
        return [new Middleware('permission:supplier_hisab.print')];
    }

    public function slips(Request $request): Response|RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', 'exists:supplier_hisabs,id'],
        ]);

        // Ordered by id so the sheet follows the order they were entered.
        $hisabs = SupplierHisab::whereIn('id', $validated['ids'])
            ->with('payments')
            ->orderBy('id')
            ->get();

        if ($hisabs->isEmpty()) {
            return back()->with('error', 'Those entries no longer exist.');
        }

        return PdfDocument::stream('supplier-hisabs.slips', [
            'rows' => $hisabs->chunk(self::COLUMNS),
            'columns' => self::COLUMNS,
        ], 'supplier-hisab-'.now()->format('Y-m-d-His').'.pdf', PdfDocument::a4());
    }

    public function summary(Request $request): Response
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->string('date')->toString())
            : today();

        $hisabs = SupplierHisab::query()
            ->onDate($date)
            ->with('payments')
            ->orderBy('id')
            ->get();

        return PdfDocument::stream('supplier-hisabs.summary', [
            'date' => $date,
            'hisabs' => $hisabs,
            'totals' => [
                'fine_baki' => round((float) $hisabs->sum('fine_baki'), 3),
                'cash_baki' => round((float) $hisabs->sum('cash_baki'), 2),
                'fine_kapi' => round($hisabs->sum(fn (SupplierHisab $h) => $h->fineKapi()), 3),
                'cash_apvi' => round($hisabs->sum(fn (SupplierHisab $h) => $h->cashApvi()), 2),
            ],
        ], 'supplier-hisab-summary-'.$date->format('Y-m-d').'.pdf', PdfDocument::a4());
    }
}
