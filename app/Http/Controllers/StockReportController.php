<?php

namespace App\Http\Controllers;

use App\Exports\StockItemsExport;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\StockGroup;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Yajra\DataTables\Facades\DataTables;

class StockReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:stock.view', only: ['index', 'print']),
            new Middleware('permission:stock.report', only: ['itemsIndex', 'itemsData', 'itemsExport']),
        ];
    }

    public function itemsIndex(): View
    {
        $itemGroups = ItemGroup::active()->orderBy('name')->get();
        $stockGroups = StockGroup::active()->ordered()->get();
        $metalTypes = MetalType::active()->orderBy('name')->get();
        $purities = Purity::active()->orderBy('name')->get();

        return view('stock-report.items-index', compact(
            'itemGroups', 'stockGroups', 'metalTypes', 'purities'
        ));
    }

    public function itemsData(Request $request)
    {
        $query = Item::query()
            ->with('itemGroup.stockGroup', 'metalType', 'purity')
            ->when($request->item_group_id, fn ($q) => $q->where('item_group_id', $request->item_group_id))
            ->when($request->stock_group_id, function ($q) use ($request) {
                return $q->whereHas('itemGroup', fn ($sub) => $sub->where('stock_group_id', $request->stock_group_id));
            })
            ->when($request->metal_type_id, fn ($q) => $q->where('metal_type_id', $request->metal_type_id))
            ->when($request->purity_id, fn ($q) => $q->where('purity_id', $request->purity_id))
            ->when($request->status, function ($q) use ($request) {
                if ($request->status === 'active') {
                    return $q->whereNull('sold_at');
                } elseif ($request->status === 'sold') {
                    return $q->whereNotNull('sold_at');
                }
                return $q;
            })
            ->orderBy('created_at', 'desc');

        return DataTables::of($query)
            ->addColumn('code', fn (Item $item) => $item->code ?? '-')
            ->addColumn('item_group', fn (Item $item) => $item->itemGroup?->name)
            ->addColumn('stock_group', fn (Item $item) => $item->itemGroup?->stockGroup?->name)
            ->addColumn('metal_type', fn (Item $item) => $item->metalType?->name)
            ->addColumn('purity', fn (Item $item) => $item->purity?->name)
            ->addColumn('gross_weight', fn (Item $item) => (float) $item->gross_weight . 'g')
            ->addColumn('status', fn (Item $item) => view('stock-report.partials._status-badge', ['item' => $item])->render())
            ->addColumn('created_at', fn (Item $item) => $item->created_at?->format('d M Y'))
            ->rawColumns(['status'])
            ->make(true);
    }

    public function itemsExport(Request $request): BinaryFileResponse
    {
        return Excel::download(
            new StockItemsExport($request->all()),
            'stock-items-' . now()->format('d-m-Y-H-i-s') . '.xlsx'
        );
    }

    // Existing methods for stock.daily report would go here
}
