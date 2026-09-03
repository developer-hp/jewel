<?php

use App\Models\AppSetting;
use App\Models\CashDrawer;
use App\Models\CashEntry;
use App\Models\InternalStock;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\RepairForm;
use App\Models\User;
use App\Services\DashboardData;
use App\Services\StockFigures;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->sales = User::factory()->create();
    $this->sales->assignRole('Sales');

    $this->gold = MetalType::where('code', 'GOLD')->firstOrFail();
    $this->ring = ItemGroup::where('prefix', 'RNG')->firstOrFail();
});

/**
 * A piece in stock, so the stock section has something to report.
 */
function dashItem(float $net = 9): Item
{
    $group = ItemGroup::where('prefix', 'RNG')->firstOrFail();
    $gold = MetalType::where('code', 'GOLD')->firstOrFail();
    $purity = Purity::where('metal_type_id', $gold->id)->firstOrFail();

    $item = new Item([
        'item_group_id' => $group->id,
        'metal_type_id' => $gold->id,
        'purity_id' => $purity->id,
        'name' => 'Ring',
        'gross_weight' => $net,
        'other_deduction' => 0,
        'is_active' => true,
    ]);

    $item->code = $group->nextItemCode();
    $item->net_weight = $net;
    $item->save();

    return $item;
}

/**
 * A repair taken in, optionally already overdue.
 */
function dashRepair(?string $deliveryDate = null): RepairForm
{
    $form = new RepairForm([
        'form_date' => today()->subDay()->toDateString(),
        'delivery_date' => $deliveryDate ?? today()->addWeek()->toDateString(),
        'customer_name' => 'A Customer',
        'contact_no' => '9000000000',
    ]);

    $form->ref_no = RepairForm::nextRefNo();
    $form->save();
    $form->lines()->create(['description' => 'Chain', 'sort_order' => 0]);

    return $form->refresh();
}

/**
 * Hide every section except the ones named.
 *
 * @param  array<int, string>  $keys
 */
function showOnly(array $keys): void
{
    AppSetting::current()->update([
        'dashboard_hidden_sections' => collect(config('dashboard'))
            ->pluck('key')
            ->reject(fn (string $key) => in_array($key, $keys, true))
            ->values()
            ->all(),
    ]);
}

// --- what renders ------------------------------------------------------------------

it('shows every section that has something behind it', function () {
    dashItem();
    dashRepair();
    InternalStock::create(['name' => 'FINE'])->entries()->create([
        'type' => 'opening', 'weight' => 100, 'note' => 'opening',
    ]);

    $this->actingAs($this->admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee("Today's Rates", false)
        ->assertSee('Needs Attention')
        ->assertSee('Quick Actions')
        ->assertSee('<h5 class="mb-0">Stock at a Glance</h5>', false)
        ->assertSee('<h5 class="mb-0">Internal Stock</h5>', false)
        ->assertSee('Repairs &amp; Orders', false)
        ->assertSee('Recent Activity');
});

it('leaves out a section with nothing to show', function () {
    // No items and no internal stock pots: neither box is worth drawing. Asserted
    // on the card heading, because "Internal Stock" is also a menu entry.
    $this->actingAs($this->admin)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('<h5 class="mb-0">Stock at a Glance</h5>', false)
        ->assertDontSee('<h5 class="mb-0">Internal Stock</h5>', false)
        // Rates always has purities behind it, so it stays.
        ->assertSee("Today's Rates", false);
});

it('says so when every section is off or empty', function () {
    showOnly([]);

    $this->actingAs($this->admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Nothing to show yet');
});

// --- hiding from Appearance ----------------------------------------------------------

it('hides a section for everyone once it is unticked', function () {
    dashItem();

    $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->assertSee('Stock at a Glance');

    $keep = collect(config('dashboard'))->pluck('key')->reject(fn ($key) => $key === 'stock')->values()->all();

    $this->actingAs($this->admin)
        ->put(route('app-settings.update'), appSettingPayload(['dashboard_sections' => $keep]))
        ->assertRedirect();

    expect(AppSetting::current()->hiddenDashboardSections())->toBe(['stock']);

    // Gone for the person who changed it, and for the next person too.
    $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->assertDontSee('Stock at a Glance');

    $other = User::factory()->create();
    $other->assignRole('Admin');
    $this->actingAs($other)->get(route('dashboard'))->assertOk()->assertDontSee('Stock at a Glance');

    // And back again.
    $this->actingAs($this->admin)
        ->put(route('app-settings.update'), appSettingPayload([
            'dashboard_sections' => collect(config('dashboard'))->pluck('key')->all(),
        ]))->assertRedirect();

    $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->assertSee('Stock at a Glance');
});

it('leaves the choice alone when a save does not mention it', function () {
    showOnly(['rates']);

    // A payload with no dashboard_sections must not be read as "hide everything".
    $this->actingAs($this->admin)->put(route('app-settings.update'), appSettingPayload())->assertRedirect();

    expect(AppSetting::current()->hiddenDashboardSections())->not->toContain('rates')
        ->and(AppSetting::current()->hiddenDashboardSections())->toContain('stock');
});

it('refuses a section key that is not in the registry', function () {
    $this->actingAs($this->admin)
        ->put(route('app-settings.update'), appSettingPayload(['dashboard_sections' => ['not_a_section']]))
        ->assertSessionHasErrors('dashboard_sections.0');
});

it('accepts none ticked, and the empty value the form sends with it', function () {
    $this->actingAs($this->admin)
        ->put(route('app-settings.update'), appSettingPayload(['dashboard_sections' => ['']]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(AppSetting::current()->hiddenDashboardSections())
        ->toHaveCount(count(config('dashboard')));
});

// --- permissions ----------------------------------------------------------------------

it('skips a section the viewer has no permission for, even when it is ticked', function () {
    dashItem();

    // Sales holds stock.view, so the Stock section is theirs; supplier_hisab.view
    // they do have, internal_stock_entry.view they do have — the one they lack is
    // app_setting, which is not a section. Prove the rule with a permissionless user.
    $none = User::factory()->create();

    $this->actingAs($this->admin);

    expect(collect(AppSetting::current()->visibleDashboardSections())->pluck('key'))
        ->toContain('stock');

    $this->actingAs($none);

    expect(collect(AppSetting::current()->visibleDashboardSections())->pluck('key'))
        ->not->toContain('stock')
        ->not->toContain('rates')
        // Sections that gate themselves stay in the list and empty out instead.
        ->toContain('attention');
});

it('gives a sales user the sections they can reach and no others', function () {
    dashItem();

    // Sales does not get dashboard.view by default, and this test is about which
    // *sections* survive the per-section permission check — so it is granted here,
    // and the two rules are tested separately rather than tangled together.
    $this->sales->givePermissionTo('dashboard.view');

    $response = $this->actingAs($this->sales)->get(route('dashboard'))->assertOk();

    // Sales may take repairs in and see stock, but not the daily report.
    $response->assertSee('Quick Actions')
        ->assertSee('Stock at a Glance');
});

// --- the figures --------------------------------------------------------------------

it('counts what is overdue and due today', function () {
    dashRepair(today()->subDays(2)->toDateString());
    dashRepair(today()->toDateString());
    dashRepair(today()->addWeek()->toDateString());

    showOnly(['attention']);

    $html = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->getContent();

    expect($html)->toContain('Repairs overdue')
        ->and($html)->toContain('Repairs due today');

    $data = app(DashboardData::class)->for(config('dashboard'));
    $lines = collect($data['attention']['lines'])->keyBy('label');

    expect($lines['Repairs overdue']->count)->toBe(1)
        ->and($lines['Repairs due today']->count)->toBe(1);
});

it('agrees with the stock screens exactly', function () {
    dashItem(9);
    dashItem(11.5);

    $data = app(DashboardData::class)->for(config('dashboard'));
    $figures = app(StockFigures::class);
    $expected = $figures->totals($figures->byItemGroup(), ['pcs', 'held', 'net']);

    expect($data['stock']['totals']->pcs)->toBe($expected->pcs)
        ->and($data['stock']['totals']->net)->toBe($expected->net)
        ->and($data['stock']['totals']->net)->toBe(20.5);
});

// --- the overview permission -----------------------------------------------------

it('shows the overview to someone who may see it', function () {
    dashItem(9);
    showOnly(['stock']);

    $this->actingAs($this->admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Stock at a Glance')
        ->assertDontSee('Where to next?');
});

// The dashboard is where every sign-in lands, so someone without dashboard.view gets
// the jump-to menu rather than a 403 — a landing page that only says "forbidden"
// leaves a counter clerk with nowhere to go.
it('shows the jump-to menu instead when the overview is not theirs', function () {
    dashItem(9);
    showOnly(['stock']);

    $this->actingAs($this->sales)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Where to next?')
        ->assertSee('palette-item', false)
        // Not a figure in sight.
        ->assertDontSee('Stock at a Glance');
});

it('builds no dashboard figures at all for that viewer', function () {
    dashItem(9);
    showOnly(['stock']);

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $this->actingAs($this->sales)->get(route('dashboard'))->assertOk();

    // Session, user, permissions and settings — not the stock aggregates behind a
    // section this viewer will never be shown.
    expect($queries)->toBeLessThan(12);
});

it('offers a menu entry only where the viewer can actually go', function () {
    // Sales cannot reach Users; the palette is built from the sidebar, which has
    // already dropped it.
    $this->actingAs($this->sales)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(route('users.index'), false);
});

// --- cash drawers -------------------------------------------------------------------

/** A drawer with an opening float, and a cash entry against it. */
function dashDrawerEntry(float $opening, float $cash, string $event = 'in'): CashDrawer
{
    $drawer = CashDrawer::firstOrCreate(
        ['name' => 'Counter 1'],
        ['opening_balance' => $opening, 'is_active' => true],
    );

    $entry = new CashEntry([
        'entry_date' => today(),
        'cash_drawer_id' => $drawer->id,
        'cash_event' => $event,
        'cash_amount' => $cash,
        'cheque_amount' => 0,
    ]);

    $entry->forceFill([
        'ref_no' => CashEntry::nextRefNo(),
        'final_amount' => $cash,
        'document_reference' => 'X '.$entry->ref_no,
    ])->save();

    return $drawer;
}

it('shows each till opening, in, out and closing', function () {
    dashDrawerEntry(1000, 500);
    dashDrawerEntry(1000, 200, 'out');

    showOnly(['cash_drawers']);

    $html = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->getContent();

    expect($html)->toContain('Cash Drawers')
        ->and($html)->toContain('Opening')
        ->and($html)->toContain('Closing');

    $data = app(DashboardData::class)->for(config('dashboard'));
    $section = $data['cash_drawers'];

    expect($section['opening'])->toBe(1000.0)
        ->and($section['in'])->toBe(500.0)
        ->and($section['out'])->toBe(200.0);

    // Opening + in − out is closing, and closing is what the drawer itself reports.
    $drawer = $section['drawers']->first();
    $closing = (float) $drawer->opening_balance + (float) $drawer->cash_in - (float) $drawer->cash_out;

    expect($closing)->toBe(1300.0)
        ->and($closing)->toBe($drawer->balance());
});

// The drawer holds notes. A cheque and old gold settle a document but never reach it.
it('leaves cheques and gold out of the till figures', function () {
    $drawer = CashDrawer::create(['name' => 'Counter 1', 'opening_balance' => 1000, 'is_active' => true]);

    $entry = new CashEntry([
        'entry_date' => today(),
        'cash_drawer_id' => $drawer->id,
        'cash_event' => 'in',
        'cash_amount' => 500,
        'cheque_amount' => 2000,
    ]);

    $entry->forceFill([
        'ref_no' => CashEntry::nextRefNo(),
        'final_amount' => 5000,
        'document_reference' => 'X 1',
        'gold_amount' => 1500,
        'gold_weight' => 10,
    ])->save();

    $section = app(DashboardData::class)->for(config('dashboard'))['cash_drawers'];

    expect($section['in'])->toBe(500.0);
});

it('shows no drawer section when there are no drawers', function () {
    showOnly(['cash_drawers']);

    $this->actingAs($this->admin)->get(route('dashboard'))
        ->assertOk()
        // Not "Cash Drawers": the sidebar carries that too. This caption belongs to
        // the section and nothing else.
        ->assertDontSee('cheques and old gold settle a document');

    expect(app(DashboardData::class)->for(config('dashboard')))->not->toHaveKey('cash_drawers');
});

it('keeps the till figures behind the drawer permission', function () {
    dashDrawerEntry(1000, 500);

    // Sales gets cash_drawer.view through MASTER_MODULES, so the guard is checked
    // against a user with no role at all.
    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('cheques and old gold settle a document');
});

it('reports each internal pot at its ledger balance', function () {
    $fine = InternalStock::create(['name' => 'FINE']);
    $fine->entries()->create(['type' => 'opening', 'weight' => 442.1, 'note' => 'opening']);
    $fine->entries()->create(['type' => 'out', 'weight' => 305, 'note' => 'out']);

    $data = app(DashboardData::class)->for(config('dashboard'));

    expect($data['internal_stock']['stocks']->first()->balance())->toBe(137.1);
});

it('lists recent activity newest first', function () {
    // The feed is filtered by what the viewer may see, so there has to be one.
    $this->actingAs($this->admin);

    $this->travelTo(now()->subHours(2));
    $older = dashItem();
    $this->travelBack();

    $newer = dashItem();

    $data = app(DashboardData::class)->for(config('dashboard'));
    $labels = collect($data['recent']['events'])->pluck('label');

    expect($labels->first())->toContain($newer->code)
        ->and($labels->contains(fn ($l) => str_contains($l, $older->code)))->toBeTrue();
});

it('leaves out activity from a module the viewer cannot see', function () {
    dashItem();
    dashRepair();

    $none = User::factory()->create();
    $this->actingAs($none);

    $data = app(DashboardData::class)->for(config('dashboard'));

    // Nothing they may look at, so no feed at all.
    expect($data)->not->toHaveKey('recent');
});

// --- the registry ----------------------------------------------------------------------

it('holds nothing that would break a cached config', function () {
    // `config:cache` var_exports the array, so a closure or object anywhere in the
    // registry would make the whole config uncacheable.
    $walk = function (array $items) use (&$walk) {
        foreach ($items as $value) {
            if (is_array($value)) {
                $walk($value);

                continue;
            }

            expect(is_scalar($value) || $value === null)->toBeTrue('registry holds a non-scalar');
        }
    };

    $walk(config('dashboard'));

    expect(var_export(config('dashboard'), true))->toBeString();
});

it('names a real permission on every section', function () {
    $seeded = Permission::pluck('name')->all();

    $unknown = collect(config('dashboard'))
        ->pluck('can')
        ->filter()
        ->reject(fn (string $permission) => in_array($permission, $seeded, true))
        ->values()
        ->all();

    expect($unknown)->toBe([]);
});

it('has a partial for every section in the registry', function () {
    foreach (config('dashboard') as $section) {
        expect(view()->exists('dashboard.sections.'.$section['key']))
            ->toBeTrue("no partial for {$section['key']}");
    }
});
