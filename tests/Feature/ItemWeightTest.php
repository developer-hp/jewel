<?php

use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\MetalRate;
use App\Models\Purity;
use App\Models\StoneMaster;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->gold22 = Purity::whereRelation('metalType', 'code', 'GOLD')->where('name', '22K')->firstOrFail();
    $this->ring = ItemGroup::where('prefix', 'RNG')->firstOrFail();

    $this->ruby = StoneMaster::where('name', 'Ruby')->firstOrFail();            // carat @ 1200
    $this->pearl = StoneMaster::where('name', 'Pearl')->firstOrFail();          // piece @ 250
    $this->kundan = StoneMaster::where('name', 'Kundan')->firstOrFail();        // gram  @ 800
    $this->meena = StoneMaster::where('name', 'Meena Work')->firstOrFail();     // fixed @ 500
    $this->diamond = StoneMaster::where('name', 'Round Brilliant VS')->firstOrFail(); // carat @ 48000
});

function postItem(array $overrides = []): TestResponse
{
    return test()->actingAs(test()->admin)->post(route('items.store'), array_merge([
        'item_group_id' => test()->ring->id,
        'metal_type_id' => test()->gold22->metal_type_id,
        'purity_id' => test()->gold22->id,
        'name' => 'Weight Test',
        'gross_weight' => 12.5,
        'other_deduction' => 0,
        'is_active' => '1',
    ], $overrides));
}

it('converts carat to gram and derives net weight', function () {
    postItem([
        'stones' => [
            ['stone_master_id' => $this->ruby->id, 'pieces' => 2, 'weight_carat' => 5, 'deduct_from_gross' => '1'],
            ['stone_master_id' => $this->diamond->id, 'pieces' => 1, 'weight_carat' => 2, 'deduct_from_gross' => '1'],
        ],
    ])->assertRedirect(route('items.index'));

    $item = Item::firstOrFail();

    // 5 ct = 1.000 g, 2 ct = 0.400 g, so 12.500 - 1.400 = 11.100
    expect((float) $item->stone_weight_grams)->toBe(1.0)
        ->and((float) $item->diamond_weight_grams)->toBe(0.4)
        ->and((float) $item->net_weight)->toBe(11.1);
});

it('accepts the weight in grams and back-fills the carat', function () {
    postItem([
        'stones' => [
            // 1 g of stone, entered in grams rather than carat.
            ['stone_master_id' => $this->ruby->id, 'pieces' => 1, 'weight_carat' => 0, 'weight_grams' => 1.0, 'deduct_from_gross' => '1'],
        ],
    ])->assertRedirect();

    $row = Item::firstOrFail()->itemStones->first();

    expect((float) $row->weight_carat)->toBe(5.0)
        ->and((float) $row->weight_grams)->toBe(1.0)
        // The carat figure drives the amount, so a gram entry prices identically.
        ->and((float) $row->amount)->toBe(6000.0);
});

it('prefers carat when both units are submitted', function () {
    postItem([
        'stones' => [
            ['stone_master_id' => $this->ruby->id, 'pieces' => 1, 'weight_carat' => 5, 'weight_grams' => 99, 'deduct_from_gross' => '1'],
        ],
    ])->assertRedirect();

    $row = Item::firstOrFail()->itemStones->first();

    expect((float) $row->weight_carat)->toBe(5.0)
        ->and((float) $row->weight_grams)->toBe(1.0);
});

it('excludes rows that are not marked for deduction', function () {
    postItem([
        'stones' => [
            ['stone_master_id' => $this->ruby->id, 'pieces' => 2, 'weight_carat' => 5, 'deduct_from_gross' => '0'],
            ['stone_master_id' => $this->diamond->id, 'pieces' => 1, 'weight_carat' => 2, 'deduct_from_gross' => '1'],
        ],
    ])->assertRedirect();

    $item = Item::firstOrFail();

    expect((float) $item->stone_weight_grams)->toBe(0.0)
        ->and((float) $item->diamond_weight_grams)->toBe(0.4)
        ->and((float) $item->net_weight)->toBe(12.1);
});

it('subtracts the other deduction', function () {
    postItem([
        'other_deduction' => 0.5,
        'stones' => [
            ['stone_master_id' => $this->ruby->id, 'pieces' => 1, 'weight_carat' => 5, 'deduct_from_gross' => '1'],
        ],
    ])->assertRedirect();

    expect((float) Item::firstOrFail()->net_weight)->toBe(11.0);
});

it('rejects deductions that exceed the gross weight', function () {
    postItem([
        'gross_weight' => 1.0,
        'stones' => [
            ['stone_master_id' => $this->ruby->id, 'pieces' => 1, 'weight_carat' => 10, 'deduct_from_gross' => '1'],
        ],
    ])->assertSessionHasErrors('gross_weight');

    expect(Item::count())->toBe(0)
        ->and(DB::table('item_stones')->count())->toBe(0);
});

it('prices each rate unit correctly', function () {
    postItem([
        'gross_weight' => 50,
        'stones' => [
            ['stone_master_id' => $this->ruby->id, 'pieces' => 3, 'weight_carat' => 4, 'deduct_from_gross' => '1'],   // carat: 1200 * 4
            ['stone_master_id' => $this->pearl->id, 'pieces' => 3, 'weight_carat' => 4, 'deduct_from_gross' => '1'],  // piece: 250 * 3
            ['stone_master_id' => $this->kundan->id, 'pieces' => 3, 'weight_carat' => 4, 'deduct_from_gross' => '1'], // gram:  800 * 0.8
            ['stone_master_id' => $this->meena->id, 'pieces' => 3, 'weight_carat' => 4, 'deduct_from_gross' => '1'],  // fixed: 500
        ],
    ])->assertRedirect();

    $amounts = Item::firstOrFail()->itemStones->pluck('amount', 'stone_master_id');

    expect((float) $amounts[$this->ruby->id])->toBe(4800.0)
        ->and((float) $amounts[$this->pearl->id])->toBe(750.0)
        ->and((float) $amounts[$this->kundan->id])->toBe(640.0)
        ->and((float) $amounts[$this->meena->id])->toBe(500.0);
});

it('snapshots the rate so later master edits do not re-price the item', function () {
    postItem([
        'stones' => [
            ['stone_master_id' => $this->ruby->id, 'pieces' => 1, 'weight_carat' => 5, 'deduct_from_gross' => '1'],
        ],
    ])->assertRedirect();

    $this->ruby->update(['default_rate' => 9999]);

    $row = Item::firstOrFail()->itemStones->first();

    expect((float) $row->rate)->toBe(1200.0)
        ->and((float) $row->amount)->toBe(6000.0);
});

it('honours a per-row rate override', function () {
    postItem([
        'stones' => [
            ['stone_master_id' => $this->ruby->id, 'pieces' => 1, 'weight_carat' => 5, 'rate' => 1500, 'deduct_from_gross' => '1'],
        ],
    ])->assertRedirect();

    expect((float) Item::firstOrFail()->itemStones->first()->amount)->toBe(7500.0);
});

it('recomputes the weights when rows are edited', function () {
    postItem([
        'stones' => [
            ['stone_master_id' => $this->ruby->id, 'pieces' => 1, 'weight_carat' => 5, 'deduct_from_gross' => '1'],
        ],
    ]);

    $item = Item::firstOrFail();

    $this->actingAs($this->admin)->put(route('items.update', $item), [
        'item_group_id' => $this->ring->id,
        'metal_type_id' => $this->gold22->metal_type_id,
        'purity_id' => $this->gold22->id,
        'name' => 'Weight Test',
        'gross_weight' => 12.5,
        'other_deduction' => 0,
        'stones' => [],
    ])->assertRedirect();

    expect((float) $item->fresh()->stone_weight_grams)->toBe(0.0)
        ->and((float) $item->fresh()->net_weight)->toBe(12.5)
        ->and($item->fresh()->itemStones)->toHaveCount(0);
});

it('ignores blank repeater rows', function () {
    postItem([
        'stones' => [
            ['stone_master_id' => '', 'pieces' => 0, 'weight_carat' => 0],
            ['stone_master_id' => $this->ruby->id, 'pieces' => 1, 'weight_carat' => 5, 'deduct_from_gross' => '1'],
        ],
    ])->assertRedirect();

    expect(Item::firstOrFail()->itemStones)->toHaveCount(1);
});

it('rejects a purity that belongs to a different metal type', function () {
    $silver = Purity::whereRelation('metalType', 'code', 'SILV')->firstOrFail();

    postItem(['purity_id' => $silver->id])->assertSessionHasErrors('purity_id');

    expect(Item::count())->toBe(0);
});

it('stores extra charges with their captions', function () {
    postItem([
        'extra_charge_1' => 1200,
        'extra_charge_1_label' => 'Polish',
        'extra_charge_2' => 500,
        'extra_charge_2_label' => 'Cert',
    ])->assertRedirect();

    $item = Item::firstOrFail();

    expect((float) $item->extra_charge_1)->toBe(1200.0)
        ->and($item->extra_charge_1_label)->toBe('Polish')
        ->and($item->extraChargeTotal())->toBe(1700.0)
        ->and($item->extraChargeLines())->toHaveCount(2);
});

it('defaults extra charges to zero when left blank', function () {
    postItem(['extra_charge_1' => '', 'extra_charge_2' => ''])->assertRedirect();

    $item = Item::firstOrFail();

    expect((float) $item->extra_charge_1)->toBe(0.0)
        ->and($item->extraChargeLines())->toBe([]);
});

it('rejects a negative extra charge and an over-long caption', function () {
    postItem(['extra_charge_1' => -10])->assertSessionHasErrors('extra_charge_1');
    postItem(['extra_charge_1_label' => str_repeat('x', 21)])->assertSessionHasErrors('extra_charge_1_label');
});

it('reports the indicative metal value at the current rate', function () {
    MetalRate::create([
        'purity_id' => $this->gold22->id,
        'effective_date' => today(),
        'rate' => 71500,
        'per_grams' => 10,
    ]);

    postItem([
        'stones' => [
            ['stone_master_id' => $this->ruby->id, 'pieces' => 1, 'weight_carat' => 5, 'deduct_from_gross' => '1'],
        ],
    ]);

    // net 11.500 g at 7150/g
    expect(Item::firstOrFail()->metalValueOn())->toBe(82225.0);
});
