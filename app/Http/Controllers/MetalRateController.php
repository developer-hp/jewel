<?php

namespace App\Http\Controllers;

use App\Http\Requests\MetalRateRequest;
use App\Models\MetalRate;
use App\Models\Purity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class MetalRateController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:metal_rate.view', only: ['index', 'today']),
            new Middleware('permission:metal_rate.create', only: ['create', 'store', 'storeToday']),
            new Middleware('permission:metal_rate.edit', only: ['edit', 'update', 'storeToday']),
            new Middleware('permission:metal_rate.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->data($request);
        }

        return view('rates.index', [
            'purities' => Purity::with('metalType')->ordered()->get(),
        ]);
    }

    private function data(Request $request): JsonResponse
    {
        $query = MetalRate::query()
            ->select('metal_rates.*')
            ->with(['purity.metalType', 'createdBy'])
            ->when($request->filled('purity_id'), fn ($q) => $q->where('purity_id', $request->integer('purity_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('effective_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('effective_date', '<=', $request->date('to')));

        return DataTables::eloquent($query)
            ->addColumn('purity', fn (MetalRate $rate) => e($rate->purity?->label() ?? '—'))
            ->editColumn('effective_date', fn (MetalRate $rate) => $rate->effective_date->format('d M Y'))
            ->addColumn('entered', fn (MetalRate $rate) => number_format((float) $rate->rate, 2).' / '.rtrim(rtrim(number_format((float) $rate->per_grams, 3, '.', ''), '0'), '.').' g')
            ->editColumn('rate_per_gram', fn (MetalRate $rate) => '<strong>'.number_format((float) $rate->rate_per_gram, 4).'</strong>')
            ->addColumn('by', fn (MetalRate $rate) => e($rate->createdBy?->username ?? '—'))
            ->addColumn('action', fn (MetalRate $rate) => view('rates.partials.actions', compact('rate'))->render())
            ->filterColumn('purity', fn ($q, $keyword) => $q->whereRelation('purity', 'name', 'like', "%{$keyword}%"))
            ->rawColumns(['rate_per_gram', 'action'])
            ->orderColumn('purity', 'purity_id $1')
            ->toJson();
    }

    /**
     * The screen the shop actually uses each morning: every active purity listed
     * with one rate box, saved in a single submit.
     */
    public function today(Request $request): View
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->string('date')->toString())
            : today();

        $purities = Purity::query()
            ->active()
            ->with('metalType')
            ->whereRelation('metalType', 'is_active', true)
            ->ordered()
            ->get()
            ->sortBy(fn (Purity $purity) => [$purity->metalType->sort_order, $purity->sort_order])
            ->groupBy(fn (Purity $purity) => $purity->metalType->name);

        $existing = MetalRate::whereDate('effective_date', $date)->get()->keyBy('purity_id');

        return view('rates.today', compact('purities', 'existing', 'date'));
    }

    /**
     * Upsert one rate per purity for the chosen date. Blank boxes are skipped so a
     * partial entry does not wipe rates already recorded that day.
     */
    public function storeToday(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'rates' => ['array'],
            'rates.*.rate' => ['nullable', 'numeric', 'min:0'],
            'rates.*.per_grams' => ['required_with:rates.*.rate', 'nullable', 'numeric', 'min:0.001'],
        ]);

        $date = Carbon::parse($validated['date'])->toDateString();
        $saved = 0;

        foreach ($validated['rates'] ?? [] as $purityId => $row) {
            if (blank($row['rate'] ?? null)) {
                continue;
            }

            if (! Purity::whereKey($purityId)->exists()) {
                continue;
            }

            $rate = MetalRate::firstOrNew([
                'purity_id' => $purityId,
                'effective_date' => $date,
            ]);

            $rate->fill([
                'rate' => $row['rate'],
                'per_grams' => $row['per_grams'],
                'created_by' => $request->user()->id,
            ])->save();

            $saved++;
        }

        return redirect()->route('rates.today', ['date' => $date])
            ->with('success', $saved > 0
                ? "Saved {$saved} rate(s) for ".Carbon::parse($date)->format('d M Y').'.'
                : 'No rates were entered.');
    }

    public function create(): View
    {
        return view('rates.create', [
            'rate' => new MetalRate(['effective_date' => today(), 'per_grams' => 10]),
            'purities' => $this->purityOptions(),
        ]);
    }

    public function store(MetalRateRequest $request): RedirectResponse
    {
        MetalRate::create($request->validated() + ['created_by' => $request->user()->id]);

        return redirect()->route('rates.index')->with('success', 'Rate has been recorded.');
    }

    public function edit(MetalRate $rate): View
    {
        return view('rates.edit', [
            'rate' => $rate,
            'purities' => $this->purityOptions(),
        ]);
    }

    public function update(MetalRateRequest $request, MetalRate $rate): RedirectResponse
    {
        $rate->update($request->validated());

        return redirect()->route('rates.index')->with('success', 'Rate has been updated.');
    }

    public function destroy(MetalRate $rate): RedirectResponse
    {
        $rate->delete();

        return redirect()->route('rates.index')->with('success', 'Rate has been deleted.');
    }

    /**
     * @return Collection<int, string>
     */
    private function purityOptions()
    {
        return Purity::active()
            ->with('metalType')
            ->ordered()
            ->get()
            ->mapWithKeys(fn (Purity $purity) => [$purity->id => $purity->label()]);
    }
}
