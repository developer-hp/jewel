<?php

use App\Models\AppSetting;
use App\Models\Item;
use App\Models\ItemEstimate;
use App\Models\ItemEstimateLine;
use App\Models\ItemGroup;
use App\Models\MakingCharge;
use App\Models\MetalRate;
use App\Models\MetalType;
use App\Models\OgEstimate;
use App\Models\OrderForm;
use App\Models\Purity;
use App\Models\SalesPerson;
use App\Models\StoneMaster;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->sales = User::factory()->create();
    $this->sales->assignRole('Sales');

    $this->person = SalesPerson::create(['name' => 'Thankibhai']);
    $this->gold = MetalType::where('code', 'GOLD')->firstOrFail();
    $this->purity = Purity::where('metal_type_id', $this->gold->id)->where('name', '22K')->firstOrFail();

    // The three units the sample's six stone rows use.
    $this->byPiece = StoneMaster::where('rate_unit', 'piece')->where('kind', 'stone')->firstOrFail();
    $this->byGram = StoneMaster::where('rate_unit', 'gram')->where('kind', 'stone')->firstOrFail();
    $this->byFixed = StoneMaster::where('rate_unit', 'fixed')->where('kind', 'stone')->firstOrFail();

    AppSetting::current()->update(['item_estimate_next_ref_no' => 43, 'gst_percent' => 3]);
});

/**
 * The payload one line posts. Defaults reproduce the sample piece.
 */
function estLine(array $overrides = []): array
{
    return array_merge([
        'description' => 'SET',
        'gross_weight' => '142.920',
        'rate' => '132920',
        'labour_amount' => '2791',
        'labour_type' => 'per_gram',
        'oc_amount' => '0',
    ], $overrides);
}

/**
 * The sample's six stone rows.
 *
 * Only the first two carry weight, so the piece loses 38.600 g; the amounts come to
 * 80,574 between them. The units are the point — by the piece, by the gram, and four
 * flat figures — because that is what exercises the whole amount rule.
 *
 * @return array<int, array<string, string|int>>
 */
function estStones($test): array
{
    return [
        // JADTAR: 446 pieces at 90.
        ['stone_master_id' => $test->byPiece->id, 'pieces' => 446, 'weight_grams' => '19.840', 'rate' => '90'],
        // MOTI GRAM: 18.760 g at 900.
        ['stone_master_id' => $test->byGram->id, 'weight_grams' => '18.760', 'rate' => '900'],
        // MOZONITE FIX, FITTING, TAKKAR, RODOLITE: flat figures, no weight.
        ['stone_master_id' => $test->byFixed->id, 'rate' => '6300'],
        ['stone_master_id' => $test->byFixed->id, 'rate' => '1800'],
        ['stone_master_id' => $test->byFixed->id, 'rate' => '8850'],
        ['stone_master_id' => $test->byFixed->id, 'rate' => '6600'],
    ];
}

function postEstimate2($test, array $lines, array $overrides = [])
{
    return $test->actingAs($test->admin)->post(route('item-estimates.store'), array_merge([
        'estimate_date' => today()->toDateString(),
        'customer_name' => 'Ravibhai Bhalodiya',
        'contact_no' => '9601263350',
        'address' => 'Ahmedabad',
        'sales_person_id' => $test->person->id,
        'lines' => $lines,
    ], $overrides));
}

// --- the arithmetic, against the sample -------------------------------------------------

it('prices the sample piece exactly as the paper does', function () {
    postEstimate2($this, [estLine(['stones' => estStones($this)])])->assertRedirect();

    $line = ItemEstimateLine::with('stones')->firstOrFail();

    expect($line->stoneWeight())->toBe(38.6)
        ->and($line->netWeight())->toBe(104.32)
        ->and($line->jadtar())->toBe(80574.0)
        ->and($line->metalValue())->toBe(1386621.44)
        ->and($line->labour())->toBe(291157.12)
        ->and($line->total())->toBe(1758352.56);
});

it('totals the grid, with metal value under the rate column', function () {
    postEstimate2($this, [estLine(['stones' => estStones($this)])])->assertRedirect();

    $totals = ItemEstimate::firstOrFail()->totals();

    expect($totals->gross)->toBe(142.92)
        ->and($totals->net)->toBe(104.32)
        ->and($totals->metal)->toBe(1386621.44)
        ->and($totals->jadtar)->toBe(80574.0)
        ->and($totals->labour)->toBe(291157.12)
        ->and($totals->oc)->toBe(0.0);
});

it('reads labour through its type', function (string $type, string $amount, float $expected) {
    // 10 g net at 100000 the ten grams is 100,000 of metal.
    postEstimate2($this, [estLine([
        'gross_weight' => '10',
        'rate' => '100000',
        'labour_type' => $type,
        'labour_amount' => $amount,
    ])])->assertRedirect();

    expect(ItemEstimateLine::firstOrFail()->labour())->toBe($expected);
})->with([
    // 10 g x 350
    ['per_gram', '350', 3500.0],
    // 12% of 100,000
    ['percentage', '12', 12000.0],
    ['fixed', '2500', 2500.0],
]);

it('itemises other charges so the printed breakdown adds up', function () {
    postEstimate2($this, [estLine(['oc_amount' => '5000', 'stones' => estStones($this)])])->assertRedirect();

    $line = ItemEstimateLine::with('stones')->firstOrFail();

    // The breakdown under the item lists the stones AND the other charges, so what it
    // totals plus metal plus labour is exactly the line total. Leaving OC out of the
    // list was what stopped the printed column reconciling.
    expect($line->charges())->toBe(85574.0)
        ->and($line->charges() + $line->metalValue() + $line->labour())->toBe($line->total());

    $html = view('item-estimates.print', [
        'estimates' => ItemEstimate::with(['lines.stones', 'lines.item', 'ogEstimate'])->get(),
        'firm' => ['name' => '', 'phone' => ''],
    ])->render();

    expect($html)->toContain('>OC</td>')
        // The column footer totals the same set it lists.
        ->and($html)->toContain('TOTAL : '.number_format(85574, 0));
});

// --- the printed box ----------------------------------------------------------------------

it('lands the round-off on the final figure, after GST', function () {
    postEstimate2($this, [estLine(['stones' => estStones($this)])], ['gst_enabled' => '1'])->assertRedirect();

    $summary = ItemEstimate::firstOrFail()->summary();

    expect($summary->amount)->toBe(1758353.0)
        ->and($summary->gst)->toBe(52751.0)
        ->and($summary->round_off)->toBe(-4.0)
        ->and($summary->total)->toBe(1811100.0)
        // Whatever else is true, the printed column has to add up.
        ->and($summary->amount + $summary->gst + $summary->round_off)->toBe($summary->total);
});

it('drops the tax but still rounds when GST is off', function () {
    postEstimate2($this, [estLine(['stones' => estStones($this)])])->assertRedirect();

    $summary = ItemEstimate::firstOrFail()->summary();

    expect($summary->gst)->toBe(0.0)
        ->and($summary->round_off)->toBe(-3.0)
        ->and($summary->total)->toBe(1758350.0);
});

it('keeps the rate it was taxed at when the setting changes', function () {
    postEstimate2($this, [estLine()], ['gst_enabled' => '1'])->assertRedirect();

    $estimate = ItemEstimate::firstOrFail();

    expect((float) $estimate->gst_percent)->toBe(3.0);

    AppSetting::current()->update(['gst_percent' => 5]);

    expect((float) $estimate->fresh()->gst_percent)->toBe(3.0);
});

// --- items --------------------------------------------------------------------------------

it('hands the form everything a line needs from the chosen piece', function () {
    $charge = MakingCharge::create([
        'code' => 'MC-EST12', 'name' => '12%', 'charge_type' => 'percentage', 'rate' => 12,
    ]);

    $group = ItemGroup::where('prefix', 'RNG')->firstOrFail();
    $item = new Item([
        'item_group_id' => $group->id,
        'metal_type_id' => $this->gold->id,
        'purity_id' => $this->purity->id,
        'making_charge_id' => $charge->id,
        'name' => 'Ring',
        'gross_weight' => 5.5,
        'other_deduction' => 0,
        'extra_charge_1' => 150,
        'extra_charge_2' => 75,
        'is_active' => true,
    ]);
    $item->code = $group->nextItemCode();
    $item->net_weight = 5.5;
    $item->save();

    $found = collect($this->actingAs($this->admin)->getJson(route('items.lookup'))->json('items'))
        ->firstWhere('code', $item->code);

    // Labour, the two extras that become OC, and the stone rows all arrive together.
    expect($found['making_charge'])->toEqual(['charge_type' => 'percentage', 'rate' => 12.0])
        ->and($found['extra_charge_1'] + $found['extra_charge_2'])->toEqual(225)
        ->and($found['gross_weight'])->toEqual(5.5)
        ->and($found)->toHaveKey('stones');
});

it('saves a description-only line with no piece and no stones', function () {
    postEstimate2($this, [estLine(['description' => 'Loose bangle', 'stones' => []])])->assertRedirect();

    $line = ItemEstimateLine::firstOrFail();

    expect($line->item_id)->toBeNull()
        ->and($line->stones)->toHaveCount(0)
        ->and($line->netWeight())->toBe(142.92);
});

it('drops blank rows and needs at least one line', function () {
    postEstimate2($this, [estLine(), ['description' => '', 'gross_weight' => '']])->assertRedirect();

    expect(ItemEstimate::firstOrFail()->lines)->toHaveCount(1);

    postEstimate2($this, [['description' => '', 'gross_weight' => '']])->assertSessionHasErrors('lines');
});

// --- building from an order ------------------------------------------------------------------

it('builds lines from an order, taking the pinned rate where there is one', function () {
    $order = new OrderForm([
        'form_date' => today()->toDateString(),
        'delivery_date' => today()->addWeek()->toDateString(),
        'customer_name' => 'Chetan bhai',
        'contact_no' => '9825143759',
        'sales_person_id' => $this->person->id,
        'sales_person_name' => $this->person->name,
    ]);
    $order->ref_no = OrderForm::nextRefNo();
    $order->save();

    $pinned = $order->lines()->create([
        'description' => 'Bangle', 'net_weight' => 10, 'metal_type_id' => $this->gold->id,
        'purity_id' => $this->purity->id, 'lc_amount' => 350, 'lc_type' => 'per_gram',
        'oc_amount' => 100, 'sort_order' => 0,
    ]);
    $pinned->forceFill(['fixed_rate_per_gram' => 9000, 'rate_fixed_at' => now()])->save();

    MetalRate::create([
        'purity_id' => $this->purity->id,
        'effective_date' => today()->toDateString(),
        'rate' => 91600,
        'per_grams' => 10,
    ]);

    $order->lines()->create([
        'description' => 'Chain', 'net_weight' => 5, 'metal_type_id' => $this->gold->id,
        'purity_id' => $this->purity->id, 'lc_amount' => 10, 'lc_type' => 'percentage',
        'oc_amount' => 0, 'sort_order' => 1,
    ]);

    $lines = $this->actingAs($this->admin)
        ->getJson(route('item-estimates.from-order', $order))
        ->assertOk()
        ->json('lines');

    // Quoted per ten grams, so the pinned 9000 a gram arrives as 90000.
    expect($lines[0]['rate'])->toEqual(90000)
        ->and($lines[0]['labour_amount'])->toEqual(350)
        ->and($lines[0]['oc_amount'])->toEqual(100)
        // No pin on the second, so it falls back to the day's rate.
        ->and($lines[1]['rate'])->toEqual(91600)
        ->and($lines[1]['labour_type'])->toBe('percentage');
});

// --- the attached OG estimate -------------------------------------------------------------------

it('prints the attached OG estimate as a further page', function () {
    $og = new OgEstimate([
        'estimate_date' => today()->toDateString(),
        'customer_name' => 'Ravibhai Bhalodiya',
        'contact_no' => '9601263350',
        'sales_person_id' => $this->person->id,
        'sales_person_name' => $this->person->name,
        'direction' => 'in',
    ]);
    $og->ref_no = OgEstimate::nextRefNo();
    $og->save();
    $og->lines()->create([
        'description' => 'ring', 'gross_weight' => 10, 'net_weight' => 10,
        'touch_percent' => 91.6, 'rate' => 150000, 'sort_order' => 0,
    ]);

    postEstimate2($this, [estLine()], ['og_estimate_id' => $og->id])->assertRedirect();

    $estimate = ItemEstimate::firstOrFail();

    expect($estimate->og_estimate_id)->toBe($og->id);

    $response = $this->actingAs($this->admin)->post(route('item-estimates.print'), ['ids' => [$estimate->id]]);

    $response->assertOk();
    expect($response->getContent())->toStartWith('%PDF-');
});

it('prints cleanly after its attached OG estimate is deleted', function () {
    $og = new OgEstimate([
        'estimate_date' => today()->toDateString(),
        'customer_name' => 'Ravibhai',
        'sales_person_id' => $this->person->id,
        'direction' => 'in',
    ]);
    $og->ref_no = OgEstimate::nextRefNo();
    $og->save();

    postEstimate2($this, [estLine()], ['og_estimate_id' => $og->id])->assertRedirect();

    $og->delete();

    $estimate = ItemEstimate::firstOrFail();

    // Soft deleted, so the relation is empty and the extra page simply does not print.
    expect($estimate->fresh()->ogEstimate)->toBeNull();

    $this->actingAs($this->admin)->post(route('item-estimates.print'), ['ids' => [$estimate->id]])->assertOk();
});

// --- the document -----------------------------------------------------------------------------------

it('issues its own reference, clear of the other estimate counters', function () {
    AppSetting::current()->update(['og_estimate_next_ref_no' => 41, 'voucher_next_ref_no' => 42]);

    postEstimate2($this, [estLine()])->assertRedirect();

    expect(ItemEstimate::firstOrFail()->ref_no)->toBe(43)
        ->and((int) AppSetting::current()->item_estimate_next_ref_no)->toBe(44)
        ->and((int) AppSetting::current()->og_estimate_next_ref_no)->toBe(41)
        ->and((int) AppSetting::current()->voucher_next_ref_no)->toBe(42);
});

it('replaces the lines and their stones on edit', function () {
    postEstimate2($this, [estLine(['stones' => estStones($this)])])->assertRedirect();

    $estimate = ItemEstimate::firstOrFail();

    $this->actingAs($this->admin)->put(route('item-estimates.update', $estimate), [
        'estimate_date' => today()->toDateString(),
        'customer_name' => 'Ravibhai Bhalodiya',
        'contact_no' => '9601263350',
        'sales_person_id' => $this->person->id,
        'lines' => [estLine(['description' => 'Changed', 'stones' => []])],
    ])->assertRedirect();

    $estimate = $estimate->fresh();

    expect($estimate->lines)->toHaveCount(1)
        ->and($estimate->lines->first()->description)->toBe('Changed')
        ->and($estimate->lines->first()->stones)->toHaveCount(0);
});

// --- the screens ---------------------------------------------------------------------------------------

it('renders the listing, its payload and both screens', function () {
    postEstimate2($this, [estLine(['stones' => estStones($this)])], ['gst_enabled' => '1'])->assertRedirect();

    $this->actingAs($this->admin)->get(route('item-estimates.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('item-estimates.edit', ItemEstimate::firstOrFail()))
        ->assertOk()
        ->assertSee('SET');

    $response = $this->actingAs($this->admin)
        ->getJson(route('item-estimates.index', dtParams(['ref', 'customer', 'contact'])));

    $response->assertOk()->assertJsonPath('recordsTotal', 1);

    expect($response->json('data.0'))->toHaveKeys(['select', 'ref', 'net', 'total', 'og_ref', 'action'])
        ->and($response->json('data.0.net'))->toBe('104.320')
        ->and($response->json('data.0.total'))->toBe('1,811,100.00');
});

it('gives the tablet a number pad and a tap target for the stones', function () {
    $html = $this->actingAs($this->admin)->get(route('item-estimates.create'))->assertOk()->getContent();

    // Both are what make this screen usable on glass, and both are easy to lose in
    // a later edit — so they are pinned here.
    expect($html)->toContain('inputmode="decimal"')
        ->and($html)->toContain('est-stones-open')
        ->and($html)->toContain('modal-fullscreen-md-down');
});

// --- photos --------------------------------------------------------------------------------------------

it('leaves photos off the print unless asked', function () {
    postEstimate2($this, [estLine()])->assertRedirect();

    $estimate = ItemEstimate::firstOrFail();

    expect($estimate->show_photo)->toBeFalse();

    $this->actingAs($this->admin)->post(route('item-estimates.print'), ['ids' => [$estimate->id]])->assertOk();

    $this->actingAs($this->admin)->put(route('item-estimates.update', $estimate), [
        'estimate_date' => today()->toDateString(),
        'customer_name' => 'Ravibhai Bhalodiya',
        'sales_person_id' => $this->person->id,
        'show_photo' => '1',
        'lines' => [estLine()],
    ])->assertRedirect();

    expect($estimate->fresh()->show_photo)->toBeTrue();

    // Ticked but the line has no piece behind it: the document still prints.
    $this->actingAs($this->admin)->post(route('item-estimates.print'), ['ids' => [$estimate->id]])->assertOk();
});

it('prints one and several', function () {
    postEstimate2($this, [estLine()])->assertRedirect();
    postEstimate2($this, [estLine(['description' => 'Second'])])->assertRedirect();

    $ids = ItemEstimate::pluck('id')->all();

    foreach ([[$ids[0]], $ids] as $set) {
        $response = $this->actingAs($this->admin)->post(route('item-estimates.print'), ['ids' => $set]);

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        expect($response->getContent())->toStartWith('%PDF-');
    }
});

// --- permissions ------------------------------------------------------------------------------------------

it('lets a sales user write and print but not edit or delete', function () {
    postEstimate2($this, [estLine()])->assertRedirect();

    $estimate = ItemEstimate::firstOrFail();

    $this->actingAs($this->sales)->get(route('item-estimates.index'))->assertOk();
    $this->actingAs($this->sales)->get(route('item-estimates.create'))->assertOk();
    $this->actingAs($this->sales)->post(route('item-estimates.print'), ['ids' => [$estimate->id]])->assertOk();

    $this->actingAs($this->sales)->get(route('item-estimates.edit', $estimate))->assertForbidden();
    $this->actingAs($this->sales)->delete(route('item-estimates.destroy', $estimate))->assertForbidden();
});

// --- deduct from gross -----------------------------------------------------------------

it('leaves the gross alone for a stone marked not to deduct', function () {
    // The same two weighed rows as the sample, but the 18.760 g one is set not to
    // come out of the gross — exactly what unticking Ded. on the piece means.
    postEstimate2($this, [estLine([
        'stones' => [
            ['stone_master_id' => $this->byPiece->id, 'pieces' => 446,
                'weight_grams' => '19.840', 'rate' => '90', 'deduct_from_gross' => '1'],
            ['stone_master_id' => $this->byGram->id,
                'weight_grams' => '18.760', 'rate' => '900', 'deduct_from_gross' => '0'],
        ],
    ])])->assertRedirect();

    $line = ItemEstimateLine::with('stones')->firstOrFail();

    expect($line->stones->pluck('deduct_from_gross')->all())->toBe([true, false])
        // Only the first row's weight comes off, not both.
        ->and($line->stoneWeight())->toBe(19.84)
        ->and($line->netWeight())->toBe(round((float) $line->gross_weight - 19.84, 3))
        // The amount is unaffected: a stone still costs what it costs.
        ->and($line->jadtar())->toBe(round(446 * 90 + 18.760 * 900, 2));
});

it('deducts every stone when the rows do not say otherwise', function () {
    // Rows seeded by script carry no flag; the long-standing behaviour is to deduct.
    postEstimate2($this, [estLine(['stones' => estStones($this)])])->assertRedirect();

    $line = ItemEstimateLine::with('stones')->firstOrFail();

    expect($line->stones->every(fn ($s) => $s->deduct_from_gross))->toBeTrue()
        ->and($line->stoneWeight())->toBe(38.6);
});
