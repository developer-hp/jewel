<?php

namespace App\Http\Controllers;

use App\Http\Requests\MakingChargeRequest;
use App\Models\MakingCharge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class MakingChargeController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:making_charge.view', only: ['index']),
            new Middleware('permission:making_charge.create', only: ['create', 'store']),
            new Middleware('permission:making_charge.edit', only: ['edit', 'update']),
            new Middleware('permission:making_charge.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->data($request);
        }

        return view('making-charges.index', ['types' => MakingCharge::TYPES]);
    }

    private function data(Request $request): JsonResponse
    {
        $query = MakingCharge::query()
            ->select('making_charges.*')
            ->withCount('items')
            ->when($request->filled('charge_type'), fn ($q) => $q->where('charge_type', $request->string('charge_type')->toString()));

        return DataTables::eloquent($query)
            ->editColumn('code', fn (MakingCharge $charge) => '<code>'.e($charge->code).'</code>')
            ->addColumn('type', fn (MakingCharge $charge) => e($charge->typeLabel()))
            ->addColumn('applies', fn (MakingCharge $charge) => '<strong>'.e($charge->summary()).'</strong>')
            ->addColumn('status', fn (MakingCharge $charge) => view('components.status-badge', ['active' => $charge->is_active])->render())
            ->addColumn('action', fn (MakingCharge $charge) => view('making-charges.partials.actions', compact('charge'))->render())
            ->orderColumn('type', 'charge_type $1')
            ->orderColumn('applies', 'rate $1')
            ->orderColumn('status', 'is_active $1')
            ->rawColumns(['code', 'applies', 'status', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        return view('making-charges.create', [
            'charge' => new MakingCharge(['is_active' => true, 'charge_type' => MakingCharge::TYPE_PER_GRAM, 'weight_basis' => 'net']),
        ]);
    }

    public function store(MakingChargeRequest $request): RedirectResponse
    {
        $charge = MakingCharge::create($request->validated());

        return redirect()->route('making-charges.index')
            ->with('success', "Making charge \"{$charge->code}\" has been created.");
    }

    public function edit(MakingCharge $makingCharge): View
    {
        return view('making-charges.edit', ['charge' => $makingCharge]);
    }

    public function update(MakingChargeRequest $request, MakingCharge $makingCharge): RedirectResponse
    {
        $makingCharge->update($request->validated());

        return redirect()->route('making-charges.index')
            ->with('success', "Making charge \"{$makingCharge->code}\" has been updated.");
    }

    public function destroy(MakingCharge $makingCharge): RedirectResponse
    {
        if ($makingCharge->items()->exists()) {
            return back()->with('error', "\"{$makingCharge->code}\" is assigned to existing items and cannot be deleted.");
        }

        $code = $makingCharge->code;
        $makingCharge->delete();

        return redirect()->route('making-charges.index')
            ->with('success', "Making charge \"{$code}\" has been deleted.");
    }
}
