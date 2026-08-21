<?php

use App\Models\SalesPerson;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->sales = User::factory()->create();
    $this->sales->assignRole('Sales');
});

it('renders the listing and its datatables payload', function () {
    SalesPerson::create(['name' => 'Shilpa Soni', 'phone' => '9825012345', 'city' => 'Ahmedabad']);

    $this->actingAs($this->admin)->get(route('sales-persons.index'))->assertOk();

    $response = $this->actingAs($this->admin)
        ->getJson(route('sales-persons.index', dtParams(['name', 'phone', 'city'])));

    $response->assertOk()->assertJsonPath('recordsTotal', 1);

    expect($response->json('data.0'))
        ->toHaveKeys(['name', 'phone', 'city', 'repair_form_links_count', 'status', 'action']);
});

it('creates, updates and deletes a sales person', function () {
    $this->actingAs($this->admin)->post(route('sales-persons.store'), [
        'name' => 'Pankaj',
        'phone' => '9825099999',
        'city' => 'Rajkot',
        'is_active' => '1',
    ])->assertRedirect(route('sales-persons.index'));

    $person = SalesPerson::firstOrFail();

    expect($person->name)->toBe('Pankaj')
        ->and($person->is_active)->toBeTrue()
        ->and($person->label())->toBe('Pankaj (Rajkot)');

    $this->actingAs($this->admin)->put(route('sales-persons.update', $person), [
        'name' => 'Pankaj Bhai',
        'sort_order' => 3,
    ])->assertRedirect();

    expect($person->refresh()->name)->toBe('Pankaj Bhai')
        ->and($person->sort_order)->toBe(3)
        ->and($person->is_active)->toBeFalse();

    $this->actingAs($this->admin)->delete(route('sales-persons.destroy', $person))->assertRedirect();

    expect(SalesPerson::whereKey($person->id)->exists())->toBeFalse();
});

it('rejects a duplicate name', function () {
    SalesPerson::create(['name' => 'Shilpa Soni']);

    $this->actingAs($this->admin)->post(route('sales-persons.store'), ['name' => 'Shilpa Soni'])
        ->assertSessionHasErrors('name');

    expect(SalesPerson::count())->toBe(1);
});

it('lets a sales user read the master but not change it', function () {
    $person = SalesPerson::create(['name' => 'Shilpa Soni']);

    $this->actingAs($this->sales)->get(route('sales-persons.index'))->assertOk();

    $this->actingAs($this->sales)->get(route('sales-persons.create'))->assertForbidden();
    $this->actingAs($this->sales)->post(route('sales-persons.store'), ['name' => 'X'])->assertForbidden();
    $this->actingAs($this->sales)->delete(route('sales-persons.destroy', $person))->assertForbidden();
});
