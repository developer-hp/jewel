<?php

namespace App\Http\Controllers;

use App\Http\Requests\ItemEstimateRequest;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\ItemEstimate;
use App\Models\ItemEstimateLine;
use App\Models\OgEstimate;
use App\Models\OrderForm;
use App\Models\SalesPerson;
use App\Models\StoneMaster;
use App\Services\ItemCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class ItemEstimateController extends Controller implements HasMiddleware
{
    public function __construct(private readonly ItemCalculator $calculator) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:item_estimate.view', only: ['index']),
            new Middleware('permission:item_estimate.create', only: ['create', 'store', 'fromOrder']),
            new Middleware('permission:item_estimate.edit', only: ['edit', 'update']),
            new Middleware('permission:item_estimate.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->data();
        }

        return view('item-estimates.index');
    }

    private function data(): JsonResponse
    {
        $query = ItemEstimate::query()
            ->select('item_estimates.*')
            ->with(['lines.stones', 'ogEstimate']);

        $prefix = ItemEstimate::refPrefix();

        return DataTables::eloquent($query)
            ->addColumn('select', fn (ItemEstimate $e) => view('item-estimates.partials.select-cell', ['estimate' => $e])->render())
            ->addColumn('ref', fn (ItemEstimate $e) => '<strong>'.e(trim($prefix.' '.$e->ref_no)).'</strong>')
            ->editColumn('estimate_date', fn (ItemEstimate $e) => $e->estimate_date->format('d-m-Y'))
            ->addColumn('customer', fn (ItemEstimate $e) => e($e->customer_name))
            ->addColumn('contact', fn (ItemEstimate $e) => e($e->contact_no ?: '—'))
            ->addColumn('net', fn (ItemEstimate $e) => number_format($e->totals()->net, 3))
            ->addColumn('total', fn (ItemEstimate $e) => number_format($e->summary()->total, 2))
            ->addColumn('og_ref', fn (ItemEstimate $e) => e($e->ogEstimate?->reference() ?? '—'))
            ->addColumn('action', fn (ItemEstimate $e) => view('item-estimates.partials.actions', ['estimate' => $e])->render())
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
        $settings = AppSetting::current();

        return view('item-estimates.create', $this->formData() + [
            'estimate' => new ItemEstimate([
                'estimate_date' => today(),
                'gst_percent' => $settings->gst_percent,
            ]),
            'lines' => collect(),
            'nextRef' => trim(ItemEstimate::refPrefix().' '.$settings->item_estimate_next_ref_no),
        ]);
    }

    public function store(ItemEstimateRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $estimate = DB::transaction(function () use ($data, $request) {
            $estimate = new ItemEstimate($data);
            // Reserved inside the transaction so the settings row lock holds.
            $estimate->ref_no = ItemEstimate::nextRefNo();
            $estimate->created_by = $request->user()->id;
            // Snapshotted, so changing the rate later leaves this document alone.
            $estimate->gst_percent = $data['gst_enabled'] ? (float) AppSetting::current()->gst_percent : 0;
            $this->applySalesPerson($estimate, $data['sales_person_id']);
            $estimate->save();

            $this->syncLines($estimate, $data['lines']);
            $this->linkCustomer($estimate);

            return $estimate;
        });

        if ($request->boolean('print_after_save')) {
            return redirect()->route('item-estimates.index')
                ->with('printAfterSave', $estimate->id)
                ->with('success', "Estimate {$estimate->reference()} saved — printing.");
        }

        return redirect()->route('item-estimates.index')
            ->with('success', "Estimate {$estimate->reference()} has been created.");
    }

    public function edit(ItemEstimate $itemEstimate): View
    {
        return view('item-estimates.edit', $this->formData() + [
            'estimate' => $itemEstimate,
            'lines' => $itemEstimate->lines()->with(['stones', 'item'])->get(),
            'nextRef' => $itemEstimate->reference(),
        ]);
    }

    public function update(ItemEstimateRequest $request, ItemEstimate $itemEstimate): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($itemEstimate, $data) {
            $this->applySalesPerson($itemEstimate, $data['sales_person_id']);
            $itemEstimate->update($data);
            $itemEstimate->forceFill([
                'gst_percent' => $data['gst_enabled'] ? (float) AppSetting::current()->gst_percent : 0,
            ])->save();

            // Replaced wholesale — the lines carry no outside references.
            $itemEstimate->lines->each(fn (ItemEstimateLine $line) => $line->stones()->delete());
            $itemEstimate->lines()->delete();

            $this->syncLines($itemEstimate, $data['lines']);
            $this->linkCustomer($itemEstimate);
        });

        return redirect()->route('item-estimates.index')
            ->with('success', "Estimate {$itemEstimate->reference()} has been updated.");
    }

    public function destroy(ItemEstimate $itemEstimate): RedirectResponse
    {
        $reference = $itemEstimate->reference();

        $itemEstimate->lines->each(fn (ItemEstimateLine $line) => $line->stones()->delete());
        $itemEstimate->lines()->delete();
        $itemEstimate->delete();

        return redirect()->route('item-estimates.index')
            ->with('success', "Estimate {$reference} has been deleted.");
    }

    /**
     * The lines an order would produce, for the "load from order" control.
     *
     * Returned rather than saved: the clerk sees them in the grid and may change
     * anything before the estimate is written.
     */
    public function fromOrder(OrderForm $orderForm): JsonResponse
    {
        $orderForm->load(['lines.stones', 'lines.purity', 'lines.sourceItem']);

        return response()->json([
            'customer_name' => $orderForm->customer_name,
            'contact_no' => $orderForm->contact_no,
            'address' => $orderForm->address,
            'lines' => $orderForm->lines->map(function ($line) {
                // The pinned rate where the line has one, otherwise the day's rate for
                // its purity — both quoted per ten grams here.
                $perGram = $line->isRateFixed()
                    ? (float) $line->fixed_rate_per_gram
                    : (float) ($line->purity?->ratePerGramOn() ?? 0);

                return [
                    'item_id' => $line->source_item_id,
                    'description' => $line->description,
                    'gross_weight' => $line->grossFromStones(),
                    'rate' => round($perGram * 10, 2),
                    'labour_amount' => (float) $line->lc_amount,
                    'labour_type' => $line->lc_type,
                    'oc_amount' => (float) $line->oc_amount,
                    'stones' => $line->stones->map(fn ($stone) => [
                        'stone_master_id' => $stone->stone_master_id,
                        'kind' => $stone->kind,
                        'pieces' => $stone->pieces,
                        'weight_carat' => (float) $stone->weight_carat,
                        'weight_grams' => (float) $stone->weight_grams,
                        'rate_unit' => $stone->rate_unit,
                        'rate' => (float) $stone->rate,
                        'amount' => (float) $stone->amount,
                    ])->values(),
                ];
            })->values(),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncLines(ItemEstimate $estimate, array $rows): void
    {
        $masters = StoneMaster::whereIn(
            'id',
            collect($rows)->pluck('stones')->filter()->flatten(1)->pluck('stone_master_id')->unique()
        )->get()->keyBy('id');

        foreach ($rows as $row) {
            $line = $estimate->lines()->create(collect($row)->except('stones')->all());

            foreach ($row['stones'] as $stone) {
                $master = $masters->get((int) $stone['stone_master_id']);

                if (! $master) {
                    continue;
                }

                $pieces = (int) ($stone['pieces'] ?? 0);
                $grams = (float) ($stone['weight_grams'] ?? 0);
                $carat = $grams > 0 ? round($grams / 0.2, 3) : 0.0;
                $rate = ($stone['rate'] ?? null) !== null && $stone['rate'] !== ''
                    ? (float) $stone['rate']
                    : (float) $master->default_rate;

                $line->stones()->create([
                    'stone_master_id' => $master->id,
                    'kind' => $master->kind,
                    'pieces' => $pieces,
                    'weight_carat' => $carat,
                    'weight_grams' => $grams,
                    'rate_unit' => $master->rate_unit,
                    'rate' => round($rate, 2),
                    // The one rule for what a stone row is worth, per its unit.
                    'amount' => $this->calculator->lineAmount($master->rate_unit, $rate, $pieces, $carat, $grams),
                    // Carried from the piece, not assumed: a stone the item does not
                    // deduct must not shrink the net weight on its quote either.
                    'deduct_from_gross' => (bool) ($stone['deduct_from_gross'] ?? true),
                ]);
            }
        }
    }

    private function applySalesPerson(ItemEstimate $estimate, int $id): void
    {
        $estimate->sales_person_id = $id;
        $estimate->sales_person_name = SalesPerson::find($id)?->name;
    }

    private function linkCustomer(ItemEstimate $estimate): void
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
            // The live rate, for the tick's label and the running summary. What a
            // saved estimate uses is its own snapshot, not this.
            'gstPercent' => (float) AppSetting::current()->gst_percent,
            'salesPersons' => SalesPerson::active()->ordered()->get(['id', 'name', 'city']),
            'orderForms' => OrderForm::query()->orderByDesc('ref_no')->get(['id', 'ref_no', 'customer_name']),
            'ogEstimates' => OgEstimate::query()->with('lines')->orderByDesc('ref_no')->limit(200)->get(),
            'stoneMasters' => StoneMaster::active()->orderBy('kind')->orderBy('name')->get(),
        ];
    }
}
