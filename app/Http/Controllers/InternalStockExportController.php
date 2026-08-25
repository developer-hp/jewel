<?php

namespace App\Http\Controllers;

use App\Models\InternalStockEntry;
use App\Support\PdfDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * The internal stock sheet: every movement with its totals.
 */
class InternalStockExportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:internal_stock_entry.print')];
    }

    public function __invoke(Request $request): Response
    {
        // Honours whatever the listing was filtered to, so what prints is what was
        // on screen.
        $entries = InternalStockEntry::query()
            ->with('internalStock')
            ->when($request->filled('internal_stock_id'), fn ($q) => $q->where('internal_stock_id', $request->integer('internal_stock_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->toString()))
            ->orderBy('type')
            ->orderByDesc('id')
            ->get();

        return PdfDocument::stream('internal-stock-entries.export', [
            'entries' => $entries,
            'totalIn' => round((float) $entries->whereIn('type', InternalStockEntry::INCOMING)->sum('weight'), 3),
            'totalOut' => round((float) $entries->where('type', InternalStockEntry::TYPE_OUT)->sum('weight'), 3),
        ], 'internal-stock-'.now()->format('Y-m-d').'.pdf', PdfDocument::a4());
    }
}
