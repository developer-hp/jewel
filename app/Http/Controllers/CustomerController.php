<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class CustomerController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:customer.view', only: ['index', 'lookup']),
            new Middleware('permission:customer.create', only: ['create', 'store']),
            new Middleware('permission:customer.edit', only: ['edit', 'update']),
            new Middleware('permission:customer.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->data();
        }

        return view('customers.index');
    }

    private function data(): JsonResponse
    {
        // select() before withCount() — the other order discards the count subquery.
        $query = Customer::query()->select('customers.*')->withCount('repairForms');

        return DataTables::eloquent($query)
            ->editColumn('address', fn (Customer $customer) => e(Str::limit($customer->address, 60) ?: '—'))
            ->addColumn('status', fn (Customer $customer) => view('components.status-badge', ['active' => $customer->is_active])->render())
            ->addColumn('action', fn (Customer $customer) => view('customers.partials.actions', ['customer' => $customer])->render())
            // Searching the number has to ignore how it was punctuated. The key is
            // only brought in when the term actually has digits — matching on an
            // empty key is `LIKE '%%'`, which would return every row.
            ->filterColumn('phone', function ($q, $keyword) {
                $key = Customer::phoneKey($keyword);

                $q->where(function ($sub) use ($keyword, $key) {
                    $sub->where('phone', 'like', "%{$keyword}%");

                    if ($key !== '') {
                        $sub->orWhere('phone_key', 'like', "%{$key}%");
                    }
                });
            })
            ->orderColumn('status', 'is_active $1')
            ->rawColumns(['status', 'action'])
            ->toJson();
    }

    /**
     * Look a customer up by number, for the repair form to prefill from.
     *
     * Returns 200 with a null customer rather than a 404: "nobody by that number"
     * is an ordinary answer here, not an error.
     */
    public function lookup(Request $request): JsonResponse
    {
        $customer = Customer::findByPhone($request->string('phone')->toString());

        return response()->json([
            'customer' => $customer ? [
                'id' => $customer->id,
                'name' => $customer->name,
                'address' => $customer->address,
            ] : null,
        ]);
    }

    /**
     * Customers matching what has been typed, for a select2.
     *
     * lookup() above answers "who has this exact number"; this answers "who did I
     * mean", which is what a picker needs.
     */
    public function search(Request $request): JsonResponse
    {
        $term = $request->string('q')->toString();

        $customers = Customer::query()
            ->where('is_active', true)
            ->when($term !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                // The digits-only key, so "9712 40" finds "9712406367".
                ->orWhere('phone_key', 'like', '%'.Customer::phoneKey($term).'%')))
            ->orderBy('name')
            ->limit(30)
            ->get();

        return response()->json([
            'customers' => $customers->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
            ])->all(),
        ]);
    }

    public function create(): View
    {
        return view('customers.create', ['customer' => new Customer(['is_active' => true])]);
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $customer = Customer::create($request->validated());

        return redirect()->route('customers.index')
            ->with('success', "Customer \"{$customer->name}\" has been created.");
    }

    public function edit(Customer $customer): View
    {
        return view('customers.edit', ['customer' => $customer]);
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return redirect()->route('customers.index')
            ->with('success', "Customer \"{$customer->name}\" has been updated.");
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        // Repair forms keep their own copy of the name, number and address, so
        // removing someone here never blanks a form that has already printed.
        $name = $customer->name;
        $customer->delete();

        return redirect()->route('customers.index')
            ->with('success', "Customer \"{$name}\" has been deleted.");
    }
}
