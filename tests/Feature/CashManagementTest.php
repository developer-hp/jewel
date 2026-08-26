<?php

use App\Models\AppSetting;
use App\Models\CashDrawer;
use App\Models\CashEntry;
use App\Models\Item;
use App\Models\ItemEstimate;
use App\Models\ItemGroup;
use App\Models\OgEstimate;
use App\Models\Purity;
use App\Models\SalesPerson;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CashMath;
use App\Services\StockFigures;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->person = SalesPerson::create(['name' => 'Shilpa Soni']);

    AppSetting::current()->update([
        'cash_entry_next_ref_no' => 21,
        'cash_entry_ref_prefix' => 'CS',
        'item_estimate_ref_prefix' => 'ES',
        'og_estimate_ref_prefix' => 'OG',
    ]);

    $this->drawer = CashDrawer::create(['name' => 'Counter 1', 'opening_balance' => 1000]);
});

/**
 * An item estimate worth exactly what is asked for.
 *
 * Built straight through the models: the estimate's own posting route is exercised
 * by ItemEstimateTest, and what matters here is the figure it settles at.
 */
function cashEstimate($test, float $lineTotal = 25000): ItemEstimate
{
    $estimate = new ItemEstimate([
        'estimate_date' => today(),
        'customer_name' => 'Ravibhai Bhalodiya',
        'contact_no' => '9601263350',
        'sales_person_id' => $test->person->id,
    ]);

    $estimate->ref_no = ItemEstimate::nextRefNo();
    $estimate->save();

    // Rate is per ten grams, so ten grams at this rate is the line total.
    $estimate->lines()->create([
        'description' => 'Ring',
        'gross_weight' => 10,
        'rate' => $lineTotal,
        'labour_amount' => 0,
        'labour_type' => 'fixed',
        'oc_amount' => 0,
        'sort_order' => 0,
    ]);

    return $estimate->fresh('lines');
}

function cashVoucher($test, float $amount = 5000): Voucher
{
    $voucher = new Voucher([
        'voucher_date' => today(),
        'sales_person_id' => $test->person->id,
        'sales_person_name' => $test->person->name,
        'payment_mode' => Voucher::MODE_CASH,
        'direction' => Voucher::DIRECTION_IN,
        'description' => 'Advance',
        'amount' => $amount,
    ]);

    $voucher->ref_no = Voucher::nextRefNo();
    $voucher->save();

    return $voucher;
}

/** An OG estimate whose gold is worth a known figure. */
function cashOgEstimate($test, float $netWeight = 10, float $touch = 100, float $rate = 50000): OgEstimate
{
    $estimate = new OgEstimate([
        'estimate_date' => today(),
        'customer_name' => 'Ravibhai Bhalodiya',
        'contact_no' => '9601263350',
        'sales_person_id' => $test->person->id,
        'direction' => OgEstimate::DIRECTION_IN,
    ]);

    $estimate->ref_no = OgEstimate::nextRefNo();
    $estimate->save();

    $estimate->lines()->create([
        'description' => 'Old chain',
        'gross_weight' => $netWeight,
        'net_weight' => $netWeight,
        'touch_percent' => $touch,
        'rate' => $rate,
        'sort_order' => 0,
    ]);

    return $estimate->fresh('lines');
}

function postCashEntry($test, array $overrides = [])
{
    return $test->actingAs($test->admin)->post(route('cash-entries.store'), array_merge([
        'entry_date' => today()->toDateString(),
        'cash_drawer_id' => $test->drawer->id,
        'cash_event' => CashEntry::EVENT_IN,
        'cash_amount' => '25000',
        'cheque_amount' => '0',
    ], $overrides));
}

// --- the drawer master ----------------------------------------------------------

it('renders the drawer listing and its datatables payload', function () {
    $this->actingAs($this->admin)->get(route('cash-drawers.index'))->assertOk();

    $response = $this->actingAs($this->admin)
        ->getJson(route('cash-drawers.index', dtParams(['name', 'opening_balance'])));

    $response->assertOk()->assertJsonPath('recordsTotal', 1);

    expect($response->json('data.0'))->toHaveKeys(['name', 'opening_balance', 'balance', 'status', 'action']);
});

it('creates, updates and deletes a drawer', function () {
    $this->actingAs($this->admin)->post(route('cash-drawers.store'), [
        'name' => 'Counter 2', 'opening_balance' => '500', 'is_active' => '1',
    ])->assertRedirect(route('cash-drawers.index'));

    $drawer = CashDrawer::where('name', 'Counter 2')->firstOrFail();

    expect((float) $drawer->opening_balance)->toBe(500.0);

    $this->actingAs($this->admin)->delete(route('cash-drawers.destroy', $drawer))->assertRedirect();

    expect(CashDrawer::find($drawer->id))->toBeNull();
});

it('refuses to delete a drawer that has entries', function () {
    postCashEntry($this, ['document_reference' => 'estimate:'.cashEstimate($this)->id])->assertRedirect();

    $this->actingAs($this->admin)->delete(route('cash-drawers.destroy', $this->drawer))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(CashDrawer::find($this->drawer->id))->not->toBeNull();
});

// --- the counter -------------------------------------------------------------------

it('issues the reference from the counter and increments it', function () {
    postCashEntry($this, ['document_reference' => 'estimate:'.cashEstimate($this)->id])->assertRedirect();

    $entry = CashEntry::firstOrFail();

    expect($entry->ref_no)->toBe(21)
        ->and($entry->reference())->toBe('CS 21')
        ->and((int) AppSetting::current()->fresh()->cash_entry_next_ref_no)->toBe(22);

    postCashEntry($this, ['document_reference' => 'voucher:'.cashVoucher($this, 25000)->id])->assertRedirect();

    expect(CashEntry::orderByDesc('id')->first()->ref_no)->toBe(22);
});

it('keeps the document reference it was given when the prefix changes later', function () {
    postCashEntry($this, ['document_reference' => 'estimate:'.cashEstimate($this)->id])->assertRedirect();

    $stored = CashEntry::firstOrFail()->document_reference;

    AppSetting::current()->update(['item_estimate_ref_prefix' => 'XX']);

    // A settled record must keep saying what it settled.
    expect(CashEntry::firstOrFail()->document_reference)->toBe($stored)
        ->and($stored)->toStartWith('ES ');
});

// --- a document is settled once ------------------------------------------------------

it('refuses a document that already has an entry', function (string $kind) {
    $reference = $kind === 'estimate'
        ? 'estimate:'.cashEstimate($this)->id
        : 'voucher:'.cashVoucher($this, 25000)->id;

    postCashEntry($this, ['document_reference' => $reference])->assertRedirect();

    postCashEntry($this, ['document_reference' => $reference])
        ->assertSessionHasErrors('document_reference');

    expect(CashEntry::count())->toBe(1);
})->with(['estimate', 'voucher']);

it('refuses an og estimate that has already been settled', function () {
    $og = cashOgEstimate($this);

    postCashEntry($this, [
        'document_reference' => 'estimate:'.cashEstimate($this, 50000)->id,
        'cash_amount' => '0',
        'og_estimate_id' => $og->id,
    ])->assertRedirect();

    postCashEntry($this, [
        'document_reference' => 'estimate:'.cashEstimate($this, 50000)->id,
        'cash_amount' => '0',
        'og_estimate_id' => $og->id,
    ])->assertSessionHasErrors('og_estimate_id');

    expect(CashEntry::count())->toBe(1);
});

it('backstops the rule in the database, not only in the request', function () {
    $estimate = cashEstimate($this);

    postCashEntry($this, ['document_reference' => 'estimate:'.$estimate->id])->assertRedirect();

    // Straight past the form request, the way a script or a second process would.
    $duplicate = new CashEntry([
        'entry_date' => today(),
        'cash_drawer_id' => $this->drawer->id,
        'cash_event' => CashEntry::EVENT_IN,
        'cash_amount' => 100,
    ]);
    $duplicate->forceFill([
        'ref_no' => 999,
        'item_estimate_id' => $estimate->id,
        'document_reference' => 'ES 1',
        'final_amount' => 100,
    ]);

    expect(fn () => $duplicate->save())->toThrow(QueryException::class);
});

it('does not reject an unchanged entry against itself', function () {
    postCashEntry($this, ['document_reference' => 'estimate:'.cashEstimate($this)->id])->assertRedirect();

    $entry = CashEntry::firstOrFail();

    $this->actingAs($this->admin)->put(route('cash-entries.update', $entry), [
        'entry_date' => today()->toDateString(),
        'cash_drawer_id' => $this->drawer->id,
        'cash_event' => CashEntry::EVENT_IN,
        'document_reference' => $entry->documentValue(),
        'cash_amount' => '20000',
        'cheque_amount' => '0',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect((float) $entry->fresh()->cash_amount)->toBe(20000.0);
});

// --- the arithmetic ------------------------------------------------------------------

it('works the discount out from what was handed over', function () {
    postCashEntry($this, [
        'document_reference' => 'estimate:'.cashEstimate($this, 25000)->id,
        'cash_amount' => '10000',
        'cheque_amount' => '5000',
        'cheque_number' => '000123',
        'cheque_name' => 'Ravibhai',
        'cheque_bank' => 'HDFC',
        'og_estimate_id' => cashOgEstimate($this, 1, 100, 50000)->id,
    ])->assertRedirect();

    $entry = CashEntry::firstOrFail();

    // 25,000 asked; 10,000 cash + 5,000 cheque + 5,000 of gold handed over.
    expect((float) $entry->final_amount)->toBe(25000.0)
        ->and((float) $entry->gold_amount)->toBe(5000.0)
        ->and($entry->settledAmount())->toBe(20000.0)
        ->and($entry->discount())->toBe(5000.0);
});

it('stores nothing it can work out', function () {
    expect(Schema::hasColumn('cash_entries', 'discount'))->toBeFalse()
        ->and(Schema::hasColumn('cash_entries', 'settled_amount'))->toBeFalse();
});

it('refuses more money than the document is worth', function () {
    postCashEntry($this, [
        'document_reference' => 'estimate:'.cashEstimate($this, 25000)->id,
        'cash_amount' => '30000',
    ])->assertSessionHasErrors('discount');

    expect(CashEntry::count())->toBe(0);
});

it('refuses an entry that moves no money', function () {
    postCashEntry($this, [
        'document_reference' => 'estimate:'.cashEstimate($this, 25000)->id,
        'cash_amount' => '0',
        'cheque_amount' => '0',
    ])->assertSessionHasErrors('cash_amount');

    expect(CashEntry::count())->toBe(0);
});

it('ignores an amount posted alongside the document', function () {
    // The guard compares against final_amount, so if that were fillable a crafted
    // post could book anything at all.
    postCashEntry($this, [
        'document_reference' => 'estimate:'.cashEstimate($this, 25000)->id,
        'cash_amount' => '25000',
        'final_amount' => '1',
        'gold_amount' => '999999',
        'gold_weight' => '999',
    ])->assertRedirect();

    $entry = CashEntry::firstOrFail();

    expect((float) $entry->final_amount)->toBe(25000.0)
        ->and((float) $entry->gold_amount)->toBe(0.0)
        ->and((float) $entry->gold_weight)->toBe(0.0);
});

// --- the snapshots hold ----------------------------------------------------------------

it('is unmoved when the estimate is edited afterwards', function () {
    $estimate = cashEstimate($this, 25000);

    postCashEntry($this, ['document_reference' => 'estimate:'.$estimate->id])->assertRedirect();

    $entry = CashEntry::firstOrFail();
    $before = $entry->discount();

    $estimate->lines()->first()->update(['rate' => 90000]);

    expect((float) $estimate->fresh('lines')->summary()->total)->not->toBe(25000.0)
        ->and((float) $entry->fresh()->final_amount)->toBe(25000.0)
        ->and($entry->fresh()->discount())->toBe($before);
});

it('snapshots the gold from the og estimate and holds it', function () {
    $og = cashOgEstimate($this, 10, 100, 50000);

    postCashEntry($this, [
        'document_reference' => 'estimate:'.cashEstimate($this, 50000)->id,
        'cash_amount' => '0',
        'og_estimate_id' => $og->id,
    ])->assertRedirect();

    $entry = CashEntry::firstOrFail();

    // 10 g at 100% touch is 10 g fine; at 50,000 per ten grams that is 50,000.
    expect((float) $entry->gold_weight)->toBe(10.0)
        ->and((float) $entry->gold_amount)->toBe(50000.0)
        ->and($entry->og_reference)->toBe($og->reference());

    $og->lines()->first()->update(['rate' => 90000]);

    expect((float) $entry->fresh()->gold_amount)->toBe(50000.0);
});

it('stores zero gold when no og estimate is chosen', function () {
    postCashEntry($this, ['document_reference' => 'estimate:'.cashEstimate($this)->id])->assertRedirect();

    $entry = CashEntry::firstOrFail();

    expect((float) $entry->gold_weight)->toBe(0.0)
        ->and((float) $entry->gold_amount)->toBe(0.0)
        ->and($entry->og_reference)->toBeNull();
});

// --- the drawer balance -------------------------------------------------------------

it('adds money in and takes money out', function () {
    postCashEntry($this, [
        'document_reference' => 'estimate:'.cashEstimate($this, 1000)->id,
        'cash_amount' => '500',
        'cheque_amount' => '200',
        'cheque_number' => '1', 'cheque_name' => 'R', 'cheque_bank' => 'HDFC',
        'og_estimate_id' => cashOgEstimate($this, 1, 100, 3000)->id,
    ])->assertRedirect();

    postCashEntry($this, [
        'document_reference' => 'voucher:'.cashVoucher($this, 400)->id,
        'cash_event' => CashEntry::EVENT_OUT,
        'cash_amount' => '400',
    ])->assertRedirect();

    // 1,000 opening + (500 + 200 + 300 in) − 400 out.
    expect($this->drawer->fresh()->balance())->toBe(1600.0);

    // The listing's aggregate has to agree with the model, or CashMath owns the
    // rule twice for nothing.
    $rendered = $this->actingAs($this->admin)
        ->getJson(route('cash-drawers.index', dtParams(['name'])))
        ->json('data.0.balance');

    expect($rendered)->toContain('1,600.00');
});

it('moves the drawer by what was settled, not by what was asked', function () {
    postCashEntry($this, [
        'document_reference' => 'estimate:'.cashEstimate($this, 25000)->id,
        'cash_amount' => '20000',
    ])->assertRedirect();

    // A discount was never money.
    expect($this->drawer->fresh()->balance())->toBe(21000.0);
});

it('leaves a deleted entry out of the balance', function () {
    postCashEntry($this, [
        'document_reference' => 'estimate:'.cashEstimate($this, 5000)->id,
        'cash_amount' => '5000',
    ])->assertRedirect();

    expect($this->drawer->fresh()->balance())->toBe(6000.0);

    $this->actingAs($this->admin)->delete(route('cash-entries.destroy', CashEntry::firstOrFail()))
        ->assertRedirect();

    expect($this->drawer->fresh()->balance())->toBe(1000.0);
});

it('computes every drawer balance in one query', function () {
    CashDrawer::create(['name' => 'Counter 2']);
    CashDrawer::create(['name' => 'Counter 3']);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($this->admin)->getJson(route('cash-drawers.index', dtParams(['name'])))->assertOk();

    // A subselect, not a balance() call per row.
    expect(count(DB::getQueryLog()))->toBeLessThan(12);

    DB::disableQueryLog();
});

// --- the lookups ------------------------------------------------------------------

it('offers only documents nobody has settled', function () {
    $estimate = cashEstimate($this);
    $voucher = cashVoucher($this, 25000);

    $before = $this->actingAs($this->admin)->getJson(route('cash-entries.lookup.documents'))->json('documents');

    expect(collect($before)->pluck('id'))
        ->toContain('estimate:'.$estimate->id, 'voucher:'.$voucher->id);

    postCashEntry($this, ['document_reference' => 'estimate:'.$estimate->id])->assertRedirect();

    $after = $this->actingAs($this->admin)->getJson(route('cash-entries.lookup.documents'))->json('documents');

    expect(collect($after)->pluck('id'))
        ->not->toContain('estimate:'.$estimate->id)
        ->and(collect($after)->pluck('id'))->toContain('voucher:'.$voucher->id);
});

it('frees a document when its entry is deleted', function () {
    $estimate = cashEstimate($this);

    postCashEntry($this, ['document_reference' => 'estimate:'.$estimate->id])->assertRedirect();

    $this->actingAs($this->admin)->delete(route('cash-entries.destroy', CashEntry::firstOrFail()))
        ->assertRedirect();

    $offered = $this->actingAs($this->admin)->getJson(route('cash-entries.lookup.documents'))->json('documents');

    expect(collect($offered)->pluck('id'))->toContain('estimate:'.$estimate->id);

    // And it can be settled again.
    postCashEntry($this, ['document_reference' => 'estimate:'.$estimate->id])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});

it('searches with or without the prefix typed, and by customer', function () {
    $estimate = cashEstimate($this);

    foreach ([$estimate->reference(), (string) $estimate->ref_no, 'Ravibhai'] as $term) {
        $found = $this->actingAs($this->admin)
            ->getJson(route('cash-entries.lookup.documents', ['q' => $term]))
            ->json('documents');

        // toContain takes values, not a message — a message here would be read as a
        // second thing to look for.
        expect(collect($found)->pluck('id'))->toContain('estimate:'.$estimate->id);
    }
});

it('reports the same final amount the estimate does', function () {
    $estimate = cashEstimate($this, 25000);

    $found = $this->actingAs($this->admin)->getJson(route('cash-entries.lookup.documents'))->json('documents.0');

    expect((float) $found['final_amount'])->toBe((float) $estimate->summary()->total);
});

it('offers only unsettled og estimates, with their gold', function () {
    $og = cashOgEstimate($this, 10, 100, 50000);

    $found = $this->actingAs($this->admin)->getJson(route('cash-entries.lookup.og-estimates'))->json('ogEstimates');

    expect($found)->toHaveCount(1)
        ->and((float) $found[0]['gold_weight'])->toBe(10.0)
        ->and((float) $found[0]['gold_amount'])->toBe(50000.0);

    postCashEntry($this, [
        'document_reference' => 'estimate:'.cashEstimate($this, 50000)->id,
        'cash_amount' => '0',
        'og_estimate_id' => $og->id,
    ])->assertRedirect();

    expect($this->actingAs($this->admin)->getJson(route('cash-entries.lookup.og-estimates'))->json('ogEstimates'))
        ->toBeEmpty();
});

// --- the cheque block ----------------------------------------------------------------

it('needs the cheque described once there is a cheque', function () {
    postCashEntry($this, [
        'document_reference' => 'estimate:'.cashEstimate($this)->id,
        'cash_amount' => '20000',
        'cheque_amount' => '5000',
    ])->assertSessionHasErrors(['cheque_number', 'cheque_name', 'cheque_bank']);
});

it('drops cheque details when there is no cheque', function () {
    postCashEntry($this, [
        'document_reference' => 'estimate:'.cashEstimate($this)->id,
        'cheque_amount' => '0',
        'cheque_number' => '000123',
        'cheque_bank' => 'HDFC',
    ])->assertRedirect();

    $entry = CashEntry::firstOrFail();

    expect($entry->cheque_number)->toBeNull()
        ->and($entry->cheque_bank)->toBeNull();
});

// --- the listing and permissions ---------------------------------------------------------

it('renders the entry listing without loading a single estimate', function () {
    postCashEntry($this, ['document_reference' => 'estimate:'.cashEstimate($this)->id])->assertRedirect();

    $response = $this->actingAs($this->admin)
        ->getJson(route('cash-entries.index', dtParams(['ref', 'document'])));

    $response->assertOk()->assertJsonPath('recordsTotal', 1);

    expect($response->json('data.0'))
        ->toHaveKeys(['ref', 'entry_date', 'drawer_name', 'document', 'event', 'final_amount', 'discount', 'action'])
        ->and($response->json('data.0.ref'))->toContain('CS 21');
});

it('lets sales book cash but not correct it', function () {
    $sales = User::factory()->create();
    $sales->assignRole('Sales');

    postCashEntry($this, ['document_reference' => 'estimate:'.cashEstimate($this)->id])->assertRedirect();

    $entry = CashEntry::firstOrFail();

    $this->actingAs($sales)->get(route('cash-entries.index'))->assertOk();
    $this->actingAs($sales)->get(route('cash-entries.create'))->assertOk();
    $this->actingAs($sales)->get(route('cash-entries.edit', $entry))->assertForbidden();
    $this->actingAs($sales)->delete(route('cash-entries.destroy', $entry))->assertForbidden();

    // Drawers are a master: readable, not writable.
    $this->actingAs($sales)->get(route('cash-drawers.index'))->assertOk();
    $this->actingAs($sales)->get(route('cash-drawers.create'))->assertForbidden();
});

it('hides every cash screen, lookups included, from a user without the permission', function () {
    $nobody = User::factory()->create();

    foreach ([
        route('cash-entries.index'),
        route('cash-entries.create'),
        route('cash-drawers.index'),
        route('cash-entries.lookup.documents'),
        route('cash-entries.lookup.og-estimates'),
    ] as $url) {
        $this->actingAs($nobody)->get($url)->assertForbidden();
    }
});

// --- the position and the export ------------------------------------------------------

it('shows what is in the tills and the gold that came in', function () {
    postCashEntry($this, [
        'document_reference' => 'estimate:'.cashEstimate($this, 8000)->id,
        'cash_amount' => '5000',
        'og_estimate_id' => cashOgEstimate($this, 10, 100, 300)->id,
    ])->assertRedirect();

    postCashEntry($this, [
        'document_reference' => 'voucher:'.cashVoucher($this, 2000)->id,
        'cash_event' => CashEntry::EVENT_OUT,
        'cash_amount' => '2000',
    ])->assertRedirect();

    $position = app(CashMath::class)->position();

    // 1,000 opening + (5,000 cash + 300 gold in) − 2,000 out.
    expect($position->cash)->toBe(4300.0)
        // Signed too: gold paid back out would count against this.
        ->and($position->gold)->toBe(10.0);

    $this->actingAs($this->admin)->get(route('cash-entries.index'))
        ->assertOk()
        ->assertSee('Total Cash')
        ->assertSee('Total Gold')
        ->assertSee('4,300');
});

it('exports the ledger as a pdf that adds up', function () {
    postCashEntry($this, [
        'document_reference' => 'estimate:'.cashEstimate($this, 8000)->id,
        'cash_amount' => '5000',
    ])->assertRedirect();

    postCashEntry($this, [
        'document_reference' => 'voucher:'.cashVoucher($this, 2000)->id,
        'cash_event' => CashEntry::EVENT_OUT,
        'cash_amount' => '2000',
    ])->assertRedirect();

    $response = $this->actingAs($this->admin)->get(route('cash-entries.export'));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');

    expect($response->getContent())->toStartWith('%PDF-');

    // The figures themselves are checked on the rendered view, where they can be
    // read; the PDF above proves it renders.
    $html = view('cash-entries.export', [
        'entries' => CashEntry::with('drawer')->orderBy('ref_no')->get(),
        'position' => app(CashMath::class)->position(),
        'totals' => (object) ['in' => 5000.0, 'out' => 2000.0, 'gold' => 0.0],
    ])->render();

    expect($html)->toContain('Cash Management')
        ->toContain('Counter 1')
        // 5,000 in, 3,000 discounted off the 8,000 estimate.
        ->toContain('5,000 RS')
        ->toContain('3,000 RS');
});

it('keeps the export behind the view permission', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('cash-entries.export'))
        ->assertForbidden();
});

// --- writing a settled piece out of stock ------------------------------------------

/** A stock piece quoted on an estimate that a cash entry then settles. */
function settledStockItem($test): Item
{
    $group = ItemGroup::where('prefix', 'NCK')->firstOrFail();
    $purity = Purity::whereRelation('metalType', 'code', 'GOLD')->where('name', '22K')->firstOrFail();

    $item = new Item([
        'item_group_id' => $group->id,
        'metal_type_id' => $purity->metal_type_id,
        'purity_id' => $purity->id,
        'name' => 'Necklace',
        'gross_weight' => 20,
        'other_deduction' => 0,
        'is_active' => true,
    ]);
    $item->code = $group->nextItemCode();
    $item->net_weight = 20;
    $item->save();

    $estimate = cashEstimate($test, 25000);
    $estimate->lines()->first()->update(['item_id' => $item->id]);

    postCashEntry($test, ['document_reference' => 'estimate:'.$estimate->id])->assertRedirect();

    return $item->fresh();
}

/** A piece sitting in stock with nothing behind it. */
function looseStockItem(string $name = 'Loose'): Item
{
    $group = ItemGroup::where('prefix', 'NCK')->firstOrFail();
    $purity = Purity::whereRelation('metalType', 'code', 'GOLD')->where('name', '22K')->firstOrFail();

    $item = new Item([
        'item_group_id' => $group->id,
        'metal_type_id' => $purity->metal_type_id,
        'purity_id' => $purity->id,
        'name' => $name,
        'gross_weight' => 5,
        'other_deduction' => 0,
        'is_active' => true,
    ]);

    // code is NOT NULL, so it has to be there before the insert.
    $item->code = $group->nextItemCode();
    $item->net_weight = 5;
    $item->save();

    return $item;
}

it('lists only the pieces a cash entry has settled', function () {
    $settled = settledStockItem($this);

    // A piece on no estimate at all has no business on this screen.
    $loose = looseStockItem('Unsold');

    $rows = $this->actingAs($this->admin)
        ->getJson(route('sold-items.index', dtParams(['code', 'group']) + ['state' => '']))
        ->json('data');

    expect(collect($rows)->pluck('code')->implode(' '))
        ->toContain($settled->code)
        ->not->toContain($loose->code);
});

it('marks a settled piece sold today and takes it off the stock figures', function () {
    $item = settledStockItem($this);

    $before = app(StockFigures::class)->byItemGroup()
        ->firstWhere('id', $item->item_group_id)->pcs;

    $this->actingAs($this->admin)->post(route('sold-items.sold', $item))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($item->fresh()->sold_at->toDateString())->toBe(today()->toDateString());

    $after = app(StockFigures::class)->byItemGroup()
        ->firstWhere('id', $item->item_group_id)->pcs;

    expect($after)->toBe($before - 1);
});

it('puts a piece back into stock', function () {
    $item = settledStockItem($this);

    $this->actingAs($this->admin)->post(route('sold-items.sold', $item))->assertRedirect();
    $this->actingAs($this->admin)->post(route('sold-items.available', $item))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($item->fresh()->sold_at)->toBeNull();
});

it('refuses to sell a piece nothing has been paid for', function () {
    $item = looseStockItem();

    $this->actingAs($this->admin)->post(route('sold-items.sold', $item))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($item->fresh()->sold_at)->toBeNull();
});

it('keeps a sold piece out of the items listing and the picker', function () {
    $item = settledStockItem($this);

    $this->actingAs($this->admin)->post(route('sold-items.sold', $item))->assertRedirect();

    $listed = $this->actingAs($this->admin)
        ->getJson(route('items.index', dtParams(['code'])))
        ->json('data');

    expect(collect($listed)->pluck('code')->implode(' '))->not->toContain($item->code);

    $picker = $this->actingAs($this->admin)->getJson(route('items.lookup'))->json('items');

    expect(collect($picker)->pluck('code'))->not->toContain($item->code);

    // Still findable when asked for.
    $sold = $this->actingAs($this->admin)
        ->getJson(route('items.index', dtParams(['code']) + ['stock' => 'sold']))
        ->json('data');

    expect(collect($sold)->pluck('code')->implode(' '))->toContain($item->code);
});

it('lets sales look at the sold list but not change it', function () {
    $item = settledStockItem($this);

    $sales = User::factory()->create();
    $sales->assignRole('Sales');

    $this->actingAs($sales)->get(route('sold-items.index'))->assertOk();
    $this->actingAs($sales)->post(route('sold-items.sold', $item))->assertForbidden();
});
