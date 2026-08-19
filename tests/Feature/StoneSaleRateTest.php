<?php

use App\Models\StoneMaster;
use App\Models\User;
use App\Services\ItemCalculator;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

it('charges the cost rate until a sale rate is set', function () {
    $stone = StoneMaster::where('name', 'Ruby')->firstOrFail();

    expect($stone->sale_rate)->toBeNull()
        ->and($stone->tracksCostRate())->toBeTrue()
        ->and($stone->effectiveSaleRate())->toBe(1200.0);
});

it('follows the cost rate up while it is still tracking', function () {
    $stone = StoneMaster::where('name', 'Ruby')->firstOrFail();

    $stone->update(['default_rate' => 1400]);

    expect($stone->fresh()->effectiveSaleRate())->toBe(1400.0);
});

it('holds an explicit sale rate independent of cost', function () {
    $stone = StoneMaster::where('name', 'Ruby')->firstOrFail();

    $stone->update(['sale_rate' => 1800]);
    $stone->update(['default_rate' => 1500]);

    expect($stone->fresh()->effectiveSaleRate())->toBe(1800.0)
        ->and($stone->fresh()->tracksCostRate())->toBeFalse();
});

it('saves a sale rate entered on the master form', function () {
    $this->actingAs($this->admin)->post(route('stones.store'), [
        'name' => 'Blue Topaz',
        'rate_unit' => 'carat',
        'default_rate' => 900,
        'sale_rate' => 1250,
        'is_active' => '1',
    ])->assertRedirect(route('stones.index'));

    $stone = StoneMaster::where('name', 'Blue Topaz')->firstOrFail();

    expect((float) $stone->sale_rate)->toBe(1250.0)
        ->and($stone->effectiveSaleRate())->toBe(1250.0);
});

it('treats a blank sale rate on the form as tracking cost', function () {
    $this->actingAs($this->admin)->post(route('stones.store'), [
        'name' => 'Blue Topaz',
        'rate_unit' => 'carat',
        'default_rate' => 900,
        'sale_rate' => '',
        'is_active' => '1',
    ])->assertRedirect();

    $stone = StoneMaster::where('name', 'Blue Topaz')->firstOrFail();

    expect($stone->sale_rate)->toBeNull()
        ->and($stone->effectiveSaleRate())->toBe(900.0);
});

it('can clear an explicit sale rate back to tracking', function () {
    $stone = StoneMaster::where('name', 'Ruby')->firstOrFail();
    $stone->update(['sale_rate' => 1800]);

    $this->actingAs($this->admin)->put(route('stones.update', $stone), [
        'name' => $stone->name,
        'rate_unit' => $stone->rate_unit,
        'default_rate' => $stone->default_rate,
        'sale_rate' => '',
        'is_active' => '1',
    ])->assertRedirect();

    expect($stone->fresh()->tracksCostRate())->toBeTrue();
});

it('rejects a negative sale rate', function () {
    $this->actingAs($this->admin)->post(route('stones.store'), [
        'name' => 'Blue Topaz',
        'rate_unit' => 'carat',
        'default_rate' => 900,
        'sale_rate' => -5,
    ])->assertSessionHasErrors('sale_rate');
});

it('leaves item line pricing on the cost rate', function () {
    $stone = StoneMaster::where('name', 'Ruby')->firstOrFail();
    $stone->update(['sale_rate' => 5000]);

    $calculator = new ItemCalculator;

    $row = $calculator->buildStoneRows(
        [['stone_master_id' => $stone->id, 'pieces' => 1, 'weight_carat' => 2, 'deduct_from_gross' => true]],
        collect([$stone->id => $stone->fresh()])
    )[0];

    // Cost 1200/ct x 2 ct — the sale rate is for the quotation, not the item.
    expect($row['rate'])->toBe(1200.0)
        ->and($row['amount'])->toBe(2400.0);
});
