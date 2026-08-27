<?php

use App\Models\CashCalculator;
use App\Models\CashDrawer;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;

/**
 * The till calculator behind the topbar button.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

/**
 * A note count in the shape the endpoint expects.
 *
 * Named distinctly: Pest's global helpers share one namespace across the whole
 * suite, and a redeclaration is a fatal, not a failure.
 */
function calculatorCounts(array $counter = [], array $safe = []): array
{
    return ['counter' => $counter, 'safe' => $safe];
}

it('offers the calculator in the topbar', function () {
    $this->actingAs($this->admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('id="cashCalculatorModal"', false)
        ->assertSee('ri-calculator-line', false);
});

it('reports the drawer position and an empty count to start with', function () {
    CashDrawer::create(['name' => 'Counter 1', 'opening_balance' => 25000, 'is_active' => true]);

    $data = $this->actingAs($this->admin)->getJson(route('cash-calculator.show'))
        ->assertOk()
        ->json();

    expect($data['expected']['cash'])->toEqual(25000)
        ->and($data['expected']['gold'])->toEqual(0)
        ->and($data['totals']['total'])->toEqual(0)
        ->and($data['saved_at'])->toBeNull()
        // Every denomination is present even before anything is saved, so the modal
        // never has to invent a row. The keys come back as ints: PHP casts numeric
        // string keys on decode, whatever the wire said.
        ->and(array_keys($data['counts']['counter']))
        ->toBe(CashCalculator::DENOMINATIONS);
});

// The figures from the screenshot this was built from, which is the arithmetic the
// shop already trusts: 500x13 + 200x31 + 100x38 + 50x12 + 20x1 + 10x102 = 18,140.
it('totals each column and the two together', function () {
    $counts = calculatorCounts(
        [500 => 13, 200 => 31, 100 => 38, 50 => 12, 20 => 1, 10 => 102],
        [500 => 1400, 100 => 200, 50 => 200, 10 => 400],
    );

    $data = $this->actingAs($this->admin)
        ->postJson(route('cash-calculator.store'), ['counts' => $counts])
        ->assertOk()
        ->json();

    expect($data['totals']['columns']['counter'])->toEqual(18140)
        ->and($data['totals']['columns']['safe'])->toEqual(734000)
        ->and($data['totals']['total'])->toEqual(752140);
});

it('keeps the count for the user who saved it', function () {
    $this->actingAs($this->admin)
        ->postJson(route('cash-calculator.store'), ['counts' => calculatorCounts([500 => 4])])
        ->assertOk();

    $data = $this->actingAs($this->admin)->getJson(route('cash-calculator.show'))->assertOk()->json();

    expect($data['counts']['counter']['500'])->toBe(4)
        ->and($data['totals']['total'])->toEqual(2000)
        ->and($data['saved_at'])->not->toBeNull();
});

// Two people counting at once must not overwrite each other.
it('keeps each user count apart', function () {
    $other = User::factory()->create();
    $other->assignRole('Admin');

    $this->actingAs($this->admin)
        ->postJson(route('cash-calculator.store'), ['counts' => calculatorCounts([500 => 4])])->assertOk();
    $this->actingAs($other)
        ->postJson(route('cash-calculator.store'), ['counts' => calculatorCounts([500 => 9])])->assertOk();

    expect($this->actingAs($this->admin)->getJson(route('cash-calculator.show'))->json('counts.counter.500'))->toBe(4)
        ->and($this->actingAs($other)->getJson(route('cash-calculator.show'))->json('counts.counter.500'))->toBe(9);
});

it('replaces the saved count rather than accumulating rows', function () {
    foreach ([4, 9, 2] as $count) {
        $this->actingAs($this->admin)
            ->postJson(route('cash-calculator.store'), ['counts' => calculatorCounts([500 => $count])])
            ->assertOk();
    }

    expect(CashCalculator::count())->toBe(1)
        ->and(CashCalculator::first()->grid()['counter'][500])->toBe(2);
});

// The posted payload is normalised, never trusted: a stale denomination from an old
// tab must not survive into a total nobody can see on screen.
it('drops a denomination it does not offer', function () {
    $data = $this->actingAs($this->admin)
        ->postJson(route('cash-calculator.store'), [
            'counts' => ['counter' => [500 => 2, 2000 => 5], 'safe' => [], 'vault' => [500 => 99]],
        ])
        ->assertOk()
        ->json();

    expect($data['counts']['counter'])->not->toHaveKey('2000')
        ->and($data['counts'])->not->toHaveKey('vault')
        ->and($data['totals']['total'])->toEqual(1000);
});

it('refuses a negative or non-integer count', function () {
    $this->actingAs($this->admin)
        ->postJson(route('cash-calculator.store'), ['counts' => calculatorCounts([500 => -3])])
        ->assertJsonValidationErrors('counts.counter.500');

    $this->actingAs($this->admin)
        ->postJson(route('cash-calculator.store'), ['counts' => calculatorCounts([500 => 'lots'])])
        ->assertJsonValidationErrors('counts.counter.500');
});

it('cannot be saved against another user by posting an id', function () {
    $victim = User::factory()->create();

    $this->actingAs($this->admin)->postJson(route('cash-calculator.store'), [
        'user_id' => $victim->id,
        'counts' => calculatorCounts([500 => 7]),
    ])->assertOk();

    expect(CashCalculator::where('user_id', $this->admin->id)->exists())->toBeTrue()
        ->and(CashCalculator::where('user_id', $victim->id)->exists())->toBeFalse();
});

it('keeps the drawer position behind the cash permission', function () {
    $nobody = User::factory()->create();

    $this->actingAs($nobody)->getJson(route('cash-calculator.show'))->assertForbidden();
    $this->actingAs($nobody)->postJson(route('cash-calculator.store'), ['counts' => calculatorCounts()])
        ->assertForbidden();

    // And the button is not rendered for them either.
    $this->actingAs($nobody)->get(route('dashboard'))->assertOk()->assertDontSee('cashCalculatorModal');
});
