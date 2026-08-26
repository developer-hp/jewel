<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

/**
 * The pieces that have been paid for, waiting to be written out of stock.
 *
 * A piece reaches this screen by being on an item estimate that a cash entry has
 * settled — money changed hands for it, so it is almost certainly gone. Almost, not
 * certainly, which is why marking it sold is a deliberate act here and reversible
 * from the same row.
 */
class SoldItemController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:item.view', only: ['index']),
            // Writing a piece out of stock is an edit to the piece.
            new Middleware('permission:item.edit', only: ['markSold', 'markAvailable']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->data($request);
        }

        return view('sold-items.index');
    }

    private function data(Request $request): JsonResponse
    {
        $query = $this->settledItems()
            ->select('items.*')
            // The settled estimate and its cash entry come with the row rather than
            // being fetched per row — the Settled column would otherwise be two
            // queries an item.
            ->with([
                'itemGroup:id,name', 'metalType:id,name', 'purity:id,name',
                'estimateLines.itemEstimate.cashEntry',
            ])
            ->when($request->string('state')->toString() === 'sold', fn ($q) => $q->sold())
            ->when($request->string('state')->toString() === 'in_stock', fn ($q) => $q->inStock());

        return DataTables::eloquent($query)
            ->editColumn('code', fn (Item $item) => '<code>'.e($item->code).'</code>')
            ->addColumn('group', fn (Item $item) => e($item->itemGroup?->name ?? '—'))
            ->addColumn('metal', fn (Item $item) => e(trim(($item->metalType?->name ?? '').' '.($item->purity?->name ?? ''))))
            ->editColumn('net_weight', fn (Item $item) => number_format((float) $item->net_weight, 3))
            // Off the cash entry's own snapshots, so this joins nothing further.
            ->addColumn('settled', fn (Item $item) => $this->settlement($item))
            ->addColumn('sold', fn (Item $item) => $item->isSold()
                ? '<span class="badge bg-danger">Sold '.$item->sold_at->format('d-m-Y').'</span>'
                : '<span class="badge bg-success">In stock</span>')
            ->addColumn('action', fn (Item $item) => view('sold-items.partials.actions', compact('item'))->render())
            ->orderColumn('sold', 'sold_at $1')
            ->rawColumns(['code', 'settled', 'sold', 'action'])
            ->toJson();
    }

    public function markSold(Item $item): RedirectResponse
    {
        if (! $this->settledItems()->whereKey($item->id)->exists()) {
            return back()->with('error',
                "{$item->code} has no settled estimate behind it, so it cannot be sold from here.");
        }

        $item->markSold();

        return back()->with('success', "{$item->code} has been marked sold.");
    }

    public function markAvailable(Item $item): RedirectResponse
    {
        $item->markAvailable();

        return back()->with('success', "{$item->code} is back in stock.");
    }

    /**
     * Items on an item estimate that a cash entry has settled.
     *
     * whereHas rather than a join, so the item rows stay distinct — a piece quoted
     * on two estimates would otherwise appear twice.
     */
    private function settledItems()
    {
        return Item::query()->whereHas('estimateLines.itemEstimate.cashEntry');
    }

    /**
     * The estimate and the cash entry a piece was settled by, read off what was
     * already eager-loaded.
     */
    private function settlement(Item $item): string
    {
        $entry = $item->estimateLines
            ->map(fn ($line) => $line->itemEstimate?->cashEntry)
            ->filter()
            ->first();

        if (! $entry) {
            return '—';
        }

        return '<strong>'.e($entry->document_reference).'</strong>'
            .'<div class="text-muted fs-12">'.e($entry->reference()).' &middot; '
            .$entry->entry_date->format('d-m-Y').'</div>';
    }
}
