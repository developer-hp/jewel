<?php

namespace App\Http\Controllers;

use App\Http\Requests\ItemRequest;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\MakingCharge;
use App\Models\MetalType;
use App\Models\OrderForm;
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
            new Middleware('permission:item.view', only: ['index', 'show', 'lookup']),
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
            // orderFormLine.orderForm feeds the Order No column; without it that
            // column is an N+1 across the page.
            ->with(['itemGroup', 'metalType', 'purity', 'makingCharge', 'supplier', 'orderFormLine.orderForm'])
            ->when($request->filled('item_group_id'), fn ($q) => $q->where('item_group_id', $request->integer('item_group_id')))
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('metal_type_id'), fn ($q) => $q->where('metal_type_id', $request->integer('metal_type_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->string('status')->toString() === 'active'))
            // Sold pieces are out of the shop, so they are out of the listing
            // unless someone asks for them. "all" shows both.
            ->when($request->string('stock')->toString() !== 'all',
                fn ($q) => $request->string('stock')->toString() === 'sold' ? $q->sold() : $q->inStock());

        // Resolved once: refPrefix() reads the settings singleton, and inside a
        // per-row closure that would be one lookup per item on the page.
        $orderPrefix = OrderForm::refPrefix();

        return DataTables::eloquent($query)
            ->editColumn('code', fn (Item $item) => '<code>'.e($item->code).'</code>')
            ->addColumn('group', fn (Item $item) => e($item->itemGroup?->name ?? '—'))
            ->addColumn('supplier', fn (Item $item) => e($item->supplier?->short_name ?: ($item->supplier?->name ?? '—')))
            ->addColumn('metal', fn (Item $item) => view('items.partials.metal-cell', compact('item'))->render())
            ->addColumn('weights', fn (Item $item) => view('items.partials.weights-cell', compact('item'))->render())
            ->addColumn('making', fn (Item $item) => e($item->makingCharge?->code ?? '—'))
            ->addColumn('order_no', fn (Item $item) => $this->orderCell($item, $orderPrefix))
            ->addColumn('action', fn (Item $item) => view('items.partials.actions', compact('item'))->render())
            // With the Status column gone, an inactive piece would otherwise look
            // exactly like a live one; the row is muted instead.
            ->setRowClass(fn (Item $item) => $item->is_active ? '' : 'row-inactive')
            // HUID no longer has a column of its own, but it is what a piece gets
            // looked up by, so the code search covers it too.
            ->filterColumn('code', function ($q, $keyword) {
                $q->where(fn ($sub) => $sub->where('code', 'like', "%{$keyword}%")
                    ->orWhere('huid', 'like', "%{$keyword}%"));
            })
            ->filterColumn('group', fn ($q, $keyword) => $q->whereRelation('itemGroup', 'name', 'like', "%{$keyword}%"))
            ->filterColumn('supplier', function ($q, $keyword) {
                $q->whereHas('supplier', fn ($sub) => $sub->where('name', 'like', "%{$keyword}%")
                    ->orWhere('short_name', 'like', "%{$keyword}%"));
            })
            ->filterColumn('order_no', function ($q, $keyword) use ($orderPrefix) {
                // "CF 160" and "160" should both find it.
                $ref = trim(str_ireplace($orderPrefix, '', $keyword));

                $q->whereHas('orderFormLine.orderForm', fn ($sub) => $sub->where('ref_no', 'like', "%{$ref}%"));
            })
            ->orderColumn('weights', 'net_weight $1')
            ->rawColumns(['code', 'metal', 'weights', 'order_no', 'action'])
            ->toJson();
    }

    /**
     * The order holding this piece, or nothing. A held piece is promised to a
     * customer and must not be sold from the case.
     */
    private function orderCell(Item $item, string $prefix): string
    {
        $form = $item->orderFormLine?->orderForm;

        if (! $form) {
            return '<span class="text-muted">—</span>';
        }

        return '<a href="'.e(route('order-forms.edit', $form)).'" class="badge bg-warning text-dark">'
            .e(trim($prefix.' '.$form->ref_no)).'</a>';
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
     * Search stock for a picker, returning everything a caller needs to copy a piece
     * onto one of its own lines — the order form does exactly that.
     *
     * Reserved pieces are excluded by default: one already promised to a customer is
     * not available to promise again.
     */
    public function lookup(Request $request): JsonResponse
    {
        $term = $request->string('q')->toString();

        $items = Item::query()
            ->active()
            // Gone from the shop, so it cannot be promised again.
            ->inStock()
            ->with(['metalType:id,name', 'purity:id,name', 'makingCharge', 'itemStones'])
            ->when($request->boolean('include_reserved') === false, fn ($q) => $q->whereNull('order_form_line_id'))
            ->when($term !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('code', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%")
                ->orWhere('huid', 'like', "%{$term}%")))
            ->orderBy('code')
            ->limit(30)
            ->get();

        return response()->json([
            'items' => $items->map(fn (Item $item) => [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'description' => $item->description,
                'metal_type_id' => $item->metal_type_id,
                'metal_type' => $item->metalType?->name,
                'purity_id' => $item->purity_id,
                'purity' => $item->purity?->name,
                'making_charge_id' => $item->making_charge_id,
                // The order line copies labour off the making charge, and carries the
                // extra charges into its own other-charges total.
                'making_charge' => $item->makingCharge ? [
                    'charge_type' => $item->makingCharge->charge_type,
                    'rate' => (float) $item->makingCharge->rate,
                ] : null,
                'extra_charge_1' => (float) $item->extra_charge_1,
                'extra_charge_2' => (float) $item->extra_charge_2,
                'gross_weight' => (float) $item->gross_weight,
                'net_weight' => (float) $item->net_weight,
                'other_deduction' => (float) $item->other_deduction,
                'stones' => $item->itemStones->map(fn ($row) => [
                    'stone_master_id' => $row->stone_master_id,
                    'kind' => $row->kind,
                    'pieces' => $row->pieces,
                    'weight_carat' => (float) $row->weight_carat,
                    'weight_grams' => (float) $row->weight_grams,
                    'rate_unit' => $row->rate_unit,
                    'rate' => (float) $row->rate,
                    'amount' => (float) $row->amount,
                    'deduct_from_gross' => (bool) $row->deduct_from_gross,
                ])->values(),
            ])->values(),
        ]);
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
