<?php

use App\Models\OrderType;
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

it('seeds the types the shop already uses', function () {
    expect(OrderType::pluck('name')->all())->toContain('CZ', 'Stock');

    // Re-running must not double them up.
    $this->seed(MasterDataSeeder::class);

    expect(OrderType::where('name', 'CZ')->count())->toBe(1);
});

it('renders the listing and its datatables payload', function () {
    $this->actingAs($this->admin)->get(route('order-types.index'))->assertOk();

    $response = $this->actingAs($this->admin)->getJson(route('order-types.index', dtParams(['name'])));

    $response->assertOk();

    expect($response->json('recordsTotal'))->toBeGreaterThan(0)
        ->and($response->json('data.0'))->toHaveKeys(['name', 'supplier_orders_count', 'status', 'action']);
});

it('creates, updates and deletes a type', function () {
    $this->actingAs($this->admin)->post(route('order-types.store'), [
        'name' => 'Antique',
        'is_active' => '1',
    ])->assertRedirect(route('order-types.index'));

    $type = OrderType::where('name', 'Antique')->firstOrFail();

    expect($type->is_active)->toBeTrue();

    $this->actingAs($this->admin)->put(route('order-types.update', $type), [
        'name' => 'Antique Jadtar',
        'sort_order' => 5,
    ])->assertRedirect();

    expect($type->refresh()->name)->toBe('Antique Jadtar')
        ->and($type->sort_order)->toBe(5)
        ->and($type->is_active)->toBeFalse();

    $this->actingAs($this->admin)->delete(route('order-types.destroy', $type))->assertRedirect();

    expect(OrderType::whereKey($type->id)->exists())->toBeFalse();
});

it('rejects a duplicate name', function () {
    $this->actingAs($this->admin)->post(route('order-types.store'), ['name' => 'CZ'])
        ->assertSessionHasErrors('name');
});

it('lets a sales user read the master but not change it', function () {
    $type = OrderType::first();

    $this->actingAs($this->sales)->get(route('order-types.index'))->assertOk();

    $this->actingAs($this->sales)->get(route('order-types.create'))->assertForbidden();
    $this->actingAs($this->sales)->post(route('order-types.store'), ['name' => 'X'])->assertForbidden();
    $this->actingAs($this->sales)->delete(route('order-types.destroy', $type))->assertForbidden();
});
