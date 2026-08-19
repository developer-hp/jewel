<?php

use App\Models\Item;
use App\Models\ItemGroup;
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
    $this->necklace = ItemGroup::where('prefix', 'NCK')->firstOrFail();
});

function createItem(array $overrides = []): TestResponse
{
    return test()->actingAs(test()->admin)->post(route('items.store'), array_merge([
        'item_group_id' => test()->ring->id,
        'metal_type_id' => test()->gold22->metal_type_id,
        'purity_id' => test()->gold22->id,
        'name' => 'Test Piece',
        'gross_weight' => 12.5,
        'other_deduction' => 0,
        'is_active' => '1',
    ], $overrides));
}

it('numbers items per group, not globally', function () {
    createItem();
    createItem();
    createItem(['item_group_id' => $this->necklace->id]);
    createItem();

    expect(Item::orderBy('id')->pluck('code')->all())
        ->toBe(['RNG0001', 'RNG0002', 'NCK0001', 'RNG0003']);
});

it('pads the number to the group setting', function () {
    $this->ring->update(['code_padding' => 6]);

    createItem();

    expect(Item::first()->code)->toBe('RNG000001');
});

it('never issues the same code twice', function () {
    foreach (range(1, 25) as $ignored) {
        createItem();
    }

    $codes = Item::pluck('code');

    expect($codes)->toHaveCount(25)
        ->and($codes->unique())->toHaveCount(25);
});

it('advances the group sequence as codes are issued', function () {
    expect($this->ring->previewNextCode())->toBe('RNG0001');

    createItem();

    expect($this->ring->fresh()->previewNextCode())->toBe('RNG0002')
        ->and($this->ring->fresh()->next_sequence)->toBe(2);
});

it('does not consume a code when the save is rejected', function () {
    // 10 ct of stones against a 1 g piece — the calculator throws and rolls back.
    $stone = StoneMaster::where('name', 'Ruby')->firstOrFail();

    createItem([
        'gross_weight' => 1.0,
        'stones' => [
            ['stone_master_id' => $stone->id, 'pieces' => 1, 'weight_carat' => 10, 'deduct_from_gross' => '1'],
        ],
    ])->assertSessionHasErrors('gross_weight');

    expect(Item::count())->toBe(0)
        ->and($this->ring->fresh()->next_sequence)->toBe(1);
});

it('rejects a duplicate prefix on another group', function () {
    $this->actingAs($this->admin)->post(route('item-groups.store'), [
        'name' => 'Another Ring Group',
        'prefix' => 'RNG',
        'code_padding' => 4,
    ])->assertSessionHasErrors('prefix');
});

it('locks the prefix once the group has items', function () {
    createItem();

    $this->actingAs($this->admin)->put(route('item-groups.update', $this->ring), [
        'name' => $this->ring->name,
        'prefix' => 'XYZ',
        'code_padding' => 4,
    ])->assertSessionHas('error');

    expect($this->ring->fresh()->prefix)->toBe('RNG');
});

it('keeps the group fixed when an item is edited', function () {
    createItem();
    $item = Item::firstOrFail();

    $this->actingAs($this->admin)->put(route('items.update', $item), [
        'item_group_id' => $this->necklace->id,
        'metal_type_id' => $this->gold22->metal_type_id,
        'purity_id' => $this->gold22->id,
        'name' => 'Renamed',
        'gross_weight' => 12.5,
        'other_deduction' => 0,
    ])->assertRedirect();

    expect($item->fresh()->item_group_id)->toBe($this->ring->id)
        ->and($item->fresh()->code)->toBe('RNG0001')
        ->and($item->fresh()->name)->toBe('Renamed');
});
