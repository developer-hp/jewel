<?php

namespace App\Http\Controllers;

use App\Http\Requests\OgEstimateRequest;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\OgEstimate;
use App\Models\OrderForm;
use App\Models\SalesPerson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class OgEstimateController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:og_estimate.view', only: ['index']),
            new Middleware('permission:og_estimate.create', only: ['create', 'store', 'copy']),
            new Middleware('permission:og_estimate.edit', only: ['edit', 'update']),
            new Middleware('permission:og_estimate.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->data();
        }

        return view('og-estimates.index');
    }

    private function data(): JsonResponse
    {
        $query = OgEstimate::query()
            ->select('og_estimates.*')
            ->with(['lines', 'orderForm']);

        $prefix = OgEstimate::refPrefix();

        return DataTables::eloquent($query)
            ->addColumn('select', fn (OgEstimate $e) => view('og-estimates.partials.select-cell', ['estimate' => $e])->render())
            ->addColumn('ref', fn (OgEstimate $e) => '<strong>'.e(trim($prefix.' '.$e->ref_no)).'</strong>')
            ->addColumn('customer', fn (OgEstimate $e) => e($e->customer_name))
            ->addColumn('contact', fn (OgEstimate $e) => e($e->contact_no ?: '—'))
            ->editColumn('estimate_date', fn (OgEstimate $e) => $e->estimate_date->format('d-m-Y'))
            ->addColumn('order_ref', fn (OgEstimate $e) => e($e->orderReferenceLabel()))
            ->addColumn('fine', fn (OgEstimate $e) => number_format($e->totals()->fine, 3))
            ->addColumn('value', fn (OgEstimate $e) => number_format($e->totals()->value, 2))
            ->addColumn('action', fn (OgEstimate $e) => view('og-estimates.partials.actions', ['estimate' => $e])->render())
            ->filterColumn('ref', fn ($q, $keyword) => $q->where('ref_no', 'like', '%'.trim($keyword, $prefix.' ').'%'))
            ->filterColumn('customer', fn ($q, $keyword) => $q->where('customer_name', 'like', "%{$keyword}%"))
            ->filterColumn('contact', fn ($q, $keyword) => $q->where('contact_no', 'like', "%{$keyword}%"))
            ->orderColumn('ref', 'ref_no $1')
            ->orderColumn('customer', 'customer_name $1')
            ->rawColumns(['select', 'ref', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        return view('og-estimates.create', $this->formData() + [
            'estimate' => new OgEstimate(['estimate_date' => today()]),
            'lines' => collect(),
            'nextRef' => trim(OgEstimate::refPrefix().' '.AppSetting::current()->og_estimate_next_ref_no),
        ]);
    }

    public function store(OgEstimateRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $estimate = DB::transaction(function () use ($data, $request) {
            $estimate = new OgEstimate($data);
            // Reserved inside the transaction so the settings row lock holds.
            $estimate->ref_no = OgEstimate::nextRefNo();
            $estimate->created_by = $request->user()->id;
            $this->applySalesPerson($estimate, $data['sales_person_id']);
            $estimate->save();

            $estimate->lines()->createMany($data['lines']);
            $this->linkCustomer($estimate);

            return $estimate;
        });

        if ($request->boolean('print_after_save')) {
            return redirect()->route('og-estimates.index')
                ->with('printAfterSave', $estimate->id)
                ->with('success', "Estimate {$estimate->reference()} saved — printing.");
        }

        return redirect()->route('og-estimates.index')
            ->with('success', "Estimate {$estimate->reference()} has been created.");
    }

    public function edit(OgEstimate $ogEstimate): View
    {
        return view('og-estimates.edit', $this->formData() + [
            'estimate' => $ogEstimate,
            'lines' => $ogEstimate->lines()->get(),
            'nextRef' => $ogEstimate->reference(),
        ]);
    }

    public function update(OgEstimateRequest $request, OgEstimate $ogEstimate): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($ogEstimate, $data) {
            $this->applySalesPerson($ogEstimate, $data['sales_person_id']);
            $ogEstimate->update($data);

            // Replaced wholesale — the lines carry no outside references.
            $ogEstimate->lines()->delete();
            $ogEstimate->lines()->createMany($data['lines']);

            $this->linkCustomer($ogEstimate);
        });

        return redirect()->route('og-estimates.index')
            ->with('success', "Estimate {$ogEstimate->reference()} has been updated.");
    }

    /**
     * Clone onto a fresh reference. The counter runs on, so the original keeps its
     * number and the copy is a new document rather than a second copy of one.
     */
    public function copy(OgEstimate $ogEstimate): RedirectResponse
    {
        $copy = DB::transaction(function () use ($ogEstimate) {
            $copy = $ogEstimate->replicate(['ref_no', 'created_by']);
            $copy->ref_no = OgEstimate::nextRefNo();
            $copy->estimate_date = today();
            $copy->created_by = auth()->id();
            $copy->save();

            foreach ($ogEstimate->lines as $line) {
                $copy->lines()->create($line->only([
                    'description', 'gross_weight', 'net_weight', 'touch_percent', 'rate', 'sort_order',
                ]));
            }

            return $copy;
        });

        return redirect()->route('og-estimates.edit', $copy)
            ->with('success', "Copied {$ogEstimate->reference()} to {$copy->reference()}.");
    }

    public function destroy(OgEstimate $ogEstimate): RedirectResponse
    {
        $reference = $ogEstimate->reference();

        $ogEstimate->lines()->delete();
        $ogEstimate->delete();

        return redirect()->route('og-estimates.index')
            ->with('success', "Estimate {$reference} has been deleted.");
    }

    /**
     * The name is snapshotted, so a later rename in the master cannot rewrite an
     * estimate that has already printed.
     */
    private function applySalesPerson(OgEstimate $estimate, int $id): void
    {
        $estimate->sales_person_id = $id;
        $estimate->sales_person_name = SalesPerson::find($id)?->name;
    }

    /**
     * Tie the estimate to the customer register, adding them on first contact.
     */
    private function linkCustomer(OgEstimate $estimate): void
    {
        $customer = Customer::rememberByPhone($estimate->contact_no, $estimate->customer_name, $estimate->address);

        if ($customer && $estimate->customer_id !== $customer->id) {
            $estimate->forceFill(['customer_id' => $customer->id])->save();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'salesPersons' => SalesPerson::active()->ordered()->get(['id', 'name', 'city']),
            'orderForms' => OrderForm::query()->orderByDesc('ref_no')->get(['id', 'ref_no', 'customer_name', 'contact_no']),
        ];
    }
}
