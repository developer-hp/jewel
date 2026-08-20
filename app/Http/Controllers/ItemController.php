<?php

namespace App\Http\Controllers;

use App\Http\Requests\ItemRequest;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\MakingCharge;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\StoneMaster;
use App\Models\Supplier;
use App\Services\ItemCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class ItemController extends Controller implements HasMiddleware
{
    public function __construct(private readonly ItemCalculator $calculator) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:item.view', only: ['index', 'show']),
            new Middleware('permission:item.create', only: ['create', 'store']),
            new Middleware('permission:item.edit', only: ['edit', 'update']),
            new Middleware('permission:item.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->data($request);
        }

        return view('items.index', [
            'groups' => ItemGroup::ordered()->pluck('name', 'id'),
            'metalTypes' => MetalType::ordered()->pluck('name', 'id'),
            'suppliers' => Supplier::ordered()->get()
                ->mapWithKeys(fn (Supplier $s) => [$s->id => $s->label()]),
        ]);
    }

    private function data(Request $request): JsonResponse
    {
        $query = Item::query()
            ->select('items.*')
            ->with(['itemGroup', 'metalType', 'purity', 'makingCharge', 'supplier'])
            ->when($request->filled('item_group_id'), fn ($q) => $q->where('item_group_id', $request->integer('item_group_id')))
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('metal_type_id'), fn ($q) => $q->where('metal_type_id', $request->integer('metal_type_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->string('status')->toString() === 'active'));

        return DataTables::eloquent($query)
            ->addColumn('photo', fn (Item $item) => view('items.partials.photo-thumb', compact('item'))->render())
            ->editColumn('code', fn (Item $item) => '<code>'.e($item->code).'</code>')
            ->addColumn('group', fn (Item $item) => e($item->itemGroup?->name ?? '—'))
            ->editColumn('huid', fn (Item $item) => $item->huid ? '<code>'.e($item->huid).'</code>' : '<span class="text-muted">—</span>')
            ->addColumn('supplier', fn (Item $item) => e($item->supplier?->short_name ?: ($item->supplier?->name ?? '—')))
            ->addColumn('metal', fn (Item $item) => view('items.partials.metal-cell', compact('item'))->render())
            ->addColumn('weights', fn (Item $item) => view('items.partials.weights-cell', compact('item'))->render())
            ->addColumn('making', fn (Item $item) => e($item->makingCharge?->code ?? '—'))
            ->addColumn('status', fn (Item $item) => view('components.status-badge', ['active' => $item->is_active])->render())
            ->addColumn('action', fn (Item $item) => view('items.partials.actions', compact('item'))->render())
            ->filterColumn('group', fn ($q, $keyword) => $q->whereRelation('itemGroup', 'name', 'like', "%{$keyword}%"))
            ->filterColumn('supplier', function ($q, $keyword) {
                $q->whereHas('supplier', fn ($sub) => $sub->where('name', 'like', "%{$keyword}%")
                    ->orWhere('short_name', 'like', "%{$keyword}%"));
            })
            ->orderColumn('weights', 'net_weight $1')
            ->orderColumn('status', 'is_active $1')
            ->rawColumns(['photo', 'code', 'huid', 'metal', 'weights', 'status', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        return view('items.create', $this->formData() + [
            'item' => new Item(['is_active' => true, 'other_deduction' => 0]),
            'stoneRows' => collect(),
            'diamondRows' => collect(),
        ]);
    }

    public function store(ItemRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $item = DB::transaction(function () use ($data) {
            $group = ItemGroup::findOrFail($data['item_group_id']);

            $item = new Item($data);
            // Reserved inside the transaction so the row lock holds; a rollback
            // releases the number too.
            $item->code = $group->nextItemCode();
            $item->net_weight = $data['gross_weight'];
            $item->save();

            $this->syncStones($item, $data['stones'] ?? []);
            $this->calculator->recalculate($item);

            return $item;
        });

        return redirect()->route('items.index')
            ->with('success', "Item \"{$item->code}\" has been created.");
    }

    public function show(Item $item): View
    {
        return view('items.show', [
            'item' => $item->load(['itemGroup', 'supplier', 'metalType', 'purity.metalType', 'makingCharge', 'itemStones.stoneMaster']),
        ]);
    }

    public function edit(Item $item): View
    {
        $item->load('itemStones.stoneMaster');

        return view('items.edit', $this->formData() + [
            'item' => $item,
            'stoneRows' => $item->itemStones->where('kind', StoneMaster::KIND_STONE)->values(),
            'diamondRows' => $item->itemStones->where('kind', StoneMaster::KIND_DIAMOND)->values(),
        ]);
    }

    public function update(ItemRequest $request, Item $item): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($item, $data) {
            // The code and its group are fixed once issued — reassigning the group
            // would leave the code inconsistent with its prefix.
            unset($data['item_group_id']);

            $item->update($data);

            $this->syncStones($item, $data['stones'] ?? []);
            $this->calculator->recalculate($item);
        });

        return redirect()->route('items.index')
            ->with('success', "Item \"{$item->code}\" has been updated.");
    }

    public function destroy(Item $item): RedirectResponse
    {
        $code = $item->code;
        $item->delete();

        return redirect()->route('items.index')
            ->with('success', "Item \"{$code}\" has been deleted.");
    }

    /**
     * Replace the item's stone/diamond rows wholesale — simpler and safer than
     * diffing, and the rows carry no external references.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncStones(Item $item, array $rows): void
    {
        $item->itemStones()->delete();

        if ($rows === []) {
            return;
        }

        $masters = StoneMaster::whereIn('id', array_column($rows, 'stone_master_id'))->get()->keyBy('id');

        $item->itemStones()->createMany($this->calculator->buildStoneRows($rows, $masters));
    }

    /**
     * Shared select options for the create/edit form.
     *
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'groups' => ItemGroup::active()->ordered()->get(),
            'suppliers' => Supplier::active()->ordered()->get(),
            'metalTypes' => MetalType::active()->ordered()->get(),
            // Grouped by metal type so the form can filter the purity dropdown client-side.
            'puritiesByMetal' => Purity::active()->ordered()->get()
                ->groupBy('metal_type_id')
                ->map(fn ($group) => $group->map(fn (Purity $p) => ['id' => $p->id, 'name' => $p->name])->values()),
            // Today's per-gram rate per purity, so the form's live summary can price
            // the piece without another round trip.
            'purityRates' => Purity::active()->get()
                ->mapWithKeys(fn (Purity $p) => [$p->id => (float) ($p->ratePerGramOn() ?? 0)]),
            'makingCharges' => MakingCharge::active()->orderBy('code')->get(),
            'stoneMasters' => StoneMaster::active()->kind(StoneMaster::KIND_STONE)->orderBy('name')->get(),
            'diamondMasters' => StoneMaster::active()->kind(StoneMaster::KIND_DIAMOND)->orderBy('name')->get(),
        ];
    }
}
