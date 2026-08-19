<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class SupplierController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:supplier.view', only: ['index']),
            new Middleware('permission:supplier.create', only: ['create', 'store']),
            new Middleware('permission:supplier.edit', only: ['edit', 'update']),
            new Middleware('permission:supplier.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->data($request);
        }

        return view('suppliers.index', [
            'cities' => Supplier::whereNotNull('city')->distinct()->orderBy('city')->pluck('city'),
        ]);
    }

    private function data(Request $request): JsonResponse
    {
        // select() before withCount() — the other order discards the count subquery.
        $query = Supplier::query()
            ->select('suppliers.*')
            ->withCount('items')
            ->when($request->filled('city'), fn ($q) => $q->where('city', $request->string('city')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->string('status')->toString() === 'active'));

        return DataTables::eloquent($query)
            ->addColumn('contact', fn (Supplier $supplier) => view('suppliers.partials.contact-cell', compact('supplier'))->render())
            ->addColumn('status', fn (Supplier $supplier) => view('components.status-badge', ['active' => $supplier->is_active])->render())
            ->addColumn('action', fn (Supplier $supplier) => view('suppliers.partials.actions', compact('supplier'))->render())
            ->filterColumn('contact', function ($q, $keyword) {
                $q->where(fn ($sub) => $sub->where('phone', 'like', "%{$keyword}%")
                    ->orWhere('address', 'like', "%{$keyword}%"));
            })
            ->orderColumn('contact', 'phone $1')
            ->orderColumn('status', 'is_active $1')
            ->rawColumns(['contact', 'status', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        return view('suppliers.create', ['supplier' => new Supplier(['is_active' => true])]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::create($request->validated());

        return redirect()->route('suppliers.index')
            ->with('success', "Supplier \"{$supplier->name}\" has been created.");
    }

    public function edit(Supplier $supplier): View
    {
        return view('suppliers.edit', compact('supplier'));
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return redirect()->route('suppliers.index')
            ->with('success', "Supplier \"{$supplier->name}\" has been updated.");
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        if ($supplier->items()->exists()) {
            return back()->with('error', "\"{$supplier->name}\" is linked to existing items and cannot be deleted.");
        }

        $name = $supplier->name;
        $supplier->delete();

        return redirect()->route('suppliers.index')
            ->with('success', "Supplier \"{$name}\" has been deleted.");
    }
}
