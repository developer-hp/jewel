<?php

namespace App\Http\Controllers;

use App\Models\MetalType;
use App\Models\StockGroup;
use App\Services\StockFigures;
use App\Support\PdfDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * What the shop holds right now, by item group and by stock group.
 */
class StockController extends Controller implements HasMiddleware
{
    public function __construct(private readonly StockFigures $figures) {}

    public static function middleware(): array
    {
        return [new Middleware('permission:stock.view')];
    }

    public function index(Request $request): View
    {
        return view('stock.index', $this->sheet($request));
    }

    public function print(Request $request): Response
    {
        return PdfDocument::stream('stock.print', $this->sheet($request), 'stock-'.now()->format('Y-m-d').'.pdf', PdfDocument::a4());
    }

    /**
     * Everything both the screen and the sheet need.
     *
     * @return array<string, mixed>
     */
    private function sheet(Request $request): array
    {
        $metalTypeId = $request->integer('metal_type_id') ?: null;

        $itemGroups = $this->figures->byItemGroup($metalTypeId);
        $stockGroups = $this->figures->byStockGroup(
            $itemGroups,
            StockGroup::active()->ordered()->get(),
        );

        $keys = ['pcs', 'held', 'gross', 'net'];

        return [
            'metalTypes' => MetalType::active()->ordered()->pluck('name', 'id'),
            'metalTypeId' => $metalTypeId,
            // Named on the sheet, so a printed page says what it covers.
            'metalTypeName' => $metalTypeId ? MetalType::find($metalTypeId)?->name : null,
            'itemGroups' => $itemGroups,
            'stockGroups' => $stockGroups,
            'itemGroupTotals' => $this->figures->totals($itemGroups, $keys),
            'stockGroupTotals' => $this->figures->totals($stockGroups, $keys),
        ];
    }
}
