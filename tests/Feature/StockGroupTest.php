<?php

use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\StockGroup;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->sales = User::factory()->create();
    $this->sales->assignRole('Sales');
});

it('renders the stock group listing and its datatables payload', function () {
    StockGroup::create(['name' => 'Plain Gold', 'code' => 'PG']);

    $this->actingAs($this->admin)->get(route('stock-groups.index'))->assertOk();

    $response = $this->actingAs($this->admin)
        ->getJson(route('stock-groups.index', dtParams(['name', 'code'])));

    $response->assertOk()->assertJsonPath('recordsTotal', 1);

    expect($response->json('data.0'))->toHaveKeys(['name', 'code', 'item_groups_count', 'status', 'action'])
        ->and($response->json('data.0.item_groups_count'))->toBe(0);
});

it('creates a stock group and uppercases its code', function () {
    $this->actingAs($this->admin)->post(route('stock-groups.store'), [
        'name' => 'Studded',
        'code' => 'std',
        'is_active' => '1',
    ])->assertRedirect(route('stock-groups.index'));

    $group = StockGroup::where('name', 'Studded')->firstOrFail();

    expect($group->code)->toBe('STD')
        ->and($group->is_active)->toBeTrue()
        ->and($group->sort_order)->toBe(0);
});

it('rejects a duplicate name or code', function () {
    StockGroup::create(['name' => 'Plain Gold', 'code' => 'PG']);

    $this->actingAs($this->admin)->post(route('stock-groups.store'), ['name' => 'Plain Gold', 'code' => 'PG2'])
        ->assertSessionHasErrors('name');

    $this->actingAs($this->admin)->post(route('stock-groups.store'), ['name' => 'Other', 'code' => 'pg'])
        ->assertSessionHasErrors('code');

    expect(StockGroup::count())->toBe(1);
});

it('updates a stock group', function () {
    $group = StockGroup::create(['name' => 'Plain Gold', 'code' => 'PG']);

    $this->actingAs($this->admin)->put(route('stock-groups.update', $group), [
        'name' => 'Plain Gold Jewellery',
        'code' => 'PGJ',
        'sort_order' => 5,
    ])->assertRedirect(route('stock-groups.index'));

    expect($group->refresh()->name)->toBe('Plain Gold Jewellery')
        ->and($group->code)->toBe('PGJ')
        ->and($group->sort_order)->toBe(5)
        ->and($group->is_active)->toBeFalse();
});

it('blocks deleting a stock group that item groups still point at', function () {
    $group = StockGroup::create(['name' => 'Plain Gold', 'code' => 'PG']);
    ItemGroup::first()->update(['stock_group_id' => $group->id]);

    $this->actingAs($this->admin)->delete(route('stock-groups.destroy', $group))->assertSessionHas('error');

    expect(StockGroup::whereKey($group->id)->exists())->toBeTrue();
});

it('deletes an unused stock group', function () {
    $group = StockGroup::create(['name' => 'Unused', 'code' => 'UNU']);

    $this->actingAs($this->admin)->delete(route('stock-groups.destroy', $group))->assertRedirect();

    expect(StockGroup::whereKey($group->id)->exists())->toBeFalse();
});

it('assigns a stock group to an item group and reports it in the listing', function () {
    $group = StockGroup::create(['name' => 'Plain Gold', 'code' => 'PG']);
    $itemGroup = ItemGroup::where('prefix', 'RNG')->firstOrFail();

    $this->actingAs($this->admin)->put(route('item-groups.update', $itemGroup), [
        'name' => $itemGroup->name,
        'prefix' => $itemGroup->prefix,
        'code_padding' => $itemGroup->code_padding,
        'stock_group_id' => $group->id,
        'is_active' => '1',
    ])->assertRedirect(route('item-groups.index'));

    expect($itemGroup->refresh()->stock_group_id)->toBe($group->id)
        ->and($group->itemGroups()->count())->toBe(1);

    $response = $this->actingAs($this->admin)->getJson(route('item-groups.index', dtParams(['name', 'stock_group'], [
        'search' => ['value' => 'Plain Gold'],
    ])));

    $response->assertOk();

    expect($response->json('recordsFiltered'))->toBe(1)
        ->and($response->json('data.0.stock_group'))->toBe('Plain Gold');
});

it('rejects an unknown stock group on an item group', function () {
    $itemGroup = ItemGroup::first();

    $this->actingAs($this->admin)->put(route('item-groups.update', $itemGroup), [
        'name' => $itemGroup->name,
        'prefix' => $itemGroup->prefix,
        'code_padding' => $itemGroup->code_padding,
        'stock_group_id' => 99999,
    ])->assertSessionHasErrors('stock_group_id');
});

it('reaches items through their item group so stock can be totalled by group', function () {
    $group = StockGroup::create(['name' => 'Plain Gold', 'code' => 'PG']);
    $itemGroup = ItemGroup::where('prefix', 'RNG')->firstOrFail();
    $itemGroup->update(['stock_group_id' => $group->id]);

    expect($group->items()->count())->toBe(Item::where('item_group_id', $itemGroup->id)->count());
});

it('lets a sales user read stock groups but not change them', function () {
    $group = StockGroup::create(['name' => 'Plain Gold', 'code' => 'PG']);

    $this->actingAs($this->sales)->get(route('stock-groups.index'))->assertOk();

    $this->actingAs($this->sales)->get(route('stock-groups.create'))->assertForbidden();
    $this->actingAs($this->sales)->post(route('stock-groups.store'), ['name' => 'X', 'code' => 'X'])->assertForbidden();
    $this->actingAs($this->sales)->delete(route('stock-groups.destroy', $group))->assertForbidden();
});
