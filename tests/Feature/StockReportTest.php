<?php

use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\MetalType;
use App\Models\OrderForm;
use App\Models\Purity;
use App\Models\StockGroup;
use App\Models\User;
use App\Services\StockFigures;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->sales = User::factory()->create();
    $this->sales->assignRole('Sales');

    $this->gold = MetalType::where('code', 'GOLD')->firstOrFail();
    $this->silver = MetalType::where('code', 'SILV')->firstOrFail();

    $this->ring = ItemGroup::where('prefix', 'RNG')->firstOrFail();
    $this->bangle = ItemGroup::where('prefix', 'BNG')->firstOrFail();

    $this->figures = app(StockFigures::class);
});

/**
 * A piece in stock. Weights are given so the sums are checkable by eye.
 */
function stockItem(ItemGroup $group, ?MetalType $metal = null, float $gross = 10, float $net = 9): Item
{
    $metal ??= MetalType::where('code', 'GOLD')->firstOrFail();
    $purity = Purity::where('metal_type_id', $metal->id)->firstOrFail();

    $item = new Item([
        'item_group_id' => $group->id,
        'metal_type_id' => $metal->id,
        'purity_id' => $purity->id,
        'name' => $group->name,
        'gross_weight' => $gross,
        'other_deduction' => 0,
        'is_active' => true,
    ]);

    $item->code = $group->nextItemCode();
    $item->net_weight = $net;
    $item->save();

    return $item;
}

/**
 * One row of a figures collection, by item group code.
 */
function rowFor($rows, string $code)
{
    return $rows->firstWhere('code', $code);
}

// --- the stock summary -----------------------------------------------------------

it('totals pieces and weight per item group, and still lists the empty ones', function () {
    stockItem($this->ring, gross: 10, net: 9);
    stockItem($this->ring, gross: 12.5, net: 11.25);
    stockItem($this->bangle, gross: 40, net: 38.6);

    $rows = $this->figures->byItemGroup();

    expect(rowFor($rows, 'RNG')->pcs)->toBe(2)
        ->and(rowFor($rows, 'RNG')->gross)->toBe(22.5)
        ->and(rowFor($rows, 'RNG')->net)->toBe(20.25)
        ->and(rowFor($rows, 'BNG')->pcs)->toBe(1)
        // A group holding nothing still has a row, so the sheet reads the same daily.
        ->and(rowFor($rows, 'NCK')->pcs)->toBe(0)
        ->and(rowFor($rows, 'NCK')->net)->toBe(0.0);

    $totals = $this->figures->totals($rows, ['pcs', 'gross', 'net']);

    expect($totals->pcs)->toBe(3)
        ->and($totals->net)->toBe(58.85);
});

it('narrows to one metal type', function () {
    stockItem($this->ring, $this->gold, net: 9);
    stockItem($this->ring, $this->silver, net: 100);

    expect(rowFor($this->figures->byItemGroup(), 'RNG')->pcs)->toBe(2)
        ->and(rowFor($this->figures->byItemGroup($this->gold->id), 'RNG')->pcs)->toBe(1)
        ->and(rowFor($this->figures->byItemGroup($this->gold->id), 'RNG')->net)->toBe(9.0)
        ->and(rowFor($this->figures->byItemGroup($this->silver->id), 'RNG')->net)->toBe(100.0);
});

it('counts a held piece without taking it out of stock', function () {
    $free = stockItem($this->ring);
    $held = stockItem($this->ring);

    expect(rowFor($this->figures->byItemGroup(), 'RNG')->held)->toBe(0);

    // Reserving is what an order form does to a piece it promises, and it is the
    // very column the Held figure reads.
    $form = new OrderForm([
        'form_date' => today(),
        'delivery_date' => today()->addWeek(),
        'customer_name' => 'A Customer',
        'contact_no' => '9000000000',
    ]);
    $form->ref_no = OrderForm::nextRefNo();
    $form->save();

    $line = $form->lines()->create(['description' => 'Ring', 'sort_order' => 0]);
    $held->forceFill(['order_form_line_id' => $line->id])->save();

    $row = rowFor($this->figures->byItemGroup(), 'RNG');

    expect($row->held)->toBe(1)
        // Held is reported, never deducted.
        ->and($row->pcs)->toBe(2)
        ->and($free->refresh()->order_form_line_id)->toBeNull();
});

it('rolls item groups up into their stock group, with a home for the rest', function () {
    $plain = StockGroup::create(['name' => 'Plain Gold', 'code' => 'PG']);
    $this->ring->update(['stock_group_id' => $plain->id]);

    stockItem($this->ring, net: 9);
    stockItem($this->bangle, net: 38.6);

    $itemGroups = $this->figures->byItemGroup();
    $rows = $this->figures->byStockGroup($itemGroups, StockGroup::active()->ordered()->get());

    $assigned = $rows->firstWhere('name', 'Plain Gold');
    $unassigned = $rows->firstWhere('name', '(unassigned)');

    expect($assigned->pcs)->toBe(1)
        ->and($assigned->net)->toBe(9.0)
        // Everything without a stock group lands here rather than vanishing.
        ->and($unassigned->pcs)->toBe(1)
        ->and($unassigned->net)->toBe(38.6)
        ->and($rows->sum('pcs'))->toBe($itemGroups->sum('pcs'));
});

it('renders the summary and prints it', function () {
    stockItem($this->ring);

    $this->actingAs($this->admin)->get(route('stock.index'))
        ->assertOk()
        ->assertSee('By Item Group')
        ->assertSee('By Stock Group')
        ->assertSee('RNG');

    $response = $this->actingAs($this->admin)->get(route('stock.print'));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF-');
});

// --- the daily report -------------------------------------------------------------

it('reads opening, add, less and closing off when pieces came and went', function () {
    // Two pieces the day before yesterday.
    $this->travelTo(today()->subDays(2)->setHour(10));
    $old = stockItem($this->ring, net: 9);
    stockItem($this->ring, net: 11);

    // One more today, and the older one removed today.
    $this->travelBack();
    $this->travelTo(today()->setHour(11));
    stockItem($this->ring, net: 5);
    $old->delete();
    $this->travelBack();

    $row = rowFor($this->figures->daily(today()), 'RNG');

    expect($row->opening_pcs)->toBe(2)
        ->and($row->opening_wt)->toBe(20.0)
        ->and($row->add_pcs)->toBe(1)
        ->and($row->add_wt)->toBe(5.0)
        ->and($row->less_pcs)->toBe(1)
        ->and($row->less_wt)->toBe(9.0)
        // 2 + 1 - 1
        ->and($row->closing_pcs)->toBe(2)
        ->and($row->closing_wt)->toBe(16.0);
});

it('reports a past day correctly, which is the point of not snapshotting', function () {
    $this->travelTo(today()->subDays(3)->setHour(9));
    stockItem($this->ring, net: 9);
    $this->travelBack();

    $this->travelTo(today()->setHour(9));
    stockItem($this->ring, net: 100);
    $this->travelBack();

    // Two days ago: the first piece was already here, nothing moved.
    $past = rowFor($this->figures->daily(today()->subDays(2)), 'RNG');

    expect($past->opening_pcs)->toBe(1)
        ->and($past->add_pcs)->toBe(0)
        ->and($past->less_pcs)->toBe(0)
        ->and($past->closing_pcs)->toBe(1)
        // Today's piece has not happened yet on that day.
        ->and($past->closing_wt)->toBe(9.0);
});

it('nets out a piece that arrived and left on the same day', function () {
    $this->travelTo(today()->setHour(9));
    $item = stockItem($this->ring, net: 7);
    $item->delete();
    $this->travelBack();

    $row = rowFor($this->figures->daily(today()), 'RNG');

    expect($row->opening_pcs)->toBe(0)
        ->and($row->add_pcs)->toBe(1)
        ->and($row->less_pcs)->toBe(1)
        // In and out again leaves nothing behind.
        ->and($row->closing_pcs)->toBe(0)
        ->and($row->closing_wt)->toBe(0.0);
});

it('always has closing equal to opening plus add less less', function () {
    $this->travelTo(today()->subDay()->setHour(9));
    $a = stockItem($this->ring, net: 9);
    stockItem($this->bangle, net: 38.6);
    $this->travelBack();

    $this->travelTo(today()->setHour(9));
    stockItem($this->ring, net: 5);
    $a->delete();
    $this->travelBack();

    $rows = $this->figures->daily(today());

    foreach ($rows as $row) {
        expect($row->closing_pcs)->toBe($row->opening_pcs + $row->add_pcs - $row->less_pcs, "row {$row->code}")
            ->and($row->closing_wt)->toBe(round($row->opening_wt + $row->add_wt - $row->less_wt, 3), "row {$row->code}");
    }

    $totals = $this->figures->totals($rows, [
        'opening_pcs', 'add_pcs', 'less_pcs', 'closing_pcs',
        'opening_wt', 'add_wt', 'less_wt', 'closing_wt',
    ]);

    expect($totals->closing_pcs)->toBe($totals->opening_pcs + $totals->add_pcs - $totals->less_pcs)
        ->and($totals->closing_wt)->toBe(round($totals->opening_wt + $totals->add_wt - $totals->less_wt, 3));
});

it('narrows the daily report to one metal type', function () {
    $this->travelTo(today()->setHour(9));
    stockItem($this->ring, $this->gold, net: 9);
    stockItem($this->ring, $this->silver, net: 100);
    $this->travelBack();

    expect(rowFor($this->figures->daily(today()), 'RNG')->add_pcs)->toBe(2)
        ->and(rowFor($this->figures->daily(today(), $this->gold->id), 'RNG')->add_pcs)->toBe(1)
        ->and(rowFor($this->figures->daily(today(), $this->gold->id), 'RNG')->add_wt)->toBe(9.0);
});

it('renders the daily report and exports it', function () {
    stockItem($this->ring);

    $this->actingAs($this->admin)->get(route('stock.daily'))
        ->assertOk()
        ->assertSee('Opening')
        ->assertSee('Closing');

    // A named day, and a metal filter, both survive into the sheet.
    $response = $this->actingAs($this->admin)->get(route('stock.daily.export', [
        'date' => today()->subDay()->toDateString(),
        'metal_type_id' => $this->gold->id,
    ]));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF-');
});

// --- permissions --------------------------------------------------------------------

it('shows a sales user the summary but not the daily report', function () {
    $this->actingAs($this->sales)->get(route('stock.index'))->assertOk();
    $this->actingAs($this->sales)->get(route('stock.print'))->assertOk();

    // Sales holds stock.view but not stock.report.
    $this->actingAs($this->sales)->get(route('stock.daily'))->assertForbidden();
    $this->actingAs($this->sales)->get(route('stock.daily.export'))->assertForbidden();
});

it('hides both from a user with no permissions', function () {
    $none = User::factory()->create();

    $this->actingAs($none)->get(route('stock.index'))->assertForbidden();
    $this->actingAs($none)->get(route('stock.daily'))->assertForbidden();

    $this->actingAs($this->admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('stock.index'));
});
