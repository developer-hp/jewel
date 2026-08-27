<?php

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\MakingCharge;
use App\Models\MetalRate;
use App\Models\MetalType;
use App\Models\OrderForm;
use App\Models\OrderFormLine;
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

    $this->person = SalesPerson::create(['name' => 'Shilpa Soni']);
    $this->gold = MetalType::where('code', 'GOLD')->firstOrFail();
    $this->purity = Purity::where('metal_type_id', $this->gold->id)->where('name', '22K')->firstOrFail();

    // Continue the shop's existing numbering, as the Appearance screen would.
    AppSetting::current()->update(['order_next_ref_no' => 160, 'order_ref_prefix' => 'CF']);
});

/**
 * A stock piece the order can promise or copy.
 */
function stockPiece(string $prefix = 'RNG', array $overrides = []): Item
{
    $group = ItemGroup::where('prefix', $prefix)->firstOrFail();
    $gold = MetalType::where('code', 'GOLD')->firstOrFail();
    $purity = Purity::where('metal_type_id', $gold->id)->where('name', '22K')->firstOrFail();

    $item = new Item(array_merge([
        'item_group_id' => $group->id,
        'metal_type_id' => $gold->id,
        'purity_id' => $purity->id,
        'name' => 'Ring',
        'gross_weight' => 5.5,
        'other_deduction' => 0,
        'is_active' => true,
    ], $overrides));

    $item->code = $group->nextItemCode();
    $item->net_weight = $item->gross_weight;
    $item->save();

    return $item;
}

/**
 * The payload one order line posts.
 */
function orderLine(array $overrides = []): array
{
    return array_merge([
        'description' => 'Ring',
        'net_weight' => '5.5',
        'lc_amount' => '350',
        'lc_type' => 'per_gram',
        'oc_amount' => '0',
        'size_pcs' => '14',
    ], $overrides);
}

/**
 * Post a whole order.
 */
function postOrder($test, array $lines, array $overrides = [])
{
    return $test->actingAs($test->admin)->post(route('order-forms.store'), array_merge([
        'form_date' => today()->toDateString(),
        'delivery_date' => today()->addWeeks(2)->toDateString(),
        'customer_name' => 'NIKHILBHAI PATEL',
        'contact_no' => '9925747799',
        'sales_person_id' => $test->person->id,
        'lines' => $lines,
    ], $overrides));
}

// --- the form ---------------------------------------------------------------------

it('issues the reference from the counter and increments it', function () {
    postOrder($this, [orderLine()])->assertRedirect(route('order-forms.index'));

    $form = OrderForm::firstOrFail();

    expect($form->ref_no)->toBe(160)
        ->and($form->reference())->toBe('CF 160')
        ->and($form->sales_person_name)->toBe('Shilpa Soni')
        ->and($form->lines)->toHaveCount(1)
        ->and($form->isReady())->toBeFalse()
        ->and((int) AppSetting::current()->order_next_ref_no)->toBe(161);
});

it('drops blank lines and requires at least one', function () {
    postOrder($this, [orderLine(), ['description' => '', 'net_weight' => '']])->assertRedirect();

    expect(OrderForm::firstOrFail()->lines)->toHaveCount(1);

    postOrder($this, [['description' => '', 'net_weight' => '']])->assertSessionHasErrors('lines');
});

it('records the stones asked for on a line', function () {
    $stone = StoneMaster::where('kind', 'stone')->firstOrFail();

    postOrder($this, [orderLine([
        'stones' => [[
            'stone_master_id' => $stone->id,
            'pieces' => 4,
            'weight_carat' => '1.500',
            'deduct_from_gross' => '1',
        ]],
    ])])->assertRedirect();

    $line = OrderFormLine::firstOrFail();

    expect($line->stones)->toHaveCount(1)
        // The one calculator derives grams from carat, here as for an item.
        ->and((float) $line->stones->first()->weight_grams)->toBe(0.3)
        ->and($line->stones->first()->kind)->toBe('stone')
        ->and($line->grossFromStones())->toBe(5.8);
});

it('rounds the labour figure to how it should be read', function (string $type, string $amount, string $printed) {
    postOrder($this, [orderLine(['lc_type' => $type, 'lc_amount' => $amount])])->assertRedirect();

    expect(OrderFormLine::firstOrFail()->labourLabel())->toBe($printed);
})->with([
    ['per_gram', '350', '350/gm'],
    ['percentage', '25', '25%'],
    ['fixed', '1500', '1,500'],
]);

it('copies labour and the extra charges off the chosen piece', function () {
    $charge = MakingCharge::create([
        'code' => 'MC-ORD12', 'name' => '12% of metal', 'charge_type' => 'percentage', 'rate' => 12,
    ]);

    $piece = stockPiece('RNG', [
        'making_charge_id' => $charge->id,
        'extra_charge_1' => 150,
        'extra_charge_2' => 75,
    ]);

    $found = collect($this->actingAs($this->admin)->getJson(route('items.lookup'))->json('items'))
        ->firstWhere('code', $piece->code);

    // The picker hands the form what it needs to fill the line in one go.
    expect($found['making_charge'])->toEqual(['charge_type' => 'percentage', 'rate' => 12.0])
        ->and($found['extra_charge_1'])->toEqual(150)
        ->and($found['extra_charge_2'])->toEqual(75);
});

it('totals the stones on a line so the form can suggest other charges', function () {
    $stone = StoneMaster::where('kind', 'stone')->where('rate_unit', 'carat')->firstOrFail();

    postOrder($this, [orderLine([
        'oc_amount' => '2225',
        'stones' => [[
            'stone_master_id' => $stone->id,
            'weight_carat' => '2.000',
            'rate' => '1000',
            'deduct_from_gross' => '1',
        ]],
    ])])->assertRedirect();

    $line = OrderFormLine::firstOrFail();

    // 2 ct at 1000 the carat. The form adds the piece's extra charges to this and
    // drops the total in the box; what is stored is what was left there.
    expect($line->stoneCharge())->toBe(2000.0)
        ->and((float) $line->oc_amount)->toBe(2225.0);
});

it('prices a per-piece stone by its pieces, not its weight', function () {
    $stone = StoneMaster::where('kind', 'stone')->where('rate_unit', 'piece')->firstOrFail();

    postOrder($this, [orderLine([
        'stones' => [[
            'stone_master_id' => $stone->id,
            'pieces' => 3,
            'weight_carat' => '2.000',
            'rate' => '15',
            'deduct_from_gross' => '1',
        ]],
    ])])->assertRedirect();

    // 3 pieces at 15, and the carat figure only feeds the weight deduction.
    expect(OrderFormLine::firstOrFail()->stoneCharge())->toBe(45.0);
});

it('keeps the other charges figure exactly as it was typed', function () {
    $stone = StoneMaster::where('kind', 'stone')->where('rate_unit', 'carat')->firstOrFail();

    // Deliberately not what the parts come to: the counter has the last word.
    postOrder($this, [orderLine([
        'oc_amount' => '999',
        'stones' => [[
            'stone_master_id' => $stone->id,
            'weight_carat' => '1.000',
            'rate' => '500',
            'deduct_from_gross' => '1',
        ]],
    ])])->assertRedirect();

    $line = OrderFormLine::firstOrFail();

    expect((float) $line->oc_amount)->toBe(999.0)
        ->and($line->stoneCharge())->toBe(500.0);
});

it('renders the add and edit screens, held lines and all', function () {
    $piece = stockPiece();

    $this->actingAs($this->admin)->get(route('order-forms.create'))
        ->assertOk()
        ->assertSee('Ref No')
        ->assertSee('Fix Rate');

    postOrder($this, [
        orderLine(['source_item_id' => $piece->id, 'reserve' => '1']),
        orderLine(['description' => 'To be made', 'made_to_order' => '1']),
    ])->assertRedirect();

    $this->actingAs($this->admin)->get(route('order-forms.edit', OrderForm::firstOrFail()))
        ->assertOk()
        ->assertSee($piece->code)
        ->assertSee('To be made')
        // The rate card only appears once something is held against a line.
        ->assertSee('id="rate-card"', false);
});

// --- reserving a stock piece --------------------------------------------------------

it('holds a stock piece against the order when the line is ticked', function () {
    $piece = stockPiece();

    postOrder($this, [orderLine(['source_item_id' => $piece->id, 'reserve' => '1'])])->assertRedirect();

    $line = OrderFormLine::firstOrFail();

    expect($piece->refresh()->order_form_line_id)->toBe($line->id)
        ->and($piece->isReserved())->toBeTrue()
        ->and($line->fresh()->isReady())->toBeTrue()
        ->and(OrderForm::firstOrFail()->isReady())->toBeTrue();
});

it('will not promise the same piece to two customers', function () {
    $piece = stockPiece();

    postOrder($this, [orderLine(['source_item_id' => $piece->id, 'reserve' => '1'])])->assertRedirect();

    postOrder($this, [orderLine(['source_item_id' => $piece->id, 'reserve' => '1'])])
        ->assertSessionHasErrors('lines');

    // The first order keeps it, and no second order was written.
    expect($piece->refresh()->order_form_line_id)->toBe(OrderFormLine::firstOrFail()->id);
});

it('shows the order a piece is held against on the items list', function () {
    $held = stockPiece();
    $free = stockPiece();

    postOrder($this, [orderLine(['source_item_id' => $held->id, 'reserve' => '1'])])->assertRedirect();

    $columns = ['code', 'name', 'group', 'supplier', 'order_no'];

    $rows = collect($this->actingAs($this->admin)
        ->getJson(route('items.index', dtParams($columns)))->json('data'))
        ->keyBy(fn ($row) => strip_tags($row['code']));

    expect($rows[$held->code]['order_no'])->toContain('CF 160')
        ->and($rows[$held->code]['order_no'])->toContain(route('order-forms.edit', OrderForm::firstOrFail()))
        // A piece nobody has spoken for shows nothing.
        ->and(strip_tags($rows[$free->code]['order_no']))->toBe('—');
});

it('finds a held piece by its order number', function () {
    $held = stockPiece();
    stockPiece();

    postOrder($this, [orderLine(['source_item_id' => $held->id, 'reserve' => '1'])])->assertRedirect();

    $columns = ['code', 'name', 'group', 'supplier', 'order_no'];

    // Typed with or without the prefix, it finds the one piece.
    foreach (['CF 160', '160'] as $term) {
        $response = $this->actingAs($this->admin)->getJson(route('items.index', dtParams(
            $columns,
            ['search' => ['value' => $term]],
        )));

        expect($response->json('recordsFiltered'))->toBe(1, "search term: {$term}")
            ->and(strip_tags($response->json('data.0.code')))->toBe($held->code);
    }
});

it('keeps a piece out of the stock picker once it is held', function () {
    $piece = stockPiece();

    $available = fn () => collect($this->actingAs($this->admin)
        ->getJson(route('items.lookup'))->json('items'))->pluck('code');

    expect($available())->toContain($piece->code);

    postOrder($this, [orderLine(['source_item_id' => $piece->id, 'reserve' => '1'])])->assertRedirect();

    expect($available())->not->toContain($piece->code);
});

it('leaves the piece free when the line is saved without the tick', function () {
    $piece = stockPiece();

    postOrder($this, [orderLine(['source_item_id' => $piece->id])])->assertRedirect();

    expect($piece->refresh()->order_form_line_id)->toBeNull()
        ->and(OrderForm::firstOrFail()->isReady())->toBeFalse();
});

it('locks a held line against edits but still lets the others move', function () {
    $piece = stockPiece();

    postOrder($this, [
        orderLine(['source_item_id' => $piece->id, 'reserve' => '1']),
        orderLine(['description' => 'Second', 'made_to_order' => '1']),
    ])->assertRedirect();

    $form = OrderForm::with('lines')->firstOrFail();
    [$held, $open] = $form->lines->all();

    $this->actingAs($this->admin)->put(route('order-forms.update', $form), [
        'form_date' => $form->form_date->toDateString(),
        'delivery_date' => $form->delivery_date->toDateString(),
        'customer_name' => 'NIKHILBHAI PATEL',
        'contact_no' => '9925747799',
        'sales_person_id' => $this->person->id,
        'lines' => [
            orderLine(['id' => $held->id, 'description' => 'CHANGED', 'net_weight' => '99']),
            orderLine(['id' => $open->id, 'description' => 'Second changed', 'made_to_order' => '1']),
        ],
    ])->assertRedirect();

    expect($held->fresh()->description)->toBe('Ring')
        ->and((float) $held->fresh()->net_weight)->toBe(5.5)
        ->and($open->fresh()->description)->toBe('Second changed');
});

// --- the pinned rate ----------------------------------------------------------------

it('pins the day rate on a held line and holds it against a later change', function () {
    MetalRate::create([
        'purity_id' => $this->purity->id,
        'effective_date' => today()->toDateString(),
        'rate' => 91600,
        'per_grams' => 10,
    ]);

    $piece = stockPiece();

    postOrder($this, [orderLine([
        'source_item_id' => $piece->id,
        'reserve' => '1',
        'purity_id' => $this->purity->id,
        'metal_type_id' => $this->gold->id,
    ])])->assertRedirect();

    $line = OrderFormLine::firstOrFail();

    expect($line->isRateFixed())->toBeFalse()
        ->and($line->rateLabel())->toBe('Open');

    $this->actingAs($this->admin)->post(route('order-forms.fix-rate', $line))->assertRedirect();

    $line = $line->fresh();

    expect($line->isRateFixed())->toBeTrue()
        ->and((float) $line->fixed_rate_per_gram)->toBe(9160.0)
        ->and($line->rate_fixed_at)->not->toBeNull();

    // Tomorrow's rate must not move what was already pinned.
    MetalRate::create([
        'purity_id' => $this->purity->id,
        'effective_date' => today()->addDay()->toDateString(),
        'rate' => 99000,
        'per_grams' => 10,
    ]);

    expect((float) $line->fresh()->fixed_rate_per_gram)->toBe(9160.0);

    $this->actingAs($this->admin)->post(route('order-forms.fix-rate', $line), ['release' => '1'])->assertRedirect();

    expect($line->fresh()->isRateFixed())->toBeFalse();
});

it('pins the rate straight from the order form, without a second visit', function () {
    MetalRate::create([
        'purity_id' => $this->purity->id,
        'effective_date' => today()->toDateString(),
        'rate' => 91600,
        'per_grams' => 10,
    ]);

    // A piece still to be made can have its rate agreed on the day all the same —
    // that is the whole point of fixing it.
    postOrder($this, [orderLine([
        'made_to_order' => '1',
        'purity_id' => $this->purity->id,
        'metal_type_id' => $this->gold->id,
        'fix_rate' => '1',
    ])])->assertRedirect()->assertSessionMissing('error');

    $line = OrderFormLine::firstOrFail();

    expect($line->isReady())->toBeFalse()
        ->and($line->isRateFixed())->toBeTrue()
        ->and((float) $line->fixed_rate_per_gram)->toBe(9160.0);
});

it('releases the pin when the tick is cleared on a later save', function () {
    MetalRate::create([
        'purity_id' => $this->purity->id,
        'effective_date' => today()->toDateString(),
        'rate' => 91600,
        'per_grams' => 10,
    ]);

    postOrder($this, [orderLine([
        'made_to_order' => '1',
        'purity_id' => $this->purity->id,
        'metal_type_id' => $this->gold->id,
        'fix_rate' => '1',
    ])])->assertRedirect();

    $form = OrderForm::firstOrFail();
    $line = $form->lines->first();

    expect($line->isRateFixed())->toBeTrue();

    $this->actingAs($this->admin)->put(route('order-forms.update', $form), [
        'form_date' => $form->form_date->toDateString(),
        'delivery_date' => $form->delivery_date->toDateString(),
        'customer_name' => 'NIKHILBHAI PATEL',
        'contact_no' => '9925747799',
        'sales_person_id' => $this->person->id,
        'lines' => [orderLine([
            'id' => $line->id,
            'made_to_order' => '1',
            'purity_id' => $this->purity->id,
            'metal_type_id' => $this->gold->id,
        ])],
    ])->assertRedirect();

    expect($line->fresh()->isRateFixed())->toBeFalse();
});

it('saves the order but says so when that purity has no rate today', function () {
    // No MetalRate for the purity: the order still matters more than the rate.
    postOrder($this, [orderLine([
        'description' => 'Pendant',
        'made_to_order' => '1',
        'purity_id' => $this->purity->id,
        'metal_type_id' => $this->gold->id,
        'fix_rate' => '1',
    ])])->assertRedirect()->assertSessionHas('error');

    $line = OrderFormLine::firstOrFail();

    expect(OrderForm::count())->toBe(1)
        ->and($line->isRateFixed())->toBeFalse();
});

it('pins a held line rate even though the rest of the line is locked', function () {
    MetalRate::create([
        'purity_id' => $this->purity->id,
        'effective_date' => today()->toDateString(),
        'rate' => 91600,
        'per_grams' => 10,
    ]);

    $piece = stockPiece();

    postOrder($this, [orderLine([
        'source_item_id' => $piece->id,
        'reserve' => '1',
        'purity_id' => $this->purity->id,
        'metal_type_id' => $this->gold->id,
    ])])->assertRedirect();

    $form = OrderForm::firstOrFail();
    $line = $form->lines->first();

    expect($line->isReady())->toBeTrue()
        ->and($line->isRateFixed())->toBeFalse();

    $this->actingAs($this->admin)->put(route('order-forms.update', $form), [
        'form_date' => $form->form_date->toDateString(),
        'delivery_date' => $form->delivery_date->toDateString(),
        'customer_name' => 'NIKHILBHAI PATEL',
        'contact_no' => '9925747799',
        'sales_person_id' => $this->person->id,
        'lines' => [orderLine(['id' => $line->id, 'fix_rate' => '1'])],
    ])->assertRedirect();

    expect($line->fresh()->isRateFixed())->toBeTrue();
});

// --- the listing ---------------------------------------------------------------------

it('lists orders with their progress, status and the customer other orders', function () {
    $piece = stockPiece();

    postOrder($this, [orderLine(['source_item_id' => $piece->id, 'reserve' => '1'])])->assertRedirect();
    postOrder($this, [orderLine(['made_to_order' => '1']), orderLine(['made_to_order' => '1'])])->assertRedirect();

    $columns = ['ref', 'customer', 'contact'];

    $all = $this->actingAs($this->admin)->getJson(route('order-forms.index', dtParams($columns)));
    $all->assertOk();

    expect($all->json('recordsTotal'))->toBe(2)
        ->and($all->json('data.0'))->toHaveKeys(['select', 'ref', 'other_orders', 'progress', 'status', 'action']);

    $ready = $this->actingAs($this->admin)
        ->getJson(route('order-forms.index', dtParams($columns) + ['status' => 'ready']));
    $pending = $this->actingAs($this->admin)
        ->getJson(route('order-forms.index', dtParams($columns) + ['status' => 'pending']));

    expect($ready->json('recordsTotal'))->toBe(1)
        ->and($ready->json('data.0.progress'))->toBe('1 / 1')
        ->and($ready->json('data.0.DT_RowClass'))->toBe('row-ready')
        ->and($pending->json('recordsTotal'))->toBe(1)
        ->and($pending->json('data.0.progress'))->toBe('0 / 2')
        ->and($pending->json('data.0.DT_RowClass'))->toBe('row-pending')
        // Both orders are the same number, so each names the other and not itself.
        ->and($ready->json('data.0.other_orders'))->toBe('CF 161')
        ->and($pending->json('data.0.other_orders'))->toBe('CF 160');
});

it('adds the customer on first contact and links the order', function () {
    postOrder($this, [orderLine()], ['address' => 'Unjha'])->assertRedirect();

    $customer = Customer::firstOrFail();

    expect($customer->phone_key)->toBe('9925747799')
        ->and($customer->address)->toBe('Unjha')
        ->and(OrderForm::firstOrFail()->customer_id)->toBe($customer->id);
});

it('will not delete an order whose pieces are held', function () {
    $piece = stockPiece();
    postOrder($this, [orderLine(['source_item_id' => $piece->id, 'reserve' => '1'])])->assertRedirect();

    $form = OrderForm::firstOrFail();

    $this->actingAs($this->admin)->delete(route('order-forms.destroy', $form))->assertSessionHas('error');

    expect(OrderForm::whereKey($form->id)->exists())->toBeTrue();
});

// --- printing ---------------------------------------------------------------------------

it('prints the order and the sticker, one and several', function () {
    AppSetting::current()->update([
        'order_contact_no' => '9712406367',
        'firm_website' => 'http://krsonsahd.com',
        'order_terms' => "ORDER ONCE PLACED CANNOT BE CANCELLED\nMINIMUM OF 10% WEIGHT VARIATION IS TO BE EXPECTED",
    ]);

    postOrder($this, [orderLine()])->assertRedirect();
    postOrder($this, [orderLine(['description' => 'Second'])])->assertRedirect();

    $ids = OrderForm::pluck('id')->all();

    foreach ([route('order-forms.print'), route('order-forms.stickers')] as $url) {
        foreach ([[$ids[0]], $ids] as $set) {
            $response = $this->actingAs($this->admin)->post($url, ['ids' => $set]);

            $response->assertOk()->assertHeader('content-type', 'application/pdf');
            expect($response->getContent())->toStartWith('%PDF-');
        }
    }
});

it('prints a sticker from a reference typed however it is written', function () {
    postOrder($this, [orderLine()])->assertRedirect();

    // The screen itself is now a picker, but a typed reference still resolves — the
    // counter often has the paper in hand rather than the row on screen.
    $this->actingAs($this->admin)->get(route('order-forms.sticker-by-ref'))
        ->assertOk()
        ->assertSee('id="order-picker"', false);

    foreach (['CF 160', 'cf160', '160'] as $typed) {
        $response = $this->actingAs($this->admin)
            ->get(route('order-forms.sticker-by-ref', ['ref_no' => $typed]));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    $this->actingAs($this->admin)->get(route('order-forms.sticker-by-ref', ['ref_no' => '9999']))
        ->assertRedirect();
});

it('prints stickers for several picked orders at once', function () {
    postOrder($this, [orderLine()])->assertRedirect();
    postOrder($this, [orderLine()])->assertRedirect();

    $ids = OrderForm::orderBy('ref_no')->pluck('id')->all();

    expect($ids)->toHaveCount(2);

    $response = $this->actingAs($this->admin)
        ->get(route('order-forms.sticker-by-ref', ['ids' => $ids]));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF-');
});

it('refuses an order id that does not exist', function () {
    $this->actingAs($this->admin)
        ->get(route('order-forms.sticker-by-ref', ['ids' => [9999]]))
        ->assertSessionHasErrors('ids.0');
});

it('finds orders for the picker by reference and by customer', function () {
    postOrder($this, [orderLine()])->assertRedirect();

    $form = OrderForm::firstOrFail();

    // With the prefix, without it, and by the customer's name — all the same order.
    foreach (['CF 160', '160', $form->customer_name] as $term) {
        $results = $this->actingAs($this->admin)
            ->getJson(route('order-forms.sticker-search', ['q' => $term]))
            ->assertOk()
            ->json('results');

        expect(collect($results)->pluck('id'))->toContain($form->id);
    }

    // The label carries the reference, the customer and the delivery date, because a
    // bare number is not enough to pick the right bag from a list.
    $first = $this->actingAs($this->admin)
        ->getJson(route('order-forms.sticker-search'))
        ->assertOk()
        ->json('results.0');

    expect($first['text'])->toContain('CF 160')->toContain($form->customer_name);
});

it('keeps the picker lookup behind the print permission', function () {
    $this->actingAs(User::factory()->create())
        ->getJson(route('order-forms.sticker-search'))
        ->assertForbidden();
});

// --- permissions -------------------------------------------------------------------------

it('lets a sales user take orders and print but not edit or delete', function () {
    postOrder($this, [orderLine()])->assertRedirect();
    $form = OrderForm::firstOrFail();

    $this->actingAs($this->sales)->get(route('order-forms.index'))->assertOk();
    $this->actingAs($this->sales)->get(route('order-forms.create'))->assertOk();
    $this->actingAs($this->sales)->post(route('order-forms.print'), ['ids' => [$form->id]])->assertOk();

    $this->actingAs($this->sales)->get(route('order-forms.edit', $form))->assertForbidden();
    $this->actingAs($this->sales)->delete(route('order-forms.destroy', $form))->assertForbidden();
    $this->actingAs($this->sales)->get(route('order-items.create'))->assertForbidden();
});

it('hides the module from a user with no permissions', function () {
    $none = User::factory()->create();

    $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->assertSee(route('order-forms.index'));
    $this->actingAs($none)->get(route('dashboard'))->assertOk()->assertDontSee(route('order-forms.index'));
    $this->actingAs($none)->get(route('order-forms.index'))->assertForbidden();
});

// --- the office copy ---------------------------------------------------------------

/** Highest /Count in the PDF's page tree. */
function orderPdfPages(string $pdf): int
{
    preg_match_all('#/Count\s+(\d+)#', $pdf, $m);

    return $m[1] ? max(array_map('intval', $m[1])) : 0;
}

/** The order print rendered as HTML, so its figures can be read back. */
function orderPrintHtml(): string
{
    return view('order-forms.print', [
        'forms' => OrderForm::with([
            'lines.item.purity', 'lines.item.metalType',
            'lines.sourceItem.purity', 'lines.sourceItem.metalType',
            'lines.sourceItem.makingCharge', 'lines.sourceItem.itemStones.stoneMaster',
        ])->get(),
        'firm' => ['query_phone' => '9712406367', 'website' => ''],
        'terms' => [],
    ])->render();
}

it('prints a customer copy and an office copy for every order', function () {
    postOrder($this, [orderLine()])->assertRedirect();
    postOrder($this, [orderLine(['description' => 'Second'])])->assertRedirect();

    $ids = OrderForm::pluck('id')->all();

    $one = $this->actingAs($this->admin)
        ->post(route('order-forms.print'), ['ids' => [$ids[0]]])->getContent();

    $both = $this->actingAs($this->admin)
        ->post(route('order-forms.print'), ['ids' => $ids])->getContent();

    // Two pages per order, not one: ten line rows, the terms and the signatures
    // already fill a page, so the copies cannot share one.
    expect(orderPdfPages($one))->toBe(2)
        ->and(orderPdfPages($both))->toBe(4);
});

it('keeps the delivery instruction off the copy that never leaves the shop', function () {
    postOrder($this, [orderLine()])->assertRedirect();

    $html = orderPrintHtml();

    expect($html)->toContain('OFFICE COPY')
        // Two pages, one instruction: it belongs only to the customer's copy.
        ->and(substr_count($html, 'PLEASE BRING THIS ORDER FORM'))->toBe(1);
});

it('prints the whole piece on the office copy when a line has one', function () {
    $piece = stockPiece('RNG', ['name' => 'Solitaire ring']);

    postOrder($this, [orderLine(['source_item_id' => $piece->id])])->assertRedirect();

    $html = orderPrintHtml();

    // Enough to find and check the piece without opening the system — and only
    // once, because the customer's copy does not carry it.
    expect(substr_count($html, $piece->code))->toBe(1)
        ->and($html)->toContain('Gross Wt')
        ->and($html)->toContain('Net Wt')
        ->and($html)->toContain('22K')
        // The office copy is a block per line, not the ten-row form.
        ->and($html)->toContain('linecard');
});

it('still prints an order whose lines have no piece behind them', function () {
    postOrder($this, [orderLine()])->assertRedirect();

    $response = $this->actingAs($this->admin)
        ->post(route('order-forms.print'), ['ids' => OrderForm::pluck('id')->all()]);

    $response->assertOk();

    // The block is still drawn; it simply carries no piece to describe.
    expect($response->getContent())->toStartWith('%PDF-')
        ->and(orderPrintHtml())->not->toContain('Gross Wt');
});

it('renders the print without provoking a php warning', function () {
    // The suite alone does not cover this: PHPUnit installs its own error handler
    // and does not fail on PHP warnings, so an order print that raised one still
    // passed assertOk() here while returning a 500 in the browser — Laravel turns
    // that warning into an ErrorException. Nesting a <table> inside the lines table
    // did exactly that, in Mpdf\Tag\Table. This asserts the render stays quiet.
    $piece = stockPiece('RNG', ['name' => 'Solitaire ring']);

    postOrder($this, [orderLine(['source_item_id' => $piece->id])])->assertRedirect();
    postOrder($this, [orderLine(['description' => 'Second'])])->assertRedirect();

    $raised = [];

    set_error_handler(function (int $level, string $message) use (&$raised) {
        // Deprecations are logged rather than thrown by Laravel, and the PDF
        // package raises one of its own on PHP 8.4.
        if (! in_array($level, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
            $raised[] = $message;
        }

        return true;
    });

    try {
        $response = $this->actingAs($this->admin)
            ->post(route('order-forms.print'), ['ids' => OrderForm::pluck('id')->all()]);
    } finally {
        restore_error_handler();
    }

    expect($raised)->toBe([]);

    $response->assertOk();
});
