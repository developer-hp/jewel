<?php

namespace App\Http\Controllers;

use App\Http\Requests\RepairItemRequest;
use App\Models\AppSetting;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\RepairForm;
use App\Models\RepairFormLine;
use App\Services\ItemCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Booking a repaired piece back into stock.
 *
 * Deliberately its own screen rather than a corner of the general item form: the
 * counter is doing one narrow thing — the piece came back, bag it, tag it, and mark
 * the line on the form as done. Saving here is what turns the form green, because
 * "ready" is read off the items, not off a flag.
 */
class RepairItemController extends Controller implements HasMiddleware
{
    public function __construct(private readonly ItemCalculator $calculator) {}

    public static function middleware(): array
    {
        return [new Middleware(['permission:repair_form.edit', 'permission:item.create'])];
    }

    public function create(): View
    {
        $group = ItemGroup::system(ItemGroup::SYSTEM_REPAIR);
        $settings = AppSetting::current();

        return view('repair-items.create', [
            'group' => $group,
            'nextCode' => $group->previewNextCode(),
            'forms' => $this->openForms(),
            'metalTypes' => MetalType::active()->ordered()->get(),
            // Grouped by metal type so the purity dropdown filters client-side, the
            // same shape the item form already consumes.
            'puritiesByMetal' => Purity::active()->ordered()->get()
                ->groupBy('metal_type_id')
                ->map(fn ($group) => $group->map(fn (Purity $p) => ['id' => $p->id, 'name' => $p->name])->values()),
            'defaultMetalTypeId' => $settings->repair_metal_type_id,
            'defaultPurityId' => $settings->repair_purity_id,
        ]);
    }

    public function store(RepairItemRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $item = DB::transaction(function () use ($data) {
            $group = ItemGroup::system(ItemGroup::SYSTEM_REPAIR);

            $item = new Item([
                'item_group_id' => $group->id,
                'repair_form_line_id' => $data['repair_form_line_id'],
                'metal_type_id' => $data['metal_type_id'],
                'purity_id' => $data['purity_id'],
                'name' => $data['name'],
                // Optional fields are absent from validated() when never submitted,
                // so read them defensively rather than by index.
                'description' => $data['description'] ?? null,
                'gross_weight' => $data['gross_weight'],
                // net_weight belongs to ItemCalculator and is never mass-assigned, so
                // the net that was typed is expressed as what the piece lost. With no
                // stone rows the calculator lands back on exactly that figure.
                'other_deduction' => round((float) $data['gross_weight'] - (float) $data['net_weight'], 3),
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

            $this->calculator->recalculate($item);

            return $item;
        });

        $form = RepairFormLine::with('repairForm')->findOrFail($data['repair_form_line_id'])->repairForm;
        $reference = $form?->reference() ?? 'the repair';
        $ready = $form && $form->load('lines.item')->isReady();

        $message = "{$item->code} booked into stock against {$reference}."
            .($ready ? ' Every piece is back — the form is now ready.' : '');

        if ($request->boolean('save_and_add_another')) {
            return redirect()->route('repair-items.create')->with('success', $message);
        }

        return redirect()->route('repair-forms.index')->with('success', $message);
    }

    /**
     * Forms with at least one piece still out, each carrying its lines for the
     * dependent dropdown. Rendered once into the page, so switching form needs no
     * round trip.
     */
    private function openForms()
    {
        return RepairForm::query()
            ->awaitingItems()
            ->with(['lines.item:id,code,repair_form_line_id'])
            ->orderByDesc('ref_no')
            ->get();
    }
}
