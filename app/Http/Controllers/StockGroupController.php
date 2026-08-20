<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockGroupRequest;
use App\Models\StockGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class StockGroupController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:stock_group.view', only: ['index']),
            new Middleware('permission:stock_group.create', only: ['create', 'store']),
            new Middleware('permission:stock_group.edit', only: ['edit', 'update']),
            new Middleware('permission:stock_group.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->data();
        }

        return view('stock-groups.index');
    }

    private function data(): JsonResponse
    {
        // select() before withCount() — the other order discards the count subquery.
        $query = StockGroup::query()->select('stock_groups.*')->withCount('itemGroups');

        return DataTables::eloquent($query)
            ->editColumn('code', fn (StockGroup $group) => '<code>'.e($group->code).'</code>')
            ->addColumn('status', fn (StockGroup $group) => view('components.status-badge', ['active' => $group->is_active])->render())
            ->addColumn('action', fn (StockGroup $group) => view('stock-groups.partials.actions', ['stockGroup' => $group])->render())
            ->orderColumn('status', 'is_active $1')
            ->rawColumns(['code', 'status', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        return view('stock-groups.create', ['stockGroup' => new StockGroup(['is_active' => true, 'sort_order' => 0])]);
    }

    public function store(StockGroupRequest $request): RedirectResponse
    {
        $group = StockGroup::create($request->validated());

        return redirect()->route('stock-groups.index')
            ->with('success', "Stock group \"{$group->name}\" has been created.");
    }

    public function edit(StockGroup $stockGroup): View
    {
        return view('stock-groups.edit', ['stockGroup' => $stockGroup]);
    }

    public function update(StockGroupRequest $request, StockGroup $stockGroup): RedirectResponse
    {
        $stockGroup->update($request->validated());

        return redirect()->route('stock-groups.index')
            ->with('success', "Stock group \"{$stockGroup->name}\" has been updated.");
    }

    public function destroy(StockGroup $stockGroup): RedirectResponse
    {
        if ($stockGroup->itemGroups()->exists()) {
            return back()->with('error', "\"{$stockGroup->name}\" is assigned to item groups and cannot be deleted.");
        }

        $name = $stockGroup->name;
        $stockGroup->delete();

        return redirect()->route('stock-groups.index')
            ->with('success', "Stock group \"{$name}\" has been deleted.");
    }
}
