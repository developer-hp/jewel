<?php

namespace App\Http\Controllers;

use App\Models\MetalType;
use App\Services\StockFigures;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * How stock moved on one day: opening, what came in, what went, and what is left.
 */
class StockReportController extends Controller implements HasMiddleware
{
    public function __construct(private readonly StockFigures $figures) {}

    public static function middleware(): array
    {
        return [new Middleware('permission:stock.report')];
    }

    public function index(Request $request): View
    {
        return view('stock.daily', $this->sheet($request));
    }

    public function export(Request $request): Response
    {
        $data = $this->sheet($request);

        return Pdf::loadView('stock.daily-export', $data)
            ->setPaper('a4', 'landscape')
            ->stream('stock-daily-'.$data['date']->format('Y-m-d').'.pdf', ['Attachment' => false]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sheet(Request $request): array
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->string('date')->toString())
            : today();

        $metalTypeId = $request->integer('metal_type_id') ?: null;

        $rows = $this->figures->daily($date, $metalTypeId);

        return [
            'date' => $date,
            'metalTypes' => MetalType::active()->ordered()->pluck('name', 'id'),
            'metalTypeId' => $metalTypeId,
            'metalTypeName' => $metalTypeId ? MetalType::find($metalTypeId)?->name : null,
            'rows' => $rows,
            'totals' => $this->figures->totals($rows, [
                'opening_pcs', 'opening_wt', 'add_pcs', 'add_wt',
                'less_pcs', 'less_wt', 'closing_pcs', 'closing_wt',
            ]),
        ];
    }
}
