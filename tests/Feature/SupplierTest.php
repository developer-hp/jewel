<?php

use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\Purity;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->gold22 = Purity::whereRelation('metalType', 'code', 'GOLD')->where('name', '22K')->firstOrFail();
    $this->ring = ItemGroup::where('prefix', 'RNG')->firstOrFail();
});

/**
 * @return array<string, mixed>
 */
function supplierPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Rajesh Ornaments',
        'short_name' => 'RAJ',
        'city' => 'Rajkot',
        'address' => '12 Sona Bazaar, Rajkot',
        'phone' => '9876543210',
        'is_active' => '1',
    ], $overrides);
}

/**
 * @return array<string, mixed>
 */
function itemPayloadWithSupplier(?int $supplierId): array
{
    return [
        'item_group_id' => test()->ring->id,
        'supplier_id' => $supplierId,
        'metal_type_id' => test()->gold22->metal_type_id,
        'purity_id' => test()->gold22->id,
        'name' => 'Supplier Test Ring',
        'gross_weight' => 10,
        'other_deduction' => 0,
        'is_active' => '1',
    ];
}

it('creates a supplier with every field', function () {
    $this->actingAs($this->admin)->post(route('suppliers.store'), supplierPayload())
        ->assertRedirect(route('suppliers.index'));

    $supplier = Supplier::firstOrFail();

    expect($supplier->name)->toBe('Rajesh Ornaments')
        ->and($supplier->short_name)->toBe('RAJ')
        ->and($supplier->city)->toBe('Rajkot')
        ->and($supplier->phone)->toBe('9876543210')
        ->and($supplier->is_active)->toBeTrue();
});

it('creates a supplier with only a name', function () {
    $this->actingAs($this->admin)->post(route('suppliers.store'), [
        'name' => 'Walk-in Karigar',
        'is_active' => '1',
    ])->assertRedirect(route('suppliers.index'));

    $supplier = Supplier::firstOrFail();

    // Blank optionals are stored as null, not empty strings.
    expect($supplier->name)->toBe('Walk-in Karigar')
        ->and($supplier->short_name)->toBeNull()
        ->and($supplier->city)->toBeNull()
        ->and($supplier->address)->toBeNull()
        ->and($supplier->phone)->toBeNull();
});

it('requires the name', function () {
    $this->actingAs($this->admin)->post(route('suppliers.store'), supplierPayload(['name' => '']))
        ->assertSessionHasErrors('name');

    expect(Supplier::count())->toBe(0);
});

it('rejects a duplicate name or short name', function () {
    Supplier::create(['name' => 'Rajesh Ornaments', 'short_name' => 'RAJ']);

    $this->actingAs($this->admin)->post(route('suppliers.store'), supplierPayload(['short_name' => 'OTHER']))
        ->assertSessionHasErrors('name');

    $this->actingAs($this->admin)->post(route('suppliers.store'), supplierPayload(['name' => 'Another Firm']))
        ->assertSessionHasErrors('short_name');
});

it('allows many suppliers with no short name', function () {
    $this->actingAs($this->admin)->post(route('suppliers.store'), ['name' => 'First'])->assertRedirect();
    $this->actingAs($this->admin)->post(route('suppliers.store'), ['name' => 'Second'])->assertRedirect();

    expect(Supplier::count())->toBe(2);
});

it('updates a supplier', function () {
    $supplier = Supplier::create(['name' => 'Old Name']);

    $this->actingAs($this->admin)
        ->put(route('suppliers.update', $supplier), supplierPayload(['name' => 'New Name']))
        ->assertRedirect(route('suppliers.index'));

    expect($supplier->fresh()->name)->toBe('New Name')
        ->and($supplier->fresh()->city)->toBe('Rajkot');
});

it('returns a datatables payload for the listing', function () {
    Supplier::create(['name' => 'Rajesh Ornaments', 'short_name' => 'RAJ', 'city' => 'Rajkot', 'phone' => '9876543210']);

    $response = $this->actingAs($this->admin)
        ->getJson(route('suppliers.index', dtParams(['name', 'short_name', 'city', 'contact'])));

    $response->assertOk();

    expect($response->json('recordsTotal'))->toBe(1)
        ->and($response->json('data.0'))->toHaveKeys(['name', 'short_name', 'city', 'contact', 'items_count', 'action'])
        ->and($response->json('data.0.contact'))->toContain('9876543210');
});

it('searches the listing by name and phone', function () {
    Supplier::create(['name' => 'Rajesh Ornaments', 'short_name' => 'RAJ', 'phone' => '9876543210']);
    Supplier::create(['name' => 'Mumbai Gold House', 'short_name' => 'MGH', 'phone' => '9111111111']);

    $columns = ['name', 'short_name', 'city', 'contact'];

    foreach (['Rajesh', 'RAJ', '9876543210'] as $term) {
        $response = $this->actingAs($this->admin)
            ->getJson(route('suppliers.index', dtParams($columns, ['search' => ['value' => $term]])));

        expect($response->json('recordsFiltered'))->toBe(1, "search term: {$term}");
    }
});

it('links a supplier to an item and leaves it optional', function () {
    $supplier = Supplier::create(['name' => 'Rajesh Ornaments', 'short_name' => 'RAJ']);

    $this->actingAs($this->admin)->post(route('items.store'), itemPayloadWithSupplier($supplier->id))
        ->assertRedirect(route('items.index'));

    expect(Item::firstOrFail()->supplier->name)->toBe('Rajesh Ornaments');

    $this->actingAs($this->admin)->post(route('items.store'), itemPayloadWithSupplier(null))
        ->assertRedirect(route('items.index'));

    expect(Item::latest('id')->first()->supplier_id)->toBeNull();
});

it('rejects a supplier that does not exist', function () {
    $this->actingAs($this->admin)->post(route('items.store'), itemPayloadWithSupplier(9999))
        ->assertSessionHasErrors('supplier_id');

    expect(Item::count())->toBe(0);
});

it('lets the supplier be changed on an existing item', function () {
    $one = Supplier::create(['name' => 'First Supplier']);
    $two = Supplier::create(['name' => 'Second Supplier']);

    $this->actingAs($this->admin)->post(route('items.store'), itemPayloadWithSupplier($one->id));
    $item = Item::firstOrFail();

    $this->actingAs($this->admin)
        ->put(route('items.update', $item), itemPayloadWithSupplier($two->id))
        ->assertRedirect();

    expect($item->fresh()->supplier_id)->toBe($two->id);

    // Clearing it back to in-house must work too.
    $this->actingAs($this->admin)->put(route('items.update', $item), itemPayloadWithSupplier(null));

    expect($item->fresh()->supplier_id)->toBeNull();
});

it('filters the item listing by supplier', function () {
    $supplier = Supplier::create(['name' => 'Rajesh Ornaments', 'short_name' => 'RAJ']);

    $this->actingAs($this->admin)->post(route('items.store'), itemPayloadWithSupplier($supplier->id));
    $this->actingAs($this->admin)->post(route('items.store'), itemPayloadWithSupplier(null));

    $columns = ['code', 'name', 'group', 'supplier'];

    expect($this->actingAs($this->admin)
        ->getJson(route('items.index', dtParams($columns) + ['supplier_id' => $supplier->id]))
        ->json('recordsFiltered'))->toBe(1);

    expect($this->actingAs($this->admin)
        ->getJson(route('items.index', dtParams($columns)))
        ->json('recordsTotal'))->toBe(2);
});

it('blocks deleting a supplier still linked to items', function () {
    $supplier = Supplier::create(['name' => 'Rajesh Ornaments']);
    $this->actingAs($this->admin)->post(route('items.store'), itemPayloadWithSupplier($supplier->id));

    $this->actingAs($this->admin)->delete(route('suppliers.destroy', $supplier))->assertSessionHas('error');

    expect(Supplier::whereKey($supplier->id)->exists())->toBeTrue();
});

it('deletes an unused supplier', function () {
    $supplier = Supplier::create(['name' => 'Unused Supplier']);

    $this->actingAs($this->admin)->delete(route('suppliers.destroy', $supplier))->assertRedirect();

    expect(Supplier::whereKey($supplier->id)->exists())->toBeFalse();
});

it('offers only active suppliers on the item form', function () {
    Supplier::create(['name' => 'Active Supplier', 'is_active' => true]);
    Supplier::create(['name' => 'Retired Supplier', 'is_active' => false]);

    $this->actingAs($this->admin)->get(route('items.create'))
        ->assertOk()
        ->assertSee('Active Supplier')
        ->assertDontSee('Retired Supplier');
});

it('gates the supplier screens by permission', function () {
    $sales = User::factory()->create();
    $sales->assignRole('Sales');

    $this->actingAs($sales)->get(route('suppliers.index'))->assertOk();
    $this->actingAs($sales)->get(route('suppliers.create'))->assertForbidden();
    $this->actingAs($sales)->post(route('suppliers.store'), supplierPayload())->assertForbidden();

    $nobody = User::factory()->create();
    $this->actingAs($nobody)->get(route('suppliers.index'))->assertForbidden();
});
