<?php

namespace App\Http\Controllers;

use App\Http\Requests\InternalStockEntryRequest;
use App\Models\InternalStock;
use App\Models\InternalStockEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class InternalStockEntryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:internal_stock_entry.view', only: ['index']),
            new Middleware('permission:internal_stock_entry.create', only: ['create', 'store']),
            new Middleware('permission:internal_stock_entry.edit', only: ['edit', 'update']),
            new Middleware('permission:internal_stock_entry.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->data($request);
        }

        return view('internal-stock-entries.index', [
            // One query for every card, rather than one per pot.
            'stocks' => InternalStock::active()->withBalance()->ordered()->get(),
        ]);
    }

    private function data(Request $request): JsonResponse
    {
        $query = InternalStockEntry::query()
            ->select('internal_stock_entries.*')
            ->with('internalStock')
            ->when($request->filled('internal_stock_id'), fn ($q) => $q->where('internal_stock_id', $request->integer('internal_stock_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->toString()));

        return DataTables::eloquent($query)
            ->addColumn('type_label', fn (InternalStockEntry $entry) => e($entry->typeLabel()))
            ->addColumn('stock', fn (InternalStockEntry $entry) => e($entry->internalStock?->name ?? '—'))
            // The two weight columns are one number read through its direction.
            ->addColumn('gold_in', fn (InternalStockEntry $entry) => $entry->isOutgoing() ? '' : $this->weight($entry->weight))
            ->addColumn('gold_out', fn (InternalStockEntry $entry) => $entry->isOutgoing() ? $this->weight($entry->weight) : '')
            ->addColumn('action', fn (InternalStockEntry $entry) => view('internal-stock-entries.partials.actions', ['entry' => $entry])->render())
            ->filterColumn('stock', fn ($q, $keyword) => $q->whereRelation('internalStock', 'name', 'like', "%{$keyword}%"))
            ->orderColumn('stock', 'internal_stock_id $1')
            ->orderColumn('gold_in', 'weight $1')
            ->orderColumn('gold_out', 'weight $1')
            ->rawColumns(['action'])
            ->toJson();
    }

    public function create(Request $request): View
    {
        return view('internal-stock-entries.create', $this->formData() + [
            'entry' => new InternalStockEntry([
                'type' => InternalStockEntry::TYPE_IN,
                'internal_stock_id' => $request->integer('internal_stock_id') ?: null,
            ]),
        ]);
    }

    public function store(InternalStockEntryRequest $request): RedirectResponse
    {
        $entry = new InternalStockEntry($request->validated());
        $entry->created_by = $request->user()->id;
        $entry->save();

        return redirect()->route('internal-stock-entries.index')
            ->with('success', "{$entry->typeLabel()} of {$this->weight($entry->weight)} GM recorded against "
                .$entry->internalStock->name.'.');
    }

    public function edit(InternalStockEntry $entry): View
    {
        return view('internal-stock-entries.edit', $this->formData() + ['entry' => $entry]);
    }

    public function update(InternalStockEntryRequest $request, InternalStockEntry $entry): RedirectResponse
    {
        $entry->update($request->validated());

        return redirect()->route('internal-stock-entries.index')
            ->with('success', 'Stock entry has been updated.');
    }

    public function destroy(InternalStockEntry $entry): RedirectResponse
    {
        $name = $entry->internalStock?->name ?? 'internal stock';
        $entry->delete();

        return redirect()->route('internal-stock-entries.index')
            ->with('success', "Stock entry for {$name} has been deleted.");
    }

    /**
     * Weights read as 4.5 rather than 4.500 at the counter, so trailing zeros go.
     */
    private function weight(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.') ?: '0';
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            // Carries each pot's balance, so the form can show it beside the picker.
            'stocks' => InternalStock::active()->withBalance()->ordered()->get(),
            'types' => InternalStockEntry::TYPES,
        ];
    }
}
