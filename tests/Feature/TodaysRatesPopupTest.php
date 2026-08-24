<?php

use App\Models\MetalRate;
use App\Models\OgEstimate;
use App\Models\Purity;
use App\Models\SalesPerson;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;

/**
 * The "Today's Rates" button on the estimate forms, and the fragment behind it.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

it('offers the button on the add form of both estimates', function (string $route) {
    $this->actingAs($this->admin)->get($route)
        ->assertOk()
        // Escaped by default, so the apostrophe is compared the way Blade wrote it.
        ->assertSee("Today's Rates")
        ->assertSee('id="todaysRatesModal"', false)
        // Fetched on open, so a form left sitting open still shows current rates.
        ->assertSee('data-rates-url="'.route('rates.snapshot').'"', false);
})->with([
    fn () => route('og-estimates.create'),
    fn () => route('item-estimates.create'),
]);

it('offers it on the edit form too', function () {
    // Edit as well as add: a rate gets looked up while correcting a saved estimate
    // just as often as while typing a new one.
    $person = SalesPerson::first() ?? SalesPerson::create(['name' => 'Counter']);

    $this->actingAs($this->admin)->post(route('og-estimates.store'), [
        'estimate_date' => today()->toDateString(),
        'customer_name' => 'Ravibhai Bhalodiya',
        'contact_no' => '9601263350',
        'address' => 'Ahmedabad',
        'sales_person_id' => $person->id,
        'order_reference' => 'in',
        'lines' => [['description' => 'Old chain', 'gross_weight' => 10, 'percent' => 90, 'rate' => 60000]],
    ])->assertRedirect();

    $estimate = OgEstimate::latest('id')->firstOrFail();

    $this->actingAs($this->admin)->get(route('og-estimates.edit', $estimate))
        ->assertOk()
        ->assertSee("Today's Rates")
        ->assertSee('id="todaysRatesModal"', false);
});

it('returns the rates fragment with the per-ten-gram figure spelt out', function () {
    $purity = Purity::with('metalType')->first();

    MetalRate::create([
        'purity_id' => $purity->id,
        'effective_date' => today(),
        'rate' => 132920,
        'per_grams' => 10,
        'created_by' => $this->admin->id,
    ]);

    $response = $this->actingAs($this->admin)->get(route('rates.snapshot'));

    $response->assertOk()
        ->assertSee($purity->name)
        ->assertSee('132,920.00')
        // rate_per_gram 13292.0000, so the per-10 g column reads the quoted figure.
        ->assertSee('13,292.0000')
        ->assertSee('132,920.00');
});

it('says so when the day has no rates', function () {
    $this->actingAs($this->admin)->get(route('rates.snapshot'))
        ->assertOk()
        ->assertSee('No rates entered for');
});

it('names the last day that had rates', function () {
    $purity = Purity::first();

    MetalRate::create([
        'purity_id' => $purity->id,
        'effective_date' => today()->subDays(3),
        'rate' => 100000,
        'per_grams' => 10,
        'created_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)->get(route('rates.snapshot'))
        ->assertOk()
        ->assertSee('The last day with rates was')
        ->assertSee(today()->subDays(3)->format('d M Y'));
});

it('refuses the fragment to a user who cannot see rates', function () {
    $user = User::factory()->create();
    $user->assignRole('Sales');

    $expected = $user->can('metal_rate.view') ? 200 : 403;

    $this->actingAs($user)->get(route('rates.snapshot'))->assertStatus($expected);
});
