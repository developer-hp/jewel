<?php

use App\Models\AppSetting;
use App\Models\Supplier;
use App\Models\SupplierHisab;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->sales = User::factory()->create();
    $this->sales->assignRole('Sales');

    $this->supplier = Supplier::create(['name' => 'Bhavesh Jewellers', 'short_name' => 'BHV', 'city' => 'Rajkot']);

    // The rate the sample dockets were produced at: 141900 per 10 g = 14190 per gram.
    AppSetting::current()->update(['hisab_rate_per_10g' => 141900]);
});

/**
 * A hisab owing $fine grams of fine gold and $cash rupees.
 */
function hisab(Supplier $supplier, float $fine = 4.5, float $cash = 0, ?string $date = null): SupplierHisab
{
    return SupplierHisab::create([
        'hisab_date' => $date ?? today()->toDateString(),
        'supplier_id' => $supplier->id,
        'supplier_label' => $supplier->short_name,
        'fine_baki' => $fine,
        'cash_baki' => $cash,
    ]);
}

// --- the rate box ---------------------------------------------------------------

it('saves the rate and shows it back on the listing', function () {
    $this->actingAs($this->admin)->post(route('supplier-hisabs.rate'), ['hisab_rate_per_10g' => 145000])
        ->assertRedirect();

    expect((float) AppSetting::current()->hisab_rate_per_10g)->toBe(145000.0)
        ->and(SupplierHisab::currentRatePerGram())->toBe(14500.0);

    $this->actingAs($this->admin)->get(route('supplier-hisabs.index'))
        ->assertOk()
        ->assertSee('145000', false);
});

// --- entries --------------------------------------------------------------------

it('creates an entry and snapshots how the supplier reads', function () {
    $this->actingAs($this->admin)->post(route('supplier-hisabs.store'), [
        'hisab_date' => today()->toDateString(),
        'supplier_id' => $this->supplier->id,
        'fine_baki' => 4.5,
        'cash_baki' => '',
    ])->assertRedirect();

    $entry = SupplierHisab::firstOrFail();

    expect($entry->supplier_label)->toBe('BHV')
        ->and((float) $entry->fine_baki)->toBe(4.5)
        ->and((float) $entry->cash_baki)->toBe(0.0)
        ->and($entry->isSettled())->toBeFalse();

    // A supplier rename must not rewrite what has already been recorded.
    $this->supplier->update(['short_name' => 'BHV-2']);

    expect($entry->refresh()->supplier_label)->toBe('BHV');
});

it('updates and deletes an entry', function () {
    $entry = hisab($this->supplier);

    $this->actingAs($this->admin)->put(route('supplier-hisabs.update', $entry), [
        'hisab_date' => today()->toDateString(),
        'supplier_id' => $this->supplier->id,
        'fine_baki' => 6,
        'cash_baki' => 500,
    ])->assertRedirect();

    expect((float) $entry->refresh()->fine_baki)->toBe(6.0)
        ->and((float) $entry->cash_baki)->toBe(500.0);

    $this->actingAs($this->admin)->delete(route('supplier-hisabs.destroy', $entry))->assertRedirect();

    expect(SupplierHisab::whereKey($entry->id)->exists())->toBeFalse();
});

it('requires a supplier', function () {
    $this->actingAs($this->admin)->post(route('supplier-hisabs.store'), [
        'hisab_date' => today()->toDateString(),
        'fine_baki' => 4.5,
    ])->assertSessionHasErrors(['supplier_id'], null, 'hisab');

    expect(SupplierHisab::count())->toBe(0);
});

// --- the listing ----------------------------------------------------------------

it('lists only the chosen day and totals it in the footer', function () {
    hisab($this->supplier, 4.5, 0);
    hisab($this->supplier, 4.5, 250);
    hisab($this->supplier, 12, 999, today()->subDay()->toDateString());

    $columns = ['supplier', 'gold_wt', 'amount'];

    $today = $this->actingAs($this->admin)->getJson(route('supplier-hisabs.index', dtParams($columns)));

    $today->assertOk()->assertJsonPath('recordsTotal', 2);

    expect($today->json('totals'))->toBe(['fine_baki' => '9', 'cash_baki' => '250.00'])
        ->and($today->json('data.0'))->toHaveKeys(['select', 'supplier', 'gold_wt', 'amount', 'action']);

    $yesterday = $this->actingAs($this->admin)->getJson(route('supplier-hisabs.index', dtParams($columns) + [
        'date' => today()->subDay()->toDateString(),
    ]));

    expect($yesterday->json('recordsTotal'))->toBe(1)
        ->and($yesterday->json('totals.fine_baki'))->toBe('12');
});

// --- the arithmetic -------------------------------------------------------------

it('derives fine weight from gross weight and touch', function () {
    $entry = hisab($this->supplier, 10);
    $entry->payments()->createMany([
        ['item_name' => 'A', 'gross_weight' => 1.5, 'touch' => 100, 'sort_order' => 0],
        ['item_name' => 'B', 'gross_weight' => 3, 'touch' => 100, 'sort_order' => 1],
        ['item_name' => 'C', 'gross_weight' => 10, 'touch' => 91.6, 'sort_order' => 2],
    ]);

    $entry = $entry->fresh();

    expect($entry->payments[2]->fineWeight())->toBe(9.16)
        ->and($entry->fineKapi())->toBe(13.66)
        ->and($entry->grossPaid())->toBe(14.5);
});

it('turns the fine still owed into cash, rounded to the nearest ten', function () {
    // 4.5 g x 14190 = 63855, which the counter settles at 63860.
    $unpaid = hisab($this->supplier, 4.5, 0)->fresh();

    expect($unpaid->ratePerGram())->toBe(14190.0)
        ->and($unpaid->fineRemaining())->toBe(4.5)
        ->and($unpaid->cashForRemainingFine())->toBe(63855.0)
        ->and($unpaid->cashApvi())->toBe(63860.0);

    // Settled entirely in gold, so nothing is left to convert.
    $paid = hisab($this->supplier, 4.5, 0);
    $paid->payments()->createMany([
        ['item_name' => 'A', 'gross_weight' => 1.5, 'touch' => 100, 'sort_order' => 0],
        ['item_name' => 'B', 'gross_weight' => 3, 'touch' => 100, 'sort_order' => 1],
    ]);

    expect($paid->fresh()->cashApvi())->toBe(0.0);
});

it('carries the outstanding cash into the payout', function () {
    $entry = hisab($this->supplier, 0, 1234)->fresh();

    expect($entry->cashApvi())->toBe(1230.0);
});

// --- settling -------------------------------------------------------------------

it('settles a hisab, dropping blank rows and pinning the rate', function () {
    $entry = hisab($this->supplier, 4.5);

    $this->actingAs($this->admin)->get(route('supplier-hisabs.settle', $entry))->assertOk();

    $this->actingAs($this->admin)->put(route('supplier-hisabs.settle.update', $entry), [
        'rows' => [
            ['item_name' => 'A', 'gross_weight' => '1.5', 'touch' => '100'],
            ['item_name' => '', 'gross_weight' => '', 'touch' => '100'],
            ['item_name' => 'B', 'gross_weight' => '3', 'touch' => '100'],
        ],
    ])->assertRedirect();

    $entry = $entry->fresh();

    expect($entry->payments)->toHaveCount(2)
        ->and($entry->payments->pluck('item_name')->all())->toBe(['A', 'B'])
        ->and($entry->fineKapi())->toBe(4.5)
        ->and($entry->isSettled())->toBeTrue()
        ->and((float) $entry->rate_per_gram)->toBe(14190.0);
});

it('replaces the rows on a second save rather than appending', function () {
    $entry = hisab($this->supplier, 4.5);

    foreach ([['A', '1.5'], ['C', '2']] as [$name, $weight]) {
        $this->actingAs($this->admin)->put(route('supplier-hisabs.settle.update', $entry), [
            'rows' => [['item_name' => $name, 'gross_weight' => $weight, 'touch' => '100']],
        ])->assertRedirect();
    }

    expect($entry->fresh()->payments->pluck('item_name')->all())->toBe(['C']);
});

it('keeps the rate it was settled at when the rate later changes', function () {
    $entry = hisab($this->supplier, 4.5);

    $this->actingAs($this->admin)->put(route('supplier-hisabs.settle.update', $entry), [
        'rows' => [['item_name' => 'A', 'gross_weight' => '1', 'touch' => '100']],
    ])->assertRedirect();

    AppSetting::current()->update(['hisab_rate_per_10g' => 160000]);

    $entry = $entry->fresh();

    expect($entry->ratePerGram())->toBe(14190.0)
        ->and($entry->ratePer10g())->toBe(141900.0);

    // An entry that was never settled quotes the live rate instead.
    expect(hisab($this->supplier, 1)->fresh()->ratePerGram())->toBe(16000.0);
});

// --- printing -------------------------------------------------------------------

it('prints one slip and several on a sheet', function () {
    $one = hisab($this->supplier, 4.5);
    $two = hisab($this->supplier, 2, 100);

    foreach ([[$one->id], [$one->id, $two->id]] as $ids) {
        $response = $this->actingAs($this->admin)->post(route('supplier-hisabs.print'), ['ids' => $ids]);

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        expect($response->getContent())->toStartWith('%PDF-');
    }
});

it('prints the day summary', function () {
    hisab($this->supplier, 4.5, 0);

    $response = $this->actingAs($this->admin)->get(route('supplier-hisabs.summary', ['date' => today()->toDateString()]));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF-');
});

it('prints a summary for a day with nothing on it', function () {
    $this->actingAs($this->admin)
        ->get(route('supplier-hisabs.summary', ['date' => today()->subYear()->toDateString()]))
        ->assertOk();
});

// --- permissions ----------------------------------------------------------------

it('lets a sales user read and print but not change anything', function () {
    $entry = hisab($this->supplier, 4.5);

    $this->actingAs($this->sales)->get(route('supplier-hisabs.index'))->assertOk();
    $this->actingAs($this->sales)->post(route('supplier-hisabs.print'), ['ids' => [$entry->id]])->assertOk();
    $this->actingAs($this->sales)->get(route('supplier-hisabs.summary'))->assertOk();
    $this->actingAs($this->sales)->get(route('supplier-hisabs.settle', $entry))->assertOk();

    $this->actingAs($this->sales)->post(route('supplier-hisabs.store'), [
        'hisab_date' => today()->toDateString(),
        'supplier_id' => $this->supplier->id,
    ])->assertForbidden();

    $this->actingAs($this->sales)->post(route('supplier-hisabs.rate'), ['hisab_rate_per_10g' => 1])->assertForbidden();
    $this->actingAs($this->sales)->put(route('supplier-hisabs.settle.update', $entry), ['rows' => []])->assertForbidden();
    $this->actingAs($this->sales)->delete(route('supplier-hisabs.destroy', $entry))->assertForbidden();
});

it('hides the module from a user with no permissions', function () {
    $none = User::factory()->create();

    $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->assertSee(route('supplier-hisabs.index'));
    $this->actingAs($none)->get(route('dashboard'))->assertOk()->assertDontSee(route('supplier-hisabs.index'));
    $this->actingAs($none)->get(route('supplier-hisabs.index'))->assertForbidden();
});
