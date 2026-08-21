<?php

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->sales = User::factory()->create();
    $this->sales->assignRole('Sales');
});

// --- the number is the identity ---------------------------------------------------

it('derives a digits-only key from however the number was typed', function (string $typed) {
    $customer = Customer::create(['name' => 'Mamta Ben', 'phone' => $typed]);

    expect($customer->phone_key)->toBe('8291711357')
        // As typed is kept for display; matching ignores the punctuation.
        ->and($customer->phone)->toBe($typed)
        ->and(Customer::findByPhone('8291711357')->is($customer))->toBeTrue()
        ->and(Customer::findByPhone('829-171 1357')->is($customer))->toBeTrue();
})->with(['8291711357', '8291 711357', '829-171-1357', '(829) 171 1357']);

it('finds nobody for a blank or digitless number', function () {
    Customer::create(['name' => 'Mamta Ben', 'phone' => '8291711357']);

    expect(Customer::findByPhone(null))->toBeNull()
        ->and(Customer::findByPhone(''))->toBeNull()
        ->and(Customer::findByPhone('n/a'))->toBeNull();
});

it('remembers a customer on first contact and returns the same one after', function () {
    $first = Customer::rememberByPhone('8291 711357', 'Mamta Ben', 'Naranpura');

    expect($first)->not->toBeNull()
        ->and($first->name)->toBe('Mamta Ben')
        ->and($first->address)->toBe('Naranpura');

    // Same number, differently punctuated and differently typed name: the register
    // is not rewritten, the existing person is returned.
    $again = Customer::rememberByPhone('829-171-1357', 'MAMTA BEN GOHEL', 'Somewhere else');

    expect($again->is($first))->toBeTrue()
        ->and($again->name)->toBe('Mamta Ben')
        ->and($again->address)->toBe('Naranpura')
        ->and(Customer::count())->toBe(1);
});

it('refuses to remember a number with no digits in it', function () {
    expect(Customer::rememberByPhone('', 'Nobody'))->toBeNull()
        ->and(Customer::rememberByPhone(null, 'Nobody'))->toBeNull()
        ->and(Customer::count())->toBe(0);
});

// --- the master -------------------------------------------------------------------

it('renders the listing and its datatables payload', function () {
    Customer::create(['name' => 'Mamta Ben', 'phone' => '8291711357', 'address' => 'Naranpura']);

    $this->actingAs($this->admin)->get(route('customers.index'))->assertOk();

    $response = $this->actingAs($this->admin)
        ->getJson(route('customers.index', dtParams(['name', 'phone', 'address'])));

    $response->assertOk()->assertJsonPath('recordsTotal', 1);

    expect($response->json('data.0'))
        ->toHaveKeys(['name', 'phone', 'address', 'repair_forms_count', 'status', 'action']);
});

it('searches the number however it was punctuated', function () {
    Customer::create(['name' => 'Mamta Ben', 'phone' => '8291 711357']);
    Customer::create(['name' => 'Someone Else', 'phone' => '9999999999']);

    foreach (['8291711357', '8291 711357', 'Mamta'] as $term) {
        $response = $this->actingAs($this->admin)->getJson(route('customers.index', dtParams(
            ['name', 'phone', 'address'],
            ['search' => ['value' => $term]],
        )));

        expect($response->json('recordsFiltered'))->toBe(1, "search term: {$term}")
            ->and($response->json('data.0.name'))->toBe('Mamta Ben');
    }
});

it('creates, updates and deletes a customer', function () {
    $this->actingAs($this->admin)->post(route('customers.store'), [
        'name' => 'Mamta Ben',
        'phone' => '8291711357',
        'address' => 'Naranpura',
        'is_active' => '1',
    ])->assertRedirect(route('customers.index'));

    $customer = Customer::firstOrFail();

    expect($customer->name)->toBe('Mamta Ben')
        ->and($customer->phone_key)->toBe('8291711357');

    $this->actingAs($this->admin)->put(route('customers.update', $customer), [
        'name' => 'Mamta Ben Gohel',
        'phone' => '8291 711357',
    ])->assertRedirect();

    expect($customer->refresh()->name)->toBe('Mamta Ben Gohel')
        // Re-punctuating your own number is not a clash with yourself.
        ->and($customer->phone_key)->toBe('8291711357')
        ->and($customer->is_active)->toBeFalse();

    $this->actingAs($this->admin)->delete(route('customers.destroy', $customer))->assertRedirect();

    expect(Customer::whereKey($customer->id)->exists())->toBeFalse();
});

it('rejects a number that already belongs to someone, however it is punctuated', function () {
    Customer::create(['name' => 'Mamta Ben', 'phone' => '8291711357']);

    $this->actingAs($this->admin)->post(route('customers.store'), [
        'name' => 'Impostor',
        'phone' => '829-171 1357',
    ])->assertSessionHasErrors('phone');

    $this->actingAs($this->admin)->post(route('customers.store'), [
        'name' => 'No Number',
        'phone' => 'call the shop',
    ])->assertSessionHasErrors('phone');

    expect(Customer::count())->toBe(1);
});

// --- the lookup the repair form uses -----------------------------------------------

it('looks a customer up by number for the repair form', function () {
    Customer::create(['name' => 'Mamta Ben', 'phone' => '8291 711357', 'address' => 'Naranpura']);

    $this->actingAs($this->admin)->getJson(route('customers.lookup', ['phone' => '8291711357']))
        ->assertOk()
        ->assertJsonPath('customer.name', 'Mamta Ben')
        ->assertJsonPath('customer.address', 'Naranpura');

    // An unknown number is an ordinary answer, not an error.
    $this->actingAs($this->admin)->getJson(route('customers.lookup', ['phone' => '1112223334']))
        ->assertOk()
        ->assertJsonPath('customer', null);

    $this->actingAs($this->admin)->getJson(route('customers.lookup'))
        ->assertOk()
        ->assertJsonPath('customer', null);
});

// --- permissions ---------------------------------------------------------------------

it('lets a sales user run the customer register', function () {
    // Counter staff take repairs in, so they need the register to fill itself.
    $customer = Customer::create(['name' => 'Mamta Ben', 'phone' => '8291711357']);

    $this->actingAs($this->sales)->get(route('customers.index'))->assertOk();
    $this->actingAs($this->sales)->getJson(route('customers.lookup', ['phone' => '8291711357']))->assertOk();
    $this->actingAs($this->sales)->get(route('customers.edit', $customer))->assertOk();
});

it('hides the register from a user with no permissions', function () {
    $none = User::factory()->create();

    $this->actingAs($none)->get(route('customers.index'))->assertForbidden();
    $this->actingAs($none)->getJson(route('customers.lookup', ['phone' => '1']))->assertForbidden();
});
