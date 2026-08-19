<?php

use App\Models\MetalRate;
use App\Models\Purity;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->gold22 = Purity::whereRelation('metalType', 'code', 'GOLD')->where('name', '22K')->firstOrFail();
    $this->silver999 = Purity::whereRelation('metalType', 'code', 'SILV')->where('name', '999')->firstOrFail();
});

it('derives the per-gram rate from whatever basis was entered', function () {
    $gold = MetalRate::create([
        'purity_id' => $this->gold22->id,
        'effective_date' => today(),
        'rate' => 71500,
        'per_grams' => 10,
    ]);

    $silver = MetalRate::create([
        'purity_id' => $this->silver999->id,
        'effective_date' => today(),
        'rate' => 92000,
        'per_grams' => 1000,
    ]);

    expect((float) $gold->rate_per_gram)->toBe(7150.0)
        ->and((float) $silver->rate_per_gram)->toBe(92.0);
});

it('recomputes the per-gram rate when the basis is edited', function () {
    $rate = MetalRate::create([
        'purity_id' => $this->gold22->id,
        'effective_date' => today(),
        'rate' => 71500,
        'per_grams' => 10,
    ]);

    $rate->update(['per_grams' => 1]);

    expect((float) $rate->fresh()->rate_per_gram)->toBe(71500.0);
});

it('resolves the rate that applied on a past date', function () {
    MetalRate::create(['purity_id' => $this->gold22->id, 'effective_date' => today()->subDays(7), 'rate' => 70000, 'per_grams' => 10]);
    MetalRate::create(['purity_id' => $this->gold22->id, 'effective_date' => today(), 'rate' => 71500, 'per_grams' => 10]);

    expect((float) $this->gold22->rateOn()->rate_per_gram)->toBe(7150.0)
        ->and((float) $this->gold22->rateOn(today()->subDays(7))->rate_per_gram)->toBe(7000.0)
        // Falls back to the most recent rate on or before the date asked for.
        ->and((float) $this->gold22->rateOn(today()->subDays(3))->rate_per_gram)->toBe(7000.0)
        ->and($this->gold22->rateOn(today()->subDays(30)))->toBeNull();
});

it('allows only one rate per purity per day', function () {
    MetalRate::create(['purity_id' => $this->gold22->id, 'effective_date' => today(), 'rate' => 71500, 'per_grams' => 10]);

    $this->actingAs($this->admin)->post(route('rates.store'), [
        'purity_id' => $this->gold22->id,
        'effective_date' => today()->toDateString(),
        'rate' => 72000,
        'per_grams' => 10,
    ])->assertSessionHasErrors('effective_date');

    expect(MetalRate::where('purity_id', $this->gold22->id)->count())->toBe(1);
});

it('saves the whole day of rates from the bulk screen', function () {
    $response = $this->actingAs($this->admin)->post(route('rates.today.store'), [
        'date' => today()->toDateString(),
        'rates' => [
            $this->gold22->id => ['rate' => 71500, 'per_grams' => 10],
            $this->silver999->id => ['rate' => 92000, 'per_grams' => 1000],
        ],
    ]);

    $response->assertRedirect();

    expect((float) $this->gold22->rateOn()->rate_per_gram)->toBe(7150.0)
        ->and((float) $this->silver999->rateOn()->rate_per_gram)->toBe(92.0);
});

it('skips blank boxes rather than wiping rates already entered that day', function () {
    MetalRate::create(['purity_id' => $this->gold22->id, 'effective_date' => today(), 'rate' => 71500, 'per_grams' => 10]);

    $this->actingAs($this->admin)->post(route('rates.today.store'), [
        'date' => today()->toDateString(),
        'rates' => [
            $this->gold22->id => ['rate' => '', 'per_grams' => 10],
            $this->silver999->id => ['rate' => 92000, 'per_grams' => 1000],
        ],
    ]);

    expect((float) $this->gold22->rateOn()->rate_per_gram)->toBe(7150.0)
        ->and((float) $this->silver999->rateOn()->rate_per_gram)->toBe(92.0);
});

it('overwrites the same purity and date instead of creating a duplicate', function () {
    $this->actingAs($this->admin)->post(route('rates.today.store'), [
        'date' => today()->toDateString(),
        'rates' => [$this->gold22->id => ['rate' => 71500, 'per_grams' => 10]],
    ]);

    $this->actingAs($this->admin)->post(route('rates.today.store'), [
        'date' => today()->toDateString(),
        'rates' => [$this->gold22->id => ['rate' => 72000, 'per_grams' => 10]],
    ]);

    expect(MetalRate::where('purity_id', $this->gold22->id)->count())->toBe(1)
        ->and((float) $this->gold22->rateOn()->rate_per_gram)->toBe(7200.0);
});

it('records who entered the rate', function () {
    $this->actingAs($this->admin)->post(route('rates.today.store'), [
        'date' => today()->toDateString(),
        'rates' => [$this->gold22->id => ['rate' => 71500, 'per_grams' => 10]],
    ]);

    expect($this->gold22->rateOn()->created_by)->toBe($this->admin->id);
});
