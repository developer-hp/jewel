<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderItemRequest;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\MakingCharge;
use App\Models\MetalType;
use App\Models\OrderForm;
use App\Models\OrderFormLine;
use App\Models\Purity;
use App\Models\StoneMaster;
use App\Models\Supplier;
use App\Services\ItemCalculator;
use App\Services\ItemPhotoStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Making a piece to order.
 *
 * The repair equivalent takes a weight and a touch, because a repaired piece is one
 * the customer already owned. A made-to-order piece is new stock, so this screen
 * carries everything an item has — metal, purity, weights, making charge, extra
 * charges and the stone and diamond rows — seeded from what was actually ordered.
 *
 * Saving is what makes the order line ready, because readiness is the existence of
 * the piece rather than a flag someone ticked.
 */
class OrderItemController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly ItemCalculator $calculator,
        private readonly ItemPhotoStore $photos,
    ) {}

    public static function middleware(): array
    {
        return [new Middleware(['permission:order_form.edit', 'permission:item.create'])];
    }

    public function create(): View
    {
        $group = ItemGroup::system(ItemGroup::SYSTEM_ORDER);

        return view('order-items.create', [
            'group' => $group,
            'nextCode' => $group->previewNextCode(),
            'forms' => $this->openForms(),
            'metalTypes' => MetalType::active()->ordered()->get(),
            'puritiesByMetal' => Purity::active()->ordered()->get()
                ->groupBy('metal_type_id')
                ->map(fn ($set) => $set->map(fn (Purity $p) => ['id' => $p->id, 'name' => $p->name])->values()),
            // Today's per-gram rate per purity, so the shared script can price the
            // piece live exactly as it does on the item form.
            'purityRates' => Purity::active()->get()
                ->mapWithKeys(fn (Purity $p) => [$p->id => (float) ($p->ratePerGramOn() ?? 0)]),
            'makingCharges' => MakingCharge::active()->orderBy('code')->get(),
            'suppliers' => Supplier::active()->ordered()->get(),
            'stoneMasters' => StoneMaster::active()->kind(StoneMaster::KIND_STONE)->orderBy('name')->get(),
            'diamondMasters' => StoneMaster::active()->kind(StoneMaster::KIND_DIAMOND)->orderBy('name')->get(),
            'item' => new Item(['is_active' => true, 'other_deduction' => 0]),
            'stoneRows' => collect(),
            'diamondRows' => collect(),
        ]);
    }

    public function store(OrderItemRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $item = DB::transaction(function () use ($data) {
            $group = ItemGroup::system(ItemGroup::SYSTEM_ORDER);

            $item = new Item([
                'item_group_id' => $group->id,
                'order_form_line_id' => $data['order_form_line_id'],
                'metal_type_id' => $data['metal_type_id'],
                'purity_id' => $data['purity_id'],
                'making_charge_id' => $data['making_charge_id'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'huid' => $data['huid'] ?? null,
                'gross_weight' => $data['gross_weight'],
                'other_deduction' => $data['other_deduction'],
                'extra_charge_1' => $data['extra_charge_1'],
                'extra_charge_1_label' => $data['extra_charge_1_label'] ?? null,
                'extra_charge_2' => $data['extra_charge_2'],
                'extra_charge_2_label' => $data['extra_charge_2_label'] ?? null,
                'is_active' => true,
            ]);

            // Reserved inside the transaction so the group's row lock holds; a
            // rollback releases the number too.
            $item->code = $group->nextItemCode();
            $item->net_weight = $data['gross_weight'];
            $item->save();

            $this->syncStones($item, $data['stones'] ?? []);
            // The one calculator owns every derived weight column, stones or not.
            $this->calculator->recalculate($item);

            return $item;
        });

        if ($request->hasFile('photo')) {
            $this->photos->put($item, $request->file('photo'));
        }

        $line = OrderFormLine::with('orderForm.lines.item')->find($data['order_form_line_id']);
        $form = $line?->orderForm;

        $message = "{$item->code} made against ".($form?->reference() ?? 'the order').'.'
            .($form && $form->isReady() ? ' Every piece is now held — the order is ready.' : '');

        if ($request->boolean('save_and_add_another')) {
            return redirect()->route('order-items.create')->with('success', $message);
        }

        return redirect()->route('order-forms.index')->with('success', $message);
    }

    /**
     * Orders with a piece still to be made, each carrying its lines for the dependent
     * dropdown. Rendered once into the page, so switching order needs no round trip.
     */
    private function openForms()
    {
        return OrderForm::query()
            ->awaitingItems()
            ->with([
                'lines' => fn ($q) => $q->where('made_to_order', true),
                'lines.item:id,code,order_form_line_id',
                'lines.stones.stoneMaster:id,name,kind',
            ])
            ->orderByDesc('ref_no')
            ->get();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncStones(Item $item, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $masters = StoneMaster::whereIn('id', array_column($rows, 'stone_master_id'))->get()->keyBy('id');

        $item->itemStones()->createMany($this->calculator->buildStoneRows($rows, $masters));
    }
}
