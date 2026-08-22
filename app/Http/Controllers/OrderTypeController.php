<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderTypeRequest;
use App\Models\OrderType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class OrderTypeController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:order_type.view', only: ['index']),
            new Middleware('permission:order_type.create', only: ['create', 'store']),
            new Middleware('permission:order_type.edit', only: ['edit', 'update']),
            new Middleware('permission:order_type.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->data();
        }

        return view('order-types.index');
    }

    private function data(): JsonResponse
    {
        // select() before withCount() — the other order discards the count subquery.
        $query = OrderType::query()->select('order_types.*')->withCount('supplierOrders');

        return DataTables::eloquent($query)
            ->addColumn('status', fn (OrderType $type) => view('components.status-badge', ['active' => $type->is_active])->render())
            ->addColumn('action', fn (OrderType $type) => view('order-types.partials.actions', ['type' => $type])->render())
            ->orderColumn('status', 'is_active $1')
            ->rawColumns(['status', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        return view('order-types.create', ['type' => new OrderType(['is_active' => true, 'sort_order' => 0])]);
    }

    public function store(OrderTypeRequest $request): RedirectResponse
    {
        $type = OrderType::create($request->validated());

        return redirect()->route('order-types.index')
            ->with('success', "Order type \"{$type->name}\" has been created.");
    }

    public function edit(OrderType $orderType): View
    {
        return view('order-types.edit', ['type' => $orderType]);
    }

    public function update(OrderTypeRequest $request, OrderType $orderType): RedirectResponse
    {
        $orderType->update($request->validated());

        return redirect()->route('order-types.index')
            ->with('success', "Order type \"{$orderType->name}\" has been updated.");
    }

    public function destroy(OrderType $orderType): RedirectResponse
    {
        // Orders keep the name they printed, so removing one here only takes it off
        // the dropdown — no slip loses its record.
        $name = $orderType->name;
        $orderType->delete();

        return redirect()->route('order-types.index')
            ->with('success', "Order type \"{$name}\" has been deleted.");
    }
}
