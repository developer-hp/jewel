<?php

use App\Models\InternalStock;
use App\Models\InternalStockEntry;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->sales = User::factory()->create();
    $this->sales->assignRole('Sales');

    $this->fine = InternalStock::create(['name' => 'FINE', 'sort_order' => 2]);
    $this->karigar = InternalStock::create(['name' => 'KARIGAR', 'sort_order' => 0]);
});

/**
 * Put a movement straight into the ledger.
 */
function entry(InternalStock $stock, string $type, float $weight, string $note = 'note'): InternalStockEntry
{
    return $stock->entries()->create([
        'type' => $type,
        'weight' => $weight,
        'note' => $note,
    ]);
}

function postEntry($test, array $overrides = [])
{
    return $test->actingAs($test->admin)->post(route('internal-stock-entries.store'), array_merge([
        'internal_stock_id' => $test->fine->id,
        'type' => 'in',
        'weight' => '10',
        'note' => 'opening',
    ], $overrides));
}

// --- the master --------------------------------------------------------------------

it('creates, updates and deletes a pot', function () {
    $this->actingAs($this->admin)->post(route('internal-stocks.store'), [
        'name' => 'OLD GOLD',
        'sort_order' => 3,
        'reset_on_opening' => '1',
        'is_active' => '1',
    ])->assertRedirect(route('internal-stocks.index'));

    $stock = InternalStock::where('name', 'OLD GOLD')->firstOrFail();

    expect($stock->reset_on_opening)->toBeTrue()
        ->and($stock->sort_order)->toBe(3);

    $this->actingAs($this->admin)->put(route('internal-stocks.update', $stock), [
        'name' => 'OLD GOLD 22K',
    ])->assertRedirect();

    // Both switches were left off the payload, so both come back false.
    expect($stock->refresh()->name)->toBe('OLD GOLD 22K')
        ->and($stock->reset_on_opening)->toBeFalse()
        ->and($stock->is_active)->toBeFalse();

    $this->actingAs($this->admin)->delete(route('internal-stocks.destroy', $stock))->assertRedirect();

    expect(InternalStock::whereKey($stock->id)->exists())->toBeFalse();
});

it('rejects a duplicate name', function () {
    $this->actingAs($this->admin)->post(route('internal-stocks.store'), ['name' => 'FINE'])
        ->assertSessionHasErrors('name');

    expect(InternalStock::where('name', 'FINE')->count())->toBe(1);
});

it('will not delete a pot that has movements against it', function () {
    entry($this->fine, 'opening', 100);

    $this->actingAs($this->admin)->delete(route('internal-stocks.destroy', $this->fine))
        ->assertSessionHas('error');

    expect(InternalStock::whereKey($this->fine->id)->exists())->toBeTrue();
});

it('sets the reset flag straight from the listing', function () {
    expect($this->fine->reset_on_opening)->toBeTrue();

    $this->actingAs($this->admin)
        ->postJson(route('internal-stocks.reset-toggle', $this->fine), ['reset_on_opening' => 0])
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect($this->fine->refresh()->reset_on_opening)->toBeFalse();

    $this->actingAs($this->admin)
        ->postJson(route('internal-stocks.reset-toggle', $this->fine), ['reset_on_opening' => 1])
        ->assertOk();

    expect($this->fine->refresh()->reset_on_opening)->toBeTrue();
});

it('renders the master listing and its payload', function () {
    $this->actingAs($this->admin)->get(route('internal-stocks.index'))->assertOk();

    $response = $this->actingAs($this->admin)->getJson(route('internal-stocks.index', dtParams(['name'])));

    $response->assertOk()->assertJsonPath('recordsTotal', 2);

    expect($response->json('data.0'))->toHaveKeys(['name', 'reset', 'sort_order', 'entries_count', 'status', 'action']);
});

// --- the balance ---------------------------------------------------------------------

it('works the balance out from the entries, with opening counting as in', function () {
    entry($this->fine, 'opening', 442.1);
    entry($this->fine, 'out', 100);
    entry($this->fine, 'out', 5);
    entry($this->fine, 'out', 200);
    entry($this->fine, 'in', 0.5);

    // 442.1 + 0.5 - 305
    expect($this->fine->balance())->toBe(137.6);

    // A pot nothing has moved through holds nothing.
    expect($this->karigar->balance())->toBe(0.0);
});

it('loads every balance in one go for the cards', function () {
    entry($this->fine, 'opening', 442.1);
    entry($this->fine, 'out', 305);
    entry($this->karigar, 'opening', 29.86);

    $stocks = InternalStock::withBalance()->ordered()->get()->keyBy('name');

    // Ordered by sort_order: KARIGAR (0) before FINE (2).
    expect($stocks->keys()->all())->toBe(['KARIGAR', 'FINE'])
        ->and($stocks['FINE']->balance())->toBe(137.1)
        ->and($stocks['KARIGAR']->balance())->toBe(29.86);
});

// --- the ledger ------------------------------------------------------------------------

it('records a movement and shows it in the listing', function () {
    postEntry($this, ['type' => 'opening', 'weight' => '81.88', 'note' => 'opening'])->assertRedirect();

    $response = $this->actingAs($this->admin)
        ->getJson(route('internal-stock-entries.index', dtParams(['stock', 'note'])));

    $response->assertOk()->assertJsonPath('recordsTotal', 1);

    $row = $response->json('data.0');

    expect($row['type_label'])->toBe('Opening')
        ->and($row['stock'])->toBe('FINE')
        // The one weight is read through its direction into two columns.
        ->and($row['gold_in'])->toBe('81.88')
        ->and($row['gold_out'])->toBe('')
        ->and($row)->toHaveKeys(['type_label', 'stock', 'gold_in', 'gold_out', 'note', 'action']);
});

it('puts an out in the out column', function () {
    entry($this->fine, 'opening', 100);

    postEntry($this, ['type' => 'out', 'weight' => '40', 'note' => 'bhangar'])->assertRedirect();

    $row = collect($this->actingAs($this->admin)
        ->getJson(route('internal-stock-entries.index', dtParams(['stock', 'note']) + ['type' => 'out']))
        ->json('data'))->first();

    expect($row['gold_out'])->toBe('40')
        ->and($row['gold_in'])->toBe('')
        ->and($this->fine->refresh()->balance())->toBe(60.0);
});

it('requires a note on every movement', function () {
    postEntry($this, ['note' => ''])->assertSessionHasErrors('note');

    expect(InternalStockEntry::count())->toBe(0);
});

it('filters the ledger by pot and by type', function () {
    entry($this->fine, 'opening', 100);
    entry($this->fine, 'out', 10);
    entry($this->karigar, 'in', 5);

    $ask = fn (array $params) => $this->actingAs($this->admin)
        ->getJson(route('internal-stock-entries.index', dtParams(['stock', 'note']) + $params))
        ->json('recordsTotal');

    expect($ask([]))->toBe(3)
        ->and($ask(['internal_stock_id' => $this->fine->id]))->toBe(2)
        ->and($ask(['type' => 'out']))->toBe(1)
        ->and($ask(['internal_stock_id' => $this->karigar->id, 'type' => 'out']))->toBe(0);
});

// --- overdrawing --------------------------------------------------------------------

it('will not let an out take more than the pot holds', function () {
    entry($this->fine, 'opening', 100);

    postEntry($this, ['type' => 'out', 'weight' => '100.001'])->assertSessionHasErrors('weight');

    expect($this->fine->refresh()->balance())->toBe(100.0);

    // Exactly the balance is fine — it empties the pot.
    postEntry($this, ['type' => 'out', 'weight' => '100'])->assertRedirect();

    expect($this->fine->refresh()->balance())->toBe(0.0);
});

it('lets an existing out be raised without tripping over itself', function () {
    entry($this->fine, 'opening', 100);
    $out = entry($this->fine, 'out', 40);

    // Balance is 60, but this entry's own 40 must come back into account first, so
    // anything up to 100 is allowed.
    $this->actingAs($this->admin)->put(route('internal-stock-entries.update', $out), [
        'internal_stock_id' => $this->fine->id,
        'type' => 'out',
        'weight' => '90',
        'note' => 'raised',
    ])->assertRedirect();

    expect($this->fine->refresh()->balance())->toBe(10.0);

    $this->actingAs($this->admin)->put(route('internal-stock-entries.update', $out), [
        'internal_stock_id' => $this->fine->id,
        'type' => 'out',
        'weight' => '101',
        'note' => 'too much',
    ])->assertSessionHasErrors('weight');

    expect($this->fine->refresh()->balance())->toBe(10.0);
});

it('does not stand in the way of an in', function () {
    postEntry($this, ['type' => 'in', 'weight' => '9999'])->assertRedirect();

    expect($this->fine->refresh()->balance())->toBe(9999.0);
});

it('frees the weight back up when a movement is deleted', function () {
    entry($this->fine, 'opening', 100);
    $out = entry($this->fine, 'out', 40);

    expect($this->fine->balance())->toBe(60.0);

    $this->actingAs($this->admin)->delete(route('internal-stock-entries.destroy', $out))->assertRedirect();

    expect($this->fine->refresh()->balance())->toBe(100.0);
});

// --- screens and the export -----------------------------------------------------------

it('renders the ledger with a card per pot', function () {
    entry($this->fine, 'opening', 442.1);
    entry($this->fine, 'out', 305);

    $this->actingAs($this->admin)->get(route('internal-stock-entries.index'))
        ->assertOk()
        ->assertSee('FINE')
        ->assertSee('137.1 GM')
        ->assertSee('KARIGAR')
        ->assertSee('0 GM');

    $this->actingAs($this->admin)->get(route('internal-stock-entries.create'))
        ->assertOk()
        // The balance travels with the option so the form can show it.
        ->assertSee('data-balance="137.1"', false);
});

it('exports the sheet with its totals', function () {
    entry($this->fine, 'opening', 442.1);
    entry($this->fine, 'out', 305);
    entry($this->karigar, 'opening', 29.86);

    $response = $this->actingAs($this->admin)->get(route('internal-stock-entries.export'));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF-');

    // The totals are of what the filter selected, so check them directly too.
    $html = view('internal-stock-entries.export', [
        'entries' => InternalStockEntry::with('internalStock')->get(),
        'totalIn' => 471.96,
        'totalOut' => 305.0,
    ])->render();

    expect($html)->toContain('471.96')
        ->and($html)->toContain('305')
        ->and($html)->toContain('TOTAL');
});

it('narrows the export to whatever the listing was filtered to', function () {
    entry($this->fine, 'opening', 442.1);
    entry($this->karigar, 'opening', 29.86);

    $this->actingAs($this->admin)
        ->get(route('internal-stock-entries.export', ['internal_stock_id' => $this->karigar->id]))
        ->assertOk();
});

// --- permissions ------------------------------------------------------------------------

it('lets a sales user move gold but not correct the ledger or the master', function () {
    entry($this->fine, 'opening', 100);
    $out = entry($this->fine, 'out', 10);

    $this->actingAs($this->sales)->get(route('internal-stock-entries.index'))->assertOk();
    $this->actingAs($this->sales)->get(route('internal-stock-entries.create'))->assertOk();
    $this->actingAs($this->sales)->get(route('internal-stock-entries.export'))->assertOk();

    $this->actingAs($this->sales)->get(route('internal-stock-entries.edit', $out))->assertForbidden();
    $this->actingAs($this->sales)->delete(route('internal-stock-entries.destroy', $out))->assertForbidden();

    // The master is a master: Sales reads it, a manager runs it.
    $this->actingAs($this->sales)->get(route('internal-stocks.index'))->assertOk();
    $this->actingAs($this->sales)->get(route('internal-stocks.create'))->assertForbidden();
    $this->actingAs($this->sales)
        ->postJson(route('internal-stocks.reset-toggle', $this->fine), ['reset_on_opening' => 0])
        ->assertForbidden();
});

it('hides the module from a user with no permissions', function () {
    $none = User::factory()->create();

    $this->actingAs($this->admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('internal-stock-entries.index'));

    $this->actingAs($none)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(route('internal-stock-entries.index'));

    $this->actingAs($none)->get(route('internal-stock-entries.index'))->assertForbidden();
});
