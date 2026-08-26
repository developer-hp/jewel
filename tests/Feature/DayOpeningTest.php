<?php

use App\Jobs\SendWhatsAppTemplate;
use App\Models\Angadiya;
use App\Models\AppSetting;
use App\Models\CashDrawer;
use App\Models\CashEntry;
use App\Models\InternalStock;
use App\Models\InternalStockEntry;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\ItemEstimate;
use App\Models\Purity;
use App\Models\SalesPerson;
use App\Models\SupplierHisab;
use App\Models\User;
use App\Models\Voucher;
use App\Models\WhatsAppReceiver;
use App\Models\WhatsAppTemplate;
use App\Services\DayOpening;
use App\Services\OpeningReports;
use App\Support\WhatsAppEvent;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Http::preventStrayRequests();
    Storage::fake('public');

    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->person = SalesPerson::create(['name' => 'Shilpa Soni']);
    $this->opening = app(DayOpening::class);
});

/** A stock piece, saved the way the app saves one. */
function openingItem(string $name = 'Necklace'): Item
{
    $group = ItemGroup::where('prefix', 'NCK')->firstOrFail();
    $purity = Purity::whereRelation('metalType', 'code', 'GOLD')->where('name', '22K')->firstOrFail();

    $item = new Item([
        'item_group_id' => $group->id,
        'metal_type_id' => $purity->metal_type_id,
        'purity_id' => $purity->id,
        'name' => $name,
        'gross_weight' => 10,
        'other_deduction' => 0,
        'is_active' => true,
    ]);

    $item->code = $group->nextItemCode();
    $item->net_weight = 10;
    $item->save();

    return $item;
}

/** An item estimate that a cash entry has settled, with the piece on it. */
function openingSettledItem($test, CashDrawer $drawer): Item
{
    $item = openingItem();

    $estimate = new ItemEstimate([
        'estimate_date' => today(),
        'customer_name' => 'Ravibhai',
        'contact_no' => '9601263350',
        'sales_person_id' => $test->person->id,
    ]);
    $estimate->ref_no = ItemEstimate::nextRefNo();
    $estimate->save();

    $estimate->lines()->create([
        'item_id' => $item->id,
        'description' => 'Necklace',
        'gross_weight' => 10,
        'rate' => 5000,
        'labour_amount' => 0,
        'labour_type' => 'fixed',
        'oc_amount' => 0,
        'sort_order' => 0,
    ]);

    $entry = new CashEntry([
        'entry_date' => today(),
        'cash_drawer_id' => $drawer->id,
        'cash_event' => CashEntry::EVENT_IN,
        'cash_amount' => 5000,
    ]);
    $entry->forceFill([
        'ref_no' => CashEntry::nextRefNo(),
        'item_estimate_id' => $estimate->id,
        'document_reference' => $estimate->reference(),
        'final_amount' => 5000,
    ]);
    $entry->save();

    return $item->fresh();
}

// --- the window ---------------------------------------------------------------------

it('covers everything on the first opening, and only what is new after that', function () {
    $first = openingItem('Before');

    expect($this->opening->since()->year)->toBe(1970);

    $this->opening->run();

    $stamped = AppSetting::current()->fresh()->last_opening_at;

    expect($stamped)->not->toBeNull();

    // The next window starts where this one finished.
    expect(app(DayOpening::class)->since()->toDateTimeString())
        ->toBe($stamped->toDateTimeString());

    $this->travel(1)->hours();
    $second = openingItem('After');

    $reports = app(OpeningReports::class);
    $added = $reports->addedItems(app(DayOpening::class)->since(), now());

    // Only the piece added since the boundary.
    expect($added->pluck('id')->all())->toBe([$second->id])
        ->and($added->pluck('id')->all())->not->toContain($first->id);
});

it('reports a piece exactly once however close to the boundary it falls', function () {
    $reports = app(OpeningReports::class);

    $item = openingItem();
    $item->markSold();
    $soldAt = $item->fresh()->sold_at;

    // The window is open on the left and closed on the right, so the instant of the
    // boundary belongs to the earlier opening and never to both.
    expect($reports->soldItems($soldAt->copy()->subSecond(), $soldAt)->pluck('id')->all())
        ->toBe([$item->id]);

    expect($reports->soldItems($soldAt, $soldAt->copy()->addHour())->pluck('id')->all())
        ->toBe([]);
});

// --- what it settles before deleting ---------------------------------------------------

it('marks a settled piece sold before the evidence is deleted', function () {
    $drawer = CashDrawer::create(['name' => 'Counter 1']);
    $item = openingSettledItem($this, $drawer);

    expect($item->sold_at)->toBeNull();

    $summary = $this->opening->run();

    expect($summary['marked_sold'])->toBe(1)
        ->and($item->fresh()->sold_at)->not->toBeNull()
        // And it lands on this opening's own report.
        ->and(collect($summary['reports'])->firstWhere('key', 'sold')['count'])->toBe(1);
});

it('leaves a piece nothing was paid for alone', function () {
    $item = openingItem();

    $this->opening->run();

    expect($item->fresh()->sold_at)->toBeNull();
});

// --- carrying the balances ---------------------------------------------------------------

it('carries a drawer forward on its cash alone', function () {
    $drawer = CashDrawer::create(['name' => 'Counter 1', 'opening_balance' => 1000]);
    $estimate = openingSettledItem($this, $drawer);

    // A second entry mixing cash with a cheque and gold.
    $entry = new CashEntry([
        'entry_date' => today(),
        'cash_drawer_id' => $drawer->id,
        'cash_event' => CashEntry::EVENT_IN,
        'cash_amount' => 200,
        'cheque_amount' => 900,
        'cheque_number' => '1', 'cheque_name' => 'R', 'cheque_bank' => 'HDFC',
    ]);
    $entry->forceFill([
        'ref_no' => CashEntry::nextRefNo(),
        'voucher_id' => tap(new Voucher([
            'voucher_date' => today(), 'payment_mode' => Voucher::MODE_CASH,
            'direction' => Voucher::DIRECTION_IN, 'description' => 'Advance', 'amount' => 1100,
        ]), function ($v) { $v->ref_no = Voucher::nextRefNo(); $v->save(); })->id,
        'document_reference' => 'VC 1',
        'final_amount' => 1100,
        'gold_amount' => 0,
    ]);
    $entry->save();

    $this->opening->run();

    // 1,000 opening + 5,000 cash + 200 cash. The 900 cheque is not in the till.
    expect((float) $drawer->fresh()->opening_balance)->toBe(6200.0);
});

it('resets a pot set to reset, and leaves the others', function () {
    $resets = InternalStock::create(['name' => 'Counter pot', 'reset_on_opening' => true]);
    $keeps = InternalStock::create(['name' => 'Vault', 'reset_on_opening' => false]);

    foreach ([$resets, $keeps] as $stock) {
        InternalStockEntry::create(['internal_stock_id' => $stock->id, 'type' => 'in', 'weight' => 30, 'note' => 'x']);
        InternalStockEntry::create(['internal_stock_id' => $stock->id, 'type' => 'out', 'weight' => 10, 'note' => 'y']);
    }

    $this->opening->run();

    // One line carrying what it holds, and the same balance as before.
    expect($resets->fresh()->entries()->count())->toBe(1)
        ->and($resets->fresh()->entries()->first()->type)->toBe(InternalStockEntry::TYPE_OPENING)
        ->and($resets->fresh()->balance())->toBe(20.0);

    // Untouched: its ledger runs on across days.
    expect($keeps->fresh()->entries()->count())->toBe(2)
        ->and($keeps->fresh()->balance())->toBe(20.0);
});

it('leaves a pot holding nothing with no opening line at all', function () {
    $stock = InternalStock::create(['name' => 'Empty pot', 'reset_on_opening' => true]);

    InternalStockEntry::create(['internal_stock_id' => $stock->id, 'type' => 'in', 'weight' => 5, 'note' => 'x']);
    InternalStockEntry::create(['internal_stock_id' => $stock->id, 'type' => 'out', 'weight' => 5, 'note' => 'y']);

    $this->opening->run();

    expect($stock->fresh()->entries()->count())->toBe(0)
        ->and($stock->fresh()->balance())->toBe(0.0);
});

// --- what it destroys ---------------------------------------------------------------------

it('clears the day for good', function () {
    $drawer = CashDrawer::create(['name' => 'Counter 1']);
    openingSettledItem($this, $drawer);

    Angadiya::create(['name' => 'Courier', 'city' => 'AHD', 'mobile' => '9', 'insurance_amount' => 100]);

    $supplier = App\Models\Supplier::create(['name' => 'Karigar', 'short_name' => 'KRG']);

    $hisab = SupplierHisab::create([
        'hisab_date' => today(), 'supplier_id' => $supplier->id,
        'supplier_label' => 'Karigar', 'fine_baki' => 10, 'cash_baki' => 100,
    ]);
    $hisab->payments()->create(['item_name' => 'Chain', 'gross_weight' => 5, 'touch' => 90, 'sort_order' => 0]);

    $summary = $this->opening->run();

    // Hard deleted, not hidden — withTrashed finds nothing either.
    expect(CashEntry::withTrashed()->count())->toBe(0)
        ->and(ItemEstimate::withTrashed()->count())->toBe(0)
        ->and(Angadiya::withTrashed()->count())->toBe(0)
        ->and(SupplierHisab::withTrashed()->count())->toBe(0)
        ->and(DB::table('item_estimate_lines')->count())->toBe(0)
        ->and(DB::table('supplier_hisab_payments')->count())->toBe(0)
        ->and($summary['deleted']['angadiyas'])->toBe(1);
});

it('leaves the items themselves alone', function () {
    $drawer = CashDrawer::create(['name' => 'Counter 1']);
    $item = openingSettledItem($this, $drawer);

    $this->opening->run();

    // The estimates go; the stock does not.
    expect(Item::find($item->id))->not->toBeNull();
});

// --- the reports -----------------------------------------------------------------------

it('writes three pdfs and links to them absolutely', function () {
    $item = openingItem();
    $item->markSold();

    $summary = $this->opening->run();

    expect($summary['reports'])->toHaveCount(3)
        ->and(collect($summary['reports'])->pluck('key')->all())->toBe(['sold', 'sold-photos', 'added']);

    $files = Storage::disk('public')->files(OpeningReports::DIRECTORY);

    expect($files)->toHaveCount(3);

    foreach ($summary['reports'] as $report) {
        // WhatsApp fetches the link from its own network, so a relative path is no use.
        expect($report['link'])->toStartWith('http');
    }

    foreach ($files as $file) {
        expect(Storage::disk('public')->get($file))->toStartWith('%PDF');
    }
});

// --- sending -----------------------------------------------------------------------------

it('queues every report to every active receiver', function () {
    WhatsAppTemplate::create([
        'event' => WhatsAppEvent::DocumentSent->value,
        'name' => 'file_message', 'language' => 'en', 'is_active' => true,
    ]);

    config([
        'services.whatsapp.token' => 'test-token',
        'services.whatsapp.phone_number_id' => '1051600000',
        'services.whatsapp.country_code' => '91',
    ]);

    WhatsAppReceiver::create(['name' => 'Owner', 'mobile' => '9601263350']);
    WhatsAppReceiver::create(['name' => 'Accountant', 'mobile' => '9825143759']);
    WhatsAppReceiver::create(['name' => 'Left', 'mobile' => '9999999999', 'is_active' => false]);

    Queue::fake();

    $summary = $this->opening->run();

    // Three reports to each of the two active receivers.
    expect($summary['sent_to'])->toBe(6);

    Queue::assertPushed(SendWhatsAppTemplate::class, 6);
});

it('closes the day even when nothing can be sent', function () {
    // No template, no credentials, no receivers — the books still close.
    WhatsAppReceiver::create(['name' => 'Owner', 'mobile' => '9601263350']);

    $summary = $this->opening->run();

    expect($summary['sent_to'])->toBe(0)
        ->and(AppSetting::current()->fresh()->last_opening_at)->not->toBeNull();
});

// --- the command --------------------------------------------------------------------------

it('does nothing unless auto opening is on', function () {
    $item = openingItem();
    $item->markSold();

    $this->artisan('opening:run')
        ->expectsOutputToContain('Auto opening is switched off')
        ->assertSuccessful();

    expect(AppSetting::current()->fresh()->last_opening_at)->toBeNull();
});

it('runs when told to, switched on or not', function () {
    $this->artisan('opening:run --force')->assertSuccessful();

    expect(AppSetting::current()->fresh()->last_opening_at)->not->toBeNull();
});

it('runs on the schedule once it is switched on', function () {
    AppSetting::current()->update(['auto_opening_enabled' => true]);

    $this->artisan('opening:run')->assertSuccessful();

    expect(AppSetting::current()->fresh()->last_opening_at)->not->toBeNull();
});

// --- the screen ------------------------------------------------------------------------------

it('shows what an opening would cover before anyone commits to it', function () {
    $item = openingItem();
    $item->markSold();
    openingItem('Another');

    $this->actingAs($this->admin)->get(route('day-opening.show'))
        ->assertOk()
        ->assertSee('cannot be undone')
        ->assertSee('Items sold')
        ->assertSee('Open the Day');
});

it('opens the day from the screen', function () {
    $this->actingAs($this->admin)->post(route('day-opening.run'))
        ->assertRedirect(route('day-opening.show'))
        ->assertSessionHas('success');

    expect(AppSetting::current()->fresh()->last_opening_at)->not->toBeNull();
});

it('lets sales see the screen but never run it', function () {
    $sales = User::factory()->create();
    $sales->assignRole('Sales');

    // Sales reads the settings — MASTER_MODULES grants that — but closing the day
    // for good is app_setting.edit, which it does not have.
    $this->actingAs($sales)->get(route('day-opening.show'))->assertOk();
    $this->actingAs($sales)->post(route('day-opening.run'))->assertForbidden();

    expect(AppSetting::current()->fresh()->last_opening_at)->toBeNull();
});

it('hides the opening from a user with no settings permission at all', function () {
    $nobody = User::factory()->create();

    $this->actingAs($nobody)->get(route('day-opening.show'))->assertForbidden();
    $this->actingAs($nobody)->post(route('day-opening.run'))->assertForbidden();
});

// --- the receivers ------------------------------------------------------------------------------

it('manages the receivers and flags a number whatsapp cannot reach', function () {
    WhatsAppReceiver::create(['name' => 'Owner', 'mobile' => '9601263350']);
    WhatsAppReceiver::create(['name' => 'Broken', 'mobile' => '12345']);

    $this->actingAs($this->admin)->get(route('whatsapp-receivers.index'))->assertOk();

    $rows = $this->actingAs($this->admin)
        ->getJson(route('whatsapp-receivers.index', dtParams(['name', 'mobile'])))
        ->json('data');

    $sendable = collect($rows)->pluck('sendable')->implode(' ');

    expect($sendable)->toContain('919601263350')
        ->and($sendable)->toContain('Unsendable');
});

it('adds and removes a receiver', function () {
    $this->actingAs($this->admin)->post(route('whatsapp-receivers.store'), [
        'name' => 'Owner', 'mobile' => '9601263350', 'is_active' => '1',
    ])->assertRedirect(route('whatsapp-receivers.index'));

    $receiver = WhatsAppReceiver::firstOrFail();

    $this->actingAs($this->admin)->delete(route('whatsapp-receivers.destroy', $receiver))->assertRedirect();

    expect(WhatsAppReceiver::count())->toBe(0);
});
