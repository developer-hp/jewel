<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurityRequest;
use App\Models\MetalType;
use App\Models\Purity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class PurityController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:purity.view', only: ['index']),
            new Middleware('permission:purity.create', only: ['create', 'store']),
            new Middleware('permission:purity.edit', only: ['edit', 'update']),
            new Middleware('permission:purity.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->data($request);
        }

        return view('purities.index', [
            'metalTypes' => MetalType::ordered()->pluck('name', 'id'),
        ]);
    }

    private function data(Request $request): JsonResponse
    {
        $query = Purity::query()
            ->select('purities.*')
            ->with('metalType')
            ->withCount('items')
            ->when($request->filled('metal_type_id'), fn ($q) => $q->where('metal_type_id', $request->integer('metal_type_id')));

        return DataTables::eloquent($query)
            ->addColumn('metal_type', fn (Purity $purity) => e($purity->metalType?->name ?? '—'))
            ->addColumn('rate', fn (Purity $purity) => view('purities.partials.rate-cell', compact('purity'))->render())
            ->addColumn('status', fn (Purity $purity) => view('components.status-badge', ['active' => $purity->is_active])->render())
            ->addColumn('action', fn (Purity $purity) => view('purities.partials.actions', compact('purity'))->render())
            ->filterColumn('metal_type', fn ($q, $keyword) => $q->whereRelation('metalType', 'name', 'like', "%{$keyword}%"))
            ->orderColumn('metal_type', function ($q, $direction) {
                $q->orderBy(
                    MetalType::select('name')->whereColumn('metal_types.id', 'purities.metal_type_id'),
                    $direction
                );
            })
            ->orderColumn('status', 'is_active $1')
            ->rawColumns(['rate', 'status', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        return view('purities.create', [
            'purity' => new Purity(['is_active' => true, 'default_per_grams' => 10, 'sort_order' => 0]),
            'metalTypes' => MetalType::active()->ordered()->pluck('name', 'id'),
        ]);
    }

    public function store(PurityRequest $request): RedirectResponse
    {
        $purity = Purity::create($request->validated());

        return redirect()->route('purities.index')
            ->with('success', "Purity \"{$purity->name}\" has been created.");
    }

    public function edit(Purity $purity): View
    {
        return view('purities.edit', [
            'purity' => $purity,
            'metalTypes' => MetalType::active()->ordered()->pluck('name', 'id'),
        ]);
    }

    public function update(PurityRequest $request, Purity $purity): RedirectResponse
    {
        $purity->update($request->validated());

        return redirect()->route('purities.index')
            ->with('success', "Purity \"{$purity->name}\" has been updated.");
    }

    public function destroy(Purity $purity): RedirectResponse
    {
        if ($purity->items()->exists()) {
            return back()->with('error', "\"{$purity->label()}\" is used by existing items and cannot be deleted.");
        }

        $label = $purity->label();
        $purity->delete();

        return redirect()->route('purities.index')
            ->with('success', "Purity \"{$label}\" has been deleted.");
    }
}
