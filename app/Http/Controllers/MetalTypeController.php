<?php

namespace App\Http\Controllers;

use App\Http\Requests\MetalTypeRequest;
use App\Models\MetalType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class MetalTypeController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:metal_type.view', only: ['index']),
            new Middleware('permission:metal_type.create', only: ['create', 'store']),
            new Middleware('permission:metal_type.edit', only: ['edit', 'update']),
            new Middleware('permission:metal_type.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->data();
        }

        return view('metal-types.index');
    }

    private function data(): JsonResponse
    {
        // select() before withCount() — the other order discards the count subqueries.
        $query = MetalType::query()->select('metal_types.*')->withCount(['purities', 'items']);

        return DataTables::eloquent($query)
            ->editColumn('code', fn (MetalType $type) => '<code>'.e($type->code).'</code>')
            ->addColumn('status', fn (MetalType $type) => view('components.status-badge', ['active' => $type->is_active])->render())
            ->addColumn('action', fn (MetalType $type) => view('metal-types.partials.actions', ['metalType' => $type])->render())
            ->orderColumn('status', 'is_active $1')
            ->rawColumns(['code', 'status', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        return view('metal-types.create', ['metalType' => new MetalType(['is_active' => true, 'sort_order' => 0])]);
    }

    public function store(MetalTypeRequest $request): RedirectResponse
    {
        $metalType = MetalType::create($request->validated());

        return redirect()->route('metal-types.index')
            ->with('success', "Metal type \"{$metalType->name}\" has been created.");
    }

    public function edit(MetalType $metalType): View
    {
        return view('metal-types.edit', compact('metalType'));
    }

    public function update(MetalTypeRequest $request, MetalType $metalType): RedirectResponse
    {
        $metalType->update($request->validated());

        return redirect()->route('metal-types.index')
            ->with('success', "Metal type \"{$metalType->name}\" has been updated.");
    }

    public function destroy(MetalType $metalType): RedirectResponse
    {
        if ($metalType->items()->exists()) {
            return back()->with('error', "\"{$metalType->name}\" is used by existing items and cannot be deleted.");
        }

        if ($metalType->purities()->exists()) {
            return back()->with('error', "Delete the purities under \"{$metalType->name}\" first.");
        }

        $name = $metalType->name;
        $metalType->delete();

        return redirect()->route('metal-types.index')
            ->with('success', "Metal type \"{$name}\" has been deleted.");
    }
}
