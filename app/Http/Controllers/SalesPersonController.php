<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalesPersonRequest;
use App\Models\SalesPerson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class SalesPersonController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:sales_person.view', only: ['index']),
            new Middleware('permission:sales_person.create', only: ['create', 'store']),
            new Middleware('permission:sales_person.edit', only: ['edit', 'update']),
            new Middleware('permission:sales_person.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->data();
        }

        return view('sales-persons.index');
    }

    private function data(): JsonResponse
    {
        // select() before withCount() — the other order discards the count subquery.
        $query = SalesPerson::query()->select('sales_persons.*')->withCount('repairFormLinks');

        return DataTables::eloquent($query)
            ->editColumn('phone', fn (SalesPerson $person) => e($person->phone ?: '—'))
            ->editColumn('city', fn (SalesPerson $person) => e($person->city ?: '—'))
            ->addColumn('status', fn (SalesPerson $person) => view('components.status-badge', ['active' => $person->is_active])->render())
            ->addColumn('action', fn (SalesPerson $person) => view('sales-persons.partials.actions', ['person' => $person])->render())
            ->orderColumn('status', 'is_active $1')
            ->rawColumns(['status', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        return view('sales-persons.create', ['person' => new SalesPerson(['is_active' => true, 'sort_order' => 0])]);
    }

    public function store(SalesPersonRequest $request): RedirectResponse
    {
        $person = SalesPerson::create($request->validated());

        return redirect()->route('sales-persons.index')
            ->with('success', "Sales person \"{$person->name}\" has been created.");
    }

    public function edit(SalesPerson $salesPerson): View
    {
        return view('sales-persons.edit', ['person' => $salesPerson]);
    }

    public function update(SalesPersonRequest $request, SalesPerson $salesPerson): RedirectResponse
    {
        $salesPerson->update($request->validated());

        return redirect()->route('sales-persons.index')
            ->with('success', "Sales person \"{$salesPerson->name}\" has been updated.");
    }

    public function destroy(SalesPerson $salesPerson): RedirectResponse
    {
        // Repair forms keep the name they printed, so deleting here only removes the
        // person from the dropdown — no form loses its record.
        $name = $salesPerson->name;
        $salesPerson->delete();

        return redirect()->route('sales-persons.index')
            ->with('success', "Sales person \"{$name}\" has been deleted.");
    }
}
