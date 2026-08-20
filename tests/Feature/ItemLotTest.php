<?php

use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\ItemLot;
use App\Models\MakingCharge;
use App\Models\Purity;
use App\Models\StoneMaster;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->ring = ItemGroup::where('prefix', 'RNG')->firstOrFail();
    $this->earring = ItemGroup::where('prefix', 'ERG')->firstOrFail();
    $this->gold22 = Purity::whereRelation('metalType', 'code', 'GOLD')->where('name', '22K')->firstOrFail();
});

/**
 * @return array<string, mixed>
 */
function lotPayload(array $overrides = []): array
{
    return array_merge([
        'lot_date' => today()->toDateString(),
        'total_gross_weight' => 480,
        'lines' => [
            ['item_group_id' => test()->ring->id, 'pieces' => 10, 'tags' => 10],
            ['item_group_id' => test()->earring->id, 'pieces' => 20, 'tags' => 10],
        ],
    ], $overrides);
}

function makeLot(array $overrides = []): ItemLot
{
    test()->actingAs(test()->admin)->post(route('lots.store'), lotPayload($overrides));

    return ItemLot::latest('id')->firstOrFail();
}

/**
 * @return array<string, mixed>
 */
function entryPayload(array $rows): array
{
    return ['rows' => $rows];
}

function ringRow(array $overrides = []): array
{
    return array_merge([
        'item_group_id' => test()->ring->id,
        // Metal, purity and making charge are per row; the screen's header only
        // seeds their defaults.
        'metal_type_id' => test()->gold22->metal_type_id,
        'purity_id' => test()->gold22->id,
        'name' => 'Ring',
        'huid' => null,
        'gross_weight' => 10.25,
        'other_deduction' => 0,
    ], $overrides);
}

// --- the lot itself ----------------------------------------------------------

it('creates a lot with a generated code and totals', function () {
    $lot = makeLot();

    // Codes derive from the auto-increment id, so assert the shape rather than a
    // fixed number — the sequence does not reset between tests.
    expect($lot->code)->toMatch('/^LOT\d{5}$/')
        ->and($lot->status)->toBe(ItemLot::STATUS_PENDING)
        ->and($lot->piecesExpected())->toBe(30)
        // Pieces and tags are different things: 20 earring pieces are 10 pairs.
        ->and($lot->tagsExpected())->toBe(20)
        ->and($lot->remainingTags())->toBe(20);
});

it('numbers lots sequentially', function () {
    $first = makeLot()->code;
    $second = makeLot()->code;

    expect($first)->toMatch('/^LOT\d{5}$/')
        ->and($second)->toMatch('/^LOT\d{5}$/')
        ->and($second)->not->toBe($first)
        // Derived from the id, so the second is always the higher number.
        ->and((int) substr($second, 3))->toBe((int) substr($first, 3) + 1);
});

it('rejects the same group twice on one lot', function () {
    $this->actingAs($this->admin)->post(route('lots.store'), lotPayload([
        'lines' => [
            ['item_group_id' => $this->ring->id, 'pieces' => 5, 'tags' => 5],
            ['item_group_id' => $this->ring->id, 'pieces' => 5, 'tags' => 5],
        ],
    ]))->assertSessionHasErrors('lines');

    expect(ItemLot::count())->toBe(0);
});

it('requires at least one line', function () {
    $this->actingAs($this->admin)->post(route('lots.store'), lotPayload(['lines' => []]))
        ->assertSessionHasErrors('lines');
});

it('will not cut a line below the items already entered', function () {
    $lot = makeLot();
    $this->actingAs($this->admin)->post(route('lots.items.store', $lot), entryPayload([ringRow(), ringRow()]));

    $this->actingAs($this->admin)->put(route('lots.update', $lot), lotPayload([
        'lines' => [
            ['item_group_id' => $this->ring->id, 'pieces' => 1, 'tags' => 1],
            ['item_group_id' => $this->earring->id, 'pieces' => 20, 'tags' => 10],
        ],
    ]))->assertSessionHasErrors('lines.0.tags');
});

it('will not drop a group that already has items', function () {
    $lot = makeLot();
    $this->actingAs($this->admin)->post(route('lots.items.store', $lot), entryPayload([ringRow()]));

    $this->actingAs($this->admin)->put(route('lots.update', $lot), lotPayload([
        'lines' => [['item_group_id' => $this->earring->id, 'pieces' => 20, 'tags' => 10]],
    ]))->assertSessionHasErrors('lines');
});

it('blocks deleting a lot that has items', function () {
    $lot = makeLot();
    $this->actingAs($this->admin)->post(route('lots.items.store', $lot), entryPayload([ringRow()]));

    $this->actingAs($this->admin)->delete(route('lots.destroy', $lot))->assertSessionHas('error');

    expect(ItemLot::whereKey($lot->id)->exists())->toBeTrue();
});

// --- entry screen ------------------------------------------------------------

it('creates one item per queued row, carrying the batch defaults', function () {
    $lot = makeLot();

    $this->actingAs($this->admin)->post(route('lots.items.store', $lot), entryPayload([
        ringRow(['huid' => 'ABC123']),
        ringRow(['gross_weight' => 11.4, 'other_deduction' => 0.4]),
    ]))->assertRedirect(route('lots.show', $lot));

    $items = Item::orderBy('id')->get();

    expect($items)->toHaveCount(2)
        ->and($items->pluck('code')->all())->toBe(['RNG0001', 'RNG0002'])
        ->and($items->every(fn ($i) => $i->item_lot_id === $lot->id))->toBeTrue()
        ->and($items->every(fn ($i) => $i->purity_id === $this->gold22->id))->toBeTrue()
        ->and($items[0]->huid)->toBe('ABC123')
        // Net is derived by the one calculator, stones or not.
        ->and((float) $items[1]->net_weight)->toBe(11.0);
});

it('moves the lot through its statuses on its own', function () {
    $lot = makeLot(['lines' => [['item_group_id' => test()->ring->id, 'pieces' => 2, 'tags' => 2]]]);

    expect($lot->status)->toBe(ItemLot::STATUS_PENDING);

    $this->actingAs($this->admin)->post(route('lots.items.store', $lot), entryPayload([ringRow()]));
    expect($lot->fresh()->status)->toBe(ItemLot::STATUS_IN_PROGRESS);

    $this->actingAs($this->admin)->post(route('lots.items.store', $lot), entryPayload([ringRow()]));
    expect($lot->fresh()->status)->toBe(ItemLot::STATUS_FINISHED);
});

it('rolls a finished lot back when an item is deleted', function () {
    $lot = makeLot(['lines' => [['item_group_id' => test()->ring->id, 'pieces' => 1, 'tags' => 1]]]);

    $this->actingAs($this->admin)->post(route('lots.items.store', $lot), entryPayload([ringRow()]));
    expect($lot->fresh()->status)->toBe(ItemLot::STATUS_FINISHED);

    // The observer is what makes this work from any deletion path.
    $this->actingAs($this->admin)->delete(route('items.destroy', Item::firstOrFail()));

    expect($lot->fresh()->status)->toBe(ItemLot::STATUS_PENDING)
        ->and($lot->fresh()->remainingTags())->toBe(1);
});

it('refuses more rows than the group has tags left', function () {
    $lot = makeLot(['lines' => [['item_group_id' => test()->ring->id, 'pieces' => 2, 'tags' => 2]]]);

    $this->actingAs($this->admin)->post(route('lots.items.store', $lot), entryPayload([
        ringRow(), ringRow(), ringRow(),
    ]))->assertSessionHasErrors('rows');

    // All or nothing: the transaction wrote none of them.
    expect(Item::count())->toBe(0);
});

it('refuses a group that is not on the lot', function () {
    $lot = makeLot(['lines' => [['item_group_id' => test()->ring->id, 'pieces' => 2, 'tags' => 2]]]);

    $this->actingAs($this->admin)->post(route('lots.items.store', $lot), entryPayload([
        ringRow(['item_group_id' => $this->earring->id, 'name' => 'Earring', 'gross_weight' => 5]),
    ]))->assertSessionHasErrors('rows');

    expect(Item::count())->toBe(0);
});

it('reports a bad row by its own index', function () {
    $lot = makeLot();

    $this->actingAs($this->admin)->post(route('lots.items.store', $lot), entryPayload([
        ringRow(),
        ringRow(['other_deduction' => 99]),
    ]))->assertSessionHasErrors('rows.1.other_deduction');

    expect(Item::count())->toBe(0);
});

it('rejects a row with no weight', function () {
    $lot = makeLot();

    $this->actingAs($this->admin)->post(route('lots.items.store', $lot), entryPayload([
        ringRow(['gross_weight' => 0]),
    ]))->assertSessionHasErrors('rows.0.gross_weight');
});

it('saves a lot in several visits', function () {
    $lot = makeLot(['lines' => [['item_group_id' => test()->ring->id, 'pieces' => 4, 'tags' => 4]]]);

    $this->actingAs($this->admin)->post(route('lots.items.store', $lot), entryPayload([ringRow(), ringRow()]));
    expect($lot->fresh()->remainingTags())->toBe(2);

    $this->actingAs($this->admin)->post(route('lots.items.store', $lot), entryPayload([ringRow(), ringRow()]));
    expect($lot->fresh()->remainingTags())->toBe(0)
        ->and($lot->fresh()->status)->toBe(ItemLot::STATUS_FINISHED);
});

it('warns about overshooting the declared weight without blocking', function () {
    $lot = makeLot(['total_gross_weight' => 20]);

    $this->actingAs($this->admin)->post(route('lots.items.store', $lot), entryPayload([
        ringRow(['gross_weight' => 15]),
        ringRow(['gross_weight' => 15]),
    ]))->assertRedirect();

    expect(Item::count())->toBe(2)
        ->and($lot->fresh()->grossEntered())->toBe(30.0)
        ->and($lot->fresh()->exceedsGrossTarget())->toBeTrue();
});

it('rebuilds the queue after a rejected save', function () {
    $lot = makeLot();

    // A rejected batch must come back with the typing intact, since nothing is
    // written until Save All and the queue lives only in the browser.
    $response = $this->actingAs($this->admin)
        ->from(route('lots.items.create', $lot))
        ->post(route('lots.items.store', $lot), entryPayload([ringRow(), ringRow(['gross_weight' => 0])]))
        ->assertRedirect(route('lots.items.create', $lot))
        ->assertSessionHasErrors('rows.1.gross_weight');

    $rows = $response->getSession()->getOldInput('rows');

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['name'])->toBe('Ring');
});

// --- screens and permissions --------------------------------------------------

it('renders the lot screens', function () {
    $lot = makeLot();

    $this->actingAs($this->admin)->get(route('lots.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('lots.create'))->assertOk();
    $this->actingAs($this->admin)->get(route('lots.show', $lot))->assertOk()->assertSee($lot->code);
    $this->actingAs($this->admin)->get(route('lots.edit', $lot))->assertOk();
    $this->actingAs($this->admin)->get(route('lots.items.create', $lot))
        ->assertOk()
        // The keyboard entry row, not a pre-seeded grid.
        ->assertSee('id="entry-row"', false)
        ->assertSee('Save All', false)
        // Per-row metal, purity and making charge, plus the F4 stone popup.
        ->assertSee('id="e-metal"', false)
        ->assertSee('id="e-purity"', false)
        ->assertSee('id="e-making"', false)
        ->assertSee('id="row-stone-modal"', false);
});

it('returns a datatables payload for the lot listing', function () {
    makeLot();

    $response = $this->actingAs($this->admin)
        ->getJson(route('lots.index', dtParams(['code', 'lot_date', 'supplier', 'groups'])));

    $response->assertOk();

    expect($response->json('recordsTotal'))->toBe(1)
        ->and($response->json('data.0'))->toHaveKeys(['code', 'groups', 'progress', 'weight', 'status_badge', 'action']);
});

it('gates lots by permission', function () {
    $lot = makeLot();

    $sales = User::factory()->create();
    $sales->assignRole('Sales'); // item_lot.view only, no item.create

    $this->actingAs($sales)->get(route('lots.index'))->assertOk();
    $this->actingAs($sales)->get(route('lots.show', $lot))->assertOk();
    $this->actingAs($sales)->get(route('lots.create'))->assertForbidden();
    $this->actingAs($sales)->get(route('lots.items.create', $lot))->assertForbidden();
    $this->actingAs($sales)->post(route('lots.items.store', $lot), entryPayload([ringRow()]))->assertForbidden();

    $nobody = User::factory()->create();
    $this->actingAs($nobody)->get(route('lots.index'))->assertForbidden();
});

// --- per-row overrides and stones ---------------------------------------------

it('lets each row carry its own metal, purity and making charge', function () {
    $lot = makeLot();
    $gold18 = Purity::whereRelation('metalType', 'code', 'GOLD')->where('name', '18K')->firstOrFail();
    $charge = MakingCharge::where('code', 'MC-PG350')->firstOrFail();

    $this->actingAs($this->admin)->post(route('lots.items.store', $lot), entryPayload([
        ringRow(['making_charge_id' => $charge->id]),
        ringRow(['purity_id' => $gold18->id]),
    ]))->assertRedirect();

    $items = Item::orderBy('id')->get();

    expect($items[0]->purity_id)->toBe($this->gold22->id)
        ->and($items[0]->making_charge_id)->toBe($charge->id)
        ->and($items[1]->purity_id)->toBe($gold18->id)
        ->and($items[1]->making_charge_id)->toBeNull();
});

it('rejects a purity that does not match the metal type on that row', function () {
    $lot = makeLot();
    $silver = Purity::whereRelation('metalType', 'code', 'SILV')->firstOrFail();

    $this->actingAs($this->admin)->post(route('lots.items.store', $lot), entryPayload([
        ringRow(['purity_id' => $silver->id]),
    ]))->assertSessionHasErrors('rows.0.purity_id');

    expect(Item::count())->toBe(0);
});

it('saves stones and diamonds entered against a row', function () {
    $lot = makeLot();
    $ruby = StoneMaster::where('name', 'Ruby')->firstOrFail();
    $diamond = StoneMaster::where('name', 'Round Brilliant SI')->firstOrFail();

    $this->actingAs($this->admin)->post(route('lots.items.store', $lot), entryPayload([
        ringRow([
            'gross_weight' => 12.5,
            'stones' => [
                ['stone_master_id' => $ruby->id, 'pieces' => 2, 'weight_carat' => 5, 'deduct_from_gross' => '1'],
                ['stone_master_id' => $diamond->id, 'pieces' => 1, 'weight_carat' => 2, 'deduct_from_gross' => '1'],
            ],
        ]),
        // A row with no stones alongside one that has them.
        ringRow(['gross_weight' => 8]),
    ]))->assertRedirect();

    $withStones = Item::with('itemStones')->orderBy('id')->first();
    $plain = Item::orderBy('id')->skip(1)->first();

    // 5 ct = 1.000 g stone, 2 ct = 0.400 g diamond.
    expect($withStones->itemStones)->toHaveCount(2)
        ->and((float) $withStones->stone_weight_grams)->toBe(1.0)
        ->and((float) $withStones->diamond_weight_grams)->toBe(0.4)
        ->and((float) $withStones->net_weight)->toBe(11.1)
        ->and($plain->itemStones)->toHaveCount(0)
        ->and((float) $plain->net_weight)->toBe(8.0);
});

it('snapshots the stone rate on a row', function () {
    $lot = makeLot();
    $ruby = StoneMaster::where('name', 'Ruby')->firstOrFail(); // carat @ 1200

    $this->actingAs($this->admin)->post(route('lots.items.store', $lot), entryPayload([
        ringRow(['stones' => [
            ['stone_master_id' => $ruby->id, 'pieces' => 1, 'weight_carat' => 5, 'deduct_from_gross' => '1'],
        ]]),
    ]));

    $ruby->update(['default_rate' => 9999]);

    $row = Item::with('itemStones')->firstOrFail()->itemStones->first();

    expect((float) $row->rate)->toBe(1200.0)
        ->and((float) $row->amount)->toBe(6000.0);
});

it('rejects a row whose stones swallow the gross weight', function () {
    $lot = makeLot();
    $ruby = StoneMaster::where('name', 'Ruby')->firstOrFail();

    $this->actingAs($this->admin)->post(route('lots.items.store', $lot), entryPayload([
        ringRow(),
        ringRow(['gross_weight' => 1, 'stones' => [
            ['stone_master_id' => $ruby->id, 'pieces' => 1, 'weight_carat' => 10, 'deduct_from_gross' => '1'],
        ]]),
    ]))->assertSessionHasErrors('rows.1.gross_weight');

    // All or nothing.
    expect(Item::count())->toBe(0);
});

it('ignores a stone line with no master chosen', function () {
    $lot = makeLot();
    $ruby = StoneMaster::where('name', 'Ruby')->firstOrFail();

    $this->actingAs($this->admin)->post(route('lots.items.store', $lot), entryPayload([
        ringRow(['stones' => [
            ['stone_master_id' => $ruby->id, 'pieces' => 1, 'weight_carat' => 5, 'deduct_from_gross' => '1'],
            ['stone_master_id' => '', 'pieces' => 0, 'weight_carat' => 0],
        ]]),
    ]))->assertRedirect();

    expect(Item::with('itemStones')->firstOrFail()->itemStones)->toHaveCount(1);
});
