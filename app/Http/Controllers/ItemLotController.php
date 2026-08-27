<?php

namespace App\Http\Controllers;

use App\Http\Requests\ItemLotRequest;
use App\Models\ItemGroup;
use App\Models\ItemLot;
use App\Models\MakingCharge;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\Supplier;
use App\Services\ItemPhotoStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class ItemLotController extends Controller implements HasMiddleware
{
    public function __construct(private readonly ItemPhotoStore $photos) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:item_lot.view', only: ['index', 'show']),
            new Middleware('permission:item_lot.create', only: ['create', 'store']),
            new Middleware('permission:item_lot.edit', only: ['edit', 'update']),
            new Middleware('permission:item_lot.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->data($request);
        }

        return view('lots.index', [
            'statuses' => ItemLot::STATUSES,
            'suppliers' => Supplier::ordered()->get()->mapWithKeys(fn (Supplier $s) => [$s->id => $s->label()]),
        ]);
    }

    private function data(Request $request): JsonResponse
    {
        // select() before withCount() — the other order discards the count subquery.
        $query = ItemLot::query()
            ->select('item_lots.*')
            ->withCount('items')
            ->with(['supplier', 'lines.itemGroup'])
            ->when($request->filled('status'), fn ($q) => $q->status($request->string('status')->toString()))
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->integer('supplier_id')));

        return DataTables::eloquent($query)
            ->editColumn('code', fn (ItemLot $lot) => '<code>'.e($lot->code).'</code>')
            ->editColumn('lot_date', fn (ItemLot $lot) => $lot->lot_date->format('d-m-Y'))
            ->addColumn('supplier', fn (ItemLot $lot) => e($lot->supplier?->short_name ?: ($lot->supplier?->name ?? 'In-house')))
            ->addColumn('groups', fn (ItemLot $lot) => view('lots.partials.groups-cell', compact('lot'))->render())
            ->addColumn('progress', fn (ItemLot $lot) => view('lots.partials.progress-cell', compact('lot'))->render())
            ->addColumn('weight', fn (ItemLot $lot) => view('lots.partials.weight-cell', compact('lot'))->render())
            ->addColumn('status_badge', fn (ItemLot $lot) => view('lots.partials.status-cell', compact('lot'))->render())
            ->addColumn('action', fn (ItemLot $lot) => view('lots.partials.actions', compact('lot'))->render())
            ->filterColumn('supplier', function ($q, $keyword) {
                $q->whereHas('supplier', fn ($sub) => $sub->where('name', 'like', "%{$keyword}%")
                    ->orWhere('short_name', 'like', "%{$keyword}%"));
            })
            ->orderColumn('status_badge', 'status $1')
            ->rawColumns(['code', 'groups', 'progress', 'weight', 'status_badge', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        return view('lots.create', $this->formData() + [
            'lot' => new ItemLot(['lot_date' => today()]),
            'lines' => collect(),
        ]);
    }

    public function store(ItemLotRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $lot = DB::transaction(function () use ($data) {
            $lot = new ItemLot($data);
            // The code is derived from the id, so it lands on the second write.
            $lot->save();
            $lot->assignCode();

            $lot->lines()->createMany($data['lines']);

            return $lot;
        });

        if ($request->hasFile('photo')) {
            $this->photos->put($lot, $request->file('photo'));
        }

        return redirect()->route('lots.show', $lot)
            ->with('success', "Lot \"{$lot->code}\" has been created.");
    }

    public function show(ItemLot $lot): View
    {
        return view('lots.show', [
            'lot' => $lot->load(['supplier', 'metalType', 'purity', 'makingCharge', 'lines.itemGroup']),
            'remaining' => $lot->remainingByGroup(),
            'items' => $lot->items()->with('itemGroup')->orderBy('id')->get(),
        ]);
    }

    public function edit(ItemLot $lot): View
    {
        return view('lots.edit', $this->formData() + [
            'lot' => $lot,
            'lines' => $lot->lines()->with('itemGroup')->get(),
        ]);
    }

    public function update(ItemLotRequest $request, ItemLot $lot): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($lot, $data) {
            $lot->update($data);

            // Replace the lines wholesale; the request has already refused any change
            // that would strand items already entered.
            $lot->lines()->delete();
            $lot->lines()->createMany($data['lines']);
        });

        if ($request->hasFile('photo')) {
            $this->photos->put($lot, $request->file('photo'));
        } elseif ($request->boolean('remove_photo')) {
            $this->photos->remove($lot);
        }

        // Changing the tag count can flip a lot into or out of finished.
        $lot->refresh()->refreshStatus();

        return redirect()->route('lots.show', $lot)
            ->with('success', "Lot \"{$lot->code}\" has been updated.");
    }

    public function destroy(ItemLot $lot): RedirectResponse
    {
        if ($lot->items()->exists()) {
            return back()->with('error', "\"{$lot->code}\" has items entered against it and cannot be deleted.");
        }

        $code = $lot->code;

        if ($lot->hasPhoto()) {
            $this->photos->remove($lot);
        }

        $lot->delete();

        return redirect()->route('lots.index')->with('success', "Lot \"{$code}\" has been deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'groups' => ItemGroup::active()->ordered()->get(),
            'suppliers' => Supplier::active()->ordered()->get(),
            'metalTypes' => MetalType::active()->ordered()->get(),
            'purities' => Purity::active()->with('metalType')->ordered()->get(),
            'makingCharges' => MakingCharge::active()->orderBy('code')->get(),
        ];
    }
}
