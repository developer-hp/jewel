<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkItemRequest;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\ItemLot;
use App\Models\MakingCharge;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\StoneMaster;
use App\Models\Supplier;
use App\Services\ItemCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The row-by-row entry screen for a lot.
 *
 * Rows queue in the browser and arrive here as one batch, so the whole thing is
 * written in a single transaction — either every row gets a code or none does.
 */
class LotItemEntryController extends Controller implements HasMiddleware
{
    public function __construct(private readonly ItemCalculator $calculator) {}

    public static function middleware(): array
    {
        return [new Middleware(['permission:item_lot.view', 'permission:item.create'])];
    }

    public function create(ItemLot $lot): View
    {
        $lot->load('lines.itemGroup');

        return view('lots.entry', [
            'lot' => $lot,
            'remaining' => $lot->remainingByGroup(),
            'groups' => $lot->lines->map(fn ($line) => $line->itemGroup)->filter()->values(),
            'suppliers' => Supplier::active()->ordered()->get(),
            'metalTypes' => MetalType::active()->ordered()->get(),
            'makingCharges' => MakingCharge::active()->orderBy('code')->get(),
            'stoneMasters' => StoneMaster::active()->kind(StoneMaster::KIND_STONE)->orderBy('name')->get(),
            'diamondMasters' => StoneMaster::active()->kind(StoneMaster::KIND_DIAMOND)->orderBy('name')->get(),
            'puritiesByMetal' => Purity::active()->ordered()->get()
                ->groupBy('metal_type_id')
                ->map(fn ($group) => $group->map(fn (Purity $p) => ['id' => $p->id, 'name' => $p->name])->values()),
        ]);
    }

    public function store(BulkItemRequest $request, ItemLot $lot): RedirectResponse
    {
        $data = $request->validated();

        $created = DB::transaction(function () use ($lot, $data) {
            // Re-check the quota with the lot rows locked, so two clerks working the
            // same lot cannot both slip past the request-level check.
            $lot->lines()->lockForUpdate()->get();

            $remaining = $lot->remainingByGroup();
            $wanted = collect($data['rows'])->countBy('item_group_id');

            foreach ($wanted as $groupId => $count) {
                if ($count > ($remaining[(int) $groupId] ?? 0)) {
                    throw ValidationException::withMessages([
                        'rows' => 'Another user filled these tags while you were typing. Reload the lot and re-enter the remaining rows.',
                    ]);
                }
            }

            // One lookup for every stone line in the batch.
            $masters = StoneMaster::whereIn(
                'id',
                collect($data['rows'])->pluck('stones')->filter()->flatten(1)->pluck('stone_master_id')->unique()
            )->get()->keyBy('id');

            $codes = [];

            foreach ($data['rows'] as $row) {
                $group = ItemGroup::findOrFail($row['item_group_id']);

                $item = new Item([
                    'item_lot_id' => $lot->id,
                    'item_group_id' => $group->id,
                    // Metal, purity and making charge are per row; the screen's header
                    // only seeds their defaults.
                    'metal_type_id' => $row['metal_type_id'],
                    'purity_id' => $row['purity_id'],
                    'making_charge_id' => $row['making_charge_id'],
                    'supplier_id' => $data['supplier_id'] ?? null,
                    'name' => $row['name'],
                    'huid' => $row['huid'],
                    'gross_weight' => $row['gross_weight'],
                    'other_deduction' => $row['other_deduction'],
                    'is_active' => true,
                ]);

                // Reserved inside the transaction so the row lock holds.
                $item->code = $group->nextItemCode();
                $item->net_weight = $row['gross_weight'];
                $item->save();

                if ($row['stones'] !== []) {
                    $item->itemStones()->createMany(
                        $this->calculator->buildStoneRows($row['stones'], $masters)
                    );
                }

                // The one calculator owns every derived weight column, stones or not.
                $this->calculator->recalculate($item);

                $codes[] = $item->code;
            }

            return $codes;
        });

        $lot->refresh()->refreshStatus();

        return redirect()->route('lots.show', $lot)->with(
            'success',
            count($created).' item(s) added to '.$lot->code.': '.implode(', ', $created).'.'
        );
    }
}
