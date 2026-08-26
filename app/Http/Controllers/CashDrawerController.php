<?php

namespace App\Http\Controllers;

use App\Http\Requests\CashDrawerRequest;
use App\Models\CashDrawer;
use App\Models\CashEntry;
use App\Services\CashMath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class CashDrawerController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:cash_drawer.view', only: ['index']),
            new Middleware('permission:cash_drawer.create', only: ['create', 'store']),
            new Middleware('permission:cash_drawer.edit', only: ['edit', 'update']),
            new Middleware('permission:cash_drawer.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->data();
        }

        return view('cash-drawers.index');
    }

    private function data(): JsonResponse
    {
        // select() before the addSelect — the other order discards it.
        //
        // Every balance in one correlated subselect rather than a call to
        // CashDrawer::balance() per row. Built off CashEntry::query() and not
        // DB::table() on purpose: the Eloquent builder carries the soft-delete
        // scope, so a deleted entry drops out of the balance for free.
        $query = CashDrawer::query()
            ->select('cash_drawers.*')
            ->addSelect(['movement' => CashEntry::query()
                ->selectRaw('COALESCE(SUM('.CashMath::SIGNED_SQL.'), 0)')
                ->whereColumn('cash_drawer_id', 'cash_drawers.id'),
            ]);

        return DataTables::eloquent($query)
            ->editColumn('opening_balance', fn (CashDrawer $drawer) => number_format((float) $drawer->opening_balance, 2))
            ->addColumn('balance', fn (CashDrawer $drawer) => '<strong>'
                .number_format((float) $drawer->opening_balance + (float) $drawer->movement, 2).'</strong>')
            ->addColumn('status', fn (CashDrawer $drawer) => view('components.status-badge', ['active' => $drawer->is_active])->render())
            ->addColumn('action', fn (CashDrawer $drawer) => view('cash-drawers.partials.actions', compact('drawer'))->render())
            ->orderColumn('status', 'is_active $1')
            ->orderColumn('balance', '(opening_balance + movement) $1')
            ->rawColumns(['balance', 'status', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        return view('cash-drawers.create', ['drawer' => new CashDrawer]);
    }

    public function store(CashDrawerRequest $request): RedirectResponse
    {
        $drawer = CashDrawer::create($request->validated());

        return redirect()->route('cash-drawers.index')
            ->with('success', "Cash drawer \"{$drawer->name}\" has been created.");
    }

    public function edit(CashDrawer $cashDrawer): View
    {
        return view('cash-drawers.edit', ['drawer' => $cashDrawer]);
    }

    public function update(CashDrawerRequest $request, CashDrawer $cashDrawer): RedirectResponse
    {
        $cashDrawer->update($request->validated());

        return redirect()->route('cash-drawers.index')
            ->with('success', "Cash drawer \"{$cashDrawer->name}\" has been updated.");
    }

    public function destroy(CashDrawer $cashDrawer): RedirectResponse
    {
        if ($cashDrawer->entries()->exists()) {
            return back()->with('error',
                "\"{$cashDrawer->name}\" has cash entries and cannot be deleted.");
        }

        $name = $cashDrawer->name;
        $cashDrawer->delete();

        return redirect()->route('cash-drawers.index')
            ->with('success', "Cash drawer \"{$name}\" has been deleted.");
    }
}
