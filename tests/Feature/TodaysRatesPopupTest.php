<?php

use App\Models\MetalRate;
use App\Models\Purity;
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

it('offers the button on both estimate forms, create and edit', function (string $route) {
    $this->actingAs($this->admin)->get($route)
        ->assertOk()
        ->assertSee("Today's Rates", false)
        ->assertSee('id="todaysRatesModal"', false)
        // Fetched on open, so a form left sitting open still shows current rates.
        ->assertSee(route('rates.snapshot'), false);
})->with([
    fn () => route('og-estimates.create'),
    fn () => route('item-estimates.create'),
]);

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
