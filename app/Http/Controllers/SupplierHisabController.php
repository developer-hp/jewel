<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierHisabRequest;
use App\Models\AppSetting;
use App\Models\Supplier;
use App\Models\SupplierHisab;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class SupplierHisabController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:supplier_hisab.view', only: ['index']),
            new Middleware('permission:supplier_hisab.create', only: ['store']),
            new Middleware('permission:supplier_hisab.edit', only: ['update', 'storeRate']),
            new Middleware('permission:supplier_hisab.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        $date = $this->date($request);

        if ($request->ajax() || $request->wantsJson()) {
            return $this->data($date);
        }

        return view('supplier-hisabs.index', [
            'date' => $date,
            'ratePer10g' => (float) AppSetting::current()->hisab_rate_per_10g,
            'suppliers' => Supplier::active()->ordered()->get(['id', 'name', 'short_name']),
        ]);
    }

    private function data(Carbon $date): JsonResponse
    {
        $query = SupplierHisab::query()
            ->select('supplier_hisabs.*')
            ->onDate($date)
            ->with('payments');

        // The day's totals for the footer. Deliberately unfiltered by the search box:
        // it is the day that is being totalled, not the current view of it.
        $day = SupplierHisab::query()->onDate($date)->get();

        return DataTables::eloquent($query)
            ->addColumn('select', fn (SupplierHisab $h) => view('supplier-hisabs.partials.select-cell', ['hisab' => $h])->render())
            ->addColumn('supplier', fn (SupplierHisab $h) => e($h->supplier_label))
            ->addColumn('gold_wt', fn (SupplierHisab $h) => $this->weight($h->fine_baki))
            ->addColumn('amount', fn (SupplierHisab $h) => number_format((float) $h->cash_baki, 2))
            ->addColumn('action', fn (SupplierHisab $h) => view('supplier-hisabs.partials.actions', ['hisab' => $h])->render())
            ->filterColumn('supplier', fn ($q, $keyword) => $q->where('supplier_label', 'like', "%{$keyword}%"))
            ->orderColumn('supplier', 'supplier_label $1')
            ->orderColumn('gold_wt', 'fine_baki $1')
            ->orderColumn('amount', 'cash_baki $1')
            ->with([
                'totals' => [
                    'fine_baki' => $this->weight($day->sum('fine_baki')),
                    'cash_baki' => number_format((float) $day->sum('cash_baki'), 2),
                ],
            ])
            ->rawColumns(['select', 'supplier', 'action'])
            ->toJson();
    }

    public function store(SupplierHisabRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $supplier = Supplier::findOrFail($data['supplier_id']);

        $hisab = SupplierHisab::create($data + [
            // Snapshotted so renaming the supplier cannot rewrite a printed slip.
            'supplier_label' => $supplier->short_name ?: $supplier->name,
        ]);
        $hisab->forceFill(['created_by' => $request->user()->id])->save();

        return $this->backToDay($hisab->hisab_date)
            ->with('success', "Hisab for \"{$hisab->supplier_label}\" has been saved.");
    }

    public function update(SupplierHisabRequest $request, SupplierHisab $hisab): RedirectResponse
    {
        $data = $request->validated();
        $supplier = Supplier::findOrFail($data['supplier_id']);

        $hisab->update($data + ['supplier_label' => $supplier->short_name ?: $supplier->name]);

        return $this->backToDay($hisab->hisab_date)
            ->with('success', "Hisab for \"{$hisab->supplier_label}\" has been updated.");
    }

    public function destroy(SupplierHisab $hisab): RedirectResponse
    {
        $label = $hisab->supplier_label;
        $date = $hisab->hisab_date;

        $hisab->payments()->delete();
        $hisab->delete();

        return $this->backToDay($date)->with('success', "Hisab for \"{$label}\" has been deleted.");
    }

    /**
     * The single "Rate Today" box on the listing toolbar.
     */
    public function storeRate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'hisab_rate_per_10g' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'date' => ['nullable', 'date'],
        ], [], ['hisab_rate_per_10g' => 'rate']);

        AppSetting::current()->update(['hisab_rate_per_10g' => $validated['hisab_rate_per_10g']]);

        return $this->backToDay($this->date($request))->with('success', 'Rate saved.');
    }

    /**
     * The day being viewed — today unless the picker says otherwise.
     */
    private function date(Request $request): Carbon
    {
        return $request->filled('date')
            ? Carbon::parse($request->string('date')->toString())
            : today();
    }

    private function backToDay(Carbon $date): RedirectResponse
    {
        return redirect()->route('supplier-hisabs.index', ['date' => $date->toDateString()]);
    }

    /**
     * Weights read as 4.5 rather than 4.500 at the counter, so trailing zeros go.
     */
    private function weight(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.') ?: '0';
    }
}
