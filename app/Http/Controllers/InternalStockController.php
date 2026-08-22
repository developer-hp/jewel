<?php

namespace App\Http\Controllers;

use App\Http\Requests\InternalStockRequest;
use App\Models\InternalStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class InternalStockController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:internal_stock.view', only: ['index']),
            new Middleware('permission:internal_stock.create', only: ['create', 'store']),
            new Middleware('permission:internal_stock.edit', only: ['edit', 'update', 'toggleReset']),
            new Middleware('permission:internal_stock.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->data();
        }

        return view('internal-stocks.index');
    }

    private function data(): JsonResponse
    {
        // select() before withCount() — the other order discards the count subquery.
        $query = InternalStock::query()->select('internal_stocks.*')->withCount('entries');

        return DataTables::eloquent($query)
            ->addColumn('reset', fn (InternalStock $stock) => view('internal-stocks.partials.reset-cell', ['stock' => $stock])->render())
            ->addColumn('status', fn (InternalStock $stock) => view('components.status-badge', ['active' => $stock->is_active])->render())
            ->addColumn('action', fn (InternalStock $stock) => view('internal-stocks.partials.actions', ['stock' => $stock])->render())
            ->orderColumn('reset', 'reset_on_opening $1')
            ->orderColumn('status', 'is_active $1')
            ->rawColumns(['reset', 'status', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        return view('internal-stocks.create', ['stock' => new InternalStock(['is_active' => true])]);
    }

    public function store(InternalStockRequest $request): RedirectResponse
    {
        $stock = InternalStock::create($request->validated());

        return redirect()->route('internal-stocks.index')
            ->with('success', "Internal stock \"{$stock->name}\" has been created.");
    }

    public function edit(InternalStock $internalStock): View
    {
        return view('internal-stocks.edit', ['stock' => $internalStock]);
    }

    public function update(InternalStockRequest $request, InternalStock $internalStock): RedirectResponse
    {
        $internalStock->update($request->validated());

        return redirect()->route('internal-stocks.index')
            ->with('success', "Internal stock \"{$internalStock->name}\" has been updated.");
    }

    /**
     * The Yes/No radios in the listing, so the flag is set without opening the row.
     */
    public function toggleReset(Request $request, InternalStock $internalStock): JsonResponse
    {
        $validated = $request->validate(['reset_on_opening' => ['required', 'boolean']]);

        $internalStock->update(['reset_on_opening' => $validated['reset_on_opening']]);

        return response()->json([
            'ok' => true,
            'message' => "{$internalStock->name} will "
                .($internalStock->reset_on_opening ? 'reset' : 'carry over').' on opening.',
        ]);
    }

    public function destroy(InternalStock $internalStock): RedirectResponse
    {
        // Its ledger is the record of what moved; removing the pot would orphan it.
        if ($internalStock->entries()->exists()) {
            return back()->with('error', "\"{$internalStock->name}\" has stock entries and cannot be deleted.");
        }

        $name = $internalStock->name;
        $internalStock->delete();

        return redirect()->route('internal-stocks.index')
            ->with('success', "Internal stock \"{$name}\" has been deleted.");
    }
}
