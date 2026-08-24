<?php

use App\Models\MetalRate;
use App\Models\MetalType;
use App\Models\OrderForm;
use App\Models\Purity;
use App\Models\SalesPerson;
use App\Models\User;
use App\Models\Voucher;
use App\Services\OrderPricing;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->person = SalesPerson::create(['name' => 'Zubin']);
    $this->gold = MetalType::where('code', 'GOLD')->firstOrFail();
    $this->purity = Purity::where('metal_type_id', $this->gold->id)->where('name', '22K')->firstOrFail();
});

/**
 * An order with one line, priced however the test needs.
 */
function pricedOrder($test, array $line = [], bool $pinned = true): OrderForm
{
    $form = new OrderForm([
        'form_date' => today()->toDateString(),
        'delivery_date' => today()->addWeek()->toDateString(),
        'customer_name' => 'Chetan bhai dobariya',
        'contact_no' => '9825143759',
        'sales_person_id' => $test->person->id,
        'sales_person_name' => $test->person->name,
    ]);

    $form->ref_no = OrderForm::nextRefNo();
    $form->save();

    $row = $form->lines()->create(array_merge([
        'description' => 'Bangle',
        'net_weight' => 10,
        'metal_type_id' => $test->gold->id,
        'purity_id' => $test->purity->id,
        'lc_amount' => 0,
        'lc_type' => 'per_gram',
        'oc_amount' => 0,
        'sort_order' => 0,
    ], $line));

    if ($pinned) {
        $row->forceFill(['fixed_rate_per_gram' => 9000, 'rate_fixed_at' => now()])->save();
    }

    return $form->refresh();
}

function advanceOf($test, OrderForm $order, float $amount): Voucher
{
    $test->actingAs($test->admin)->post(route('vouchers.store'), [
        'voucher_date' => today()->toDateString(),
        'sales_person_id' => $test->person->id,
        'payment_mode' => 'cash',
        'order_reference' => 'order:'.$order->id,
        'description' => 'Advance',
        'amount' => (string) $amount,
    ])->assertRedirect();

    return Voucher::latest('id')->firstOrFail();
}

// --- pricing ------------------------------------------------------------------------

it('prices a line as metal plus labour plus other charges', function (string $type, float $lc, float $expected) {
    $order = pricedOrder($this, ['lc_type' => $type, 'lc_amount' => $lc, 'oc_amount' => 500]);

    // 10 g at 9000 the gram is 90000 of metal, plus labour, plus 500 of other charges.
    expect(app(OrderPricing::class)->value($order)->value)->toBe($expected);
})->with([
    // 10 g x 350
    ['per_gram', 350.0, 94000.0],
    // 12% of 90000
    ['percentage', 12.0, 101300.0],
    ['fixed', 2500.0, 93000.0],
]);

it('falls back to the rate of the day when a line is not pinned', function () {
    MetalRate::create([
        'purity_id' => $this->purity->id,
        'effective_date' => today()->toDateString(),
        'rate' => 91600,
        'per_grams' => 10,
    ]);

    $order = pricedOrder($this, [], pinned: false);

    // 10 g at 9160 the gram.
    expect(app(OrderPricing::class)->value($order)->value)->toBe(91600.0);
});

it('reports a line it cannot price rather than guessing', function () {
    // No pinned rate, and no rate entered for that purity today.
    $order = pricedOrder($this, [], pinned: false);

    $pricing = app(OrderPricing::class)->value($order);

    expect($pricing->unpriced)->toBe(1)
        ->and($pricing->priced)->toBe(0)
        ->and($pricing->value)->toBe(0.0)
        ->and($order->balance())->toBeNull();
});

// --- advances -----------------------------------------------------------------------

it('totals the advances taken against an order', function () {
    $order = pricedOrder($this);

    advanceOf($this, $order, 15000);
    advanceOf($this, $order, 5000);

    $order = $order->fresh();

    expect($order->vouchers)->toHaveCount(2)
        ->and($order->advancesPaid())->toBe(20000.0)
        ->and($order->pricing()->value)->toBe(90000.0)
        ->and($order->balance())->toBe(70000.0);
});

it('gives the balance back when an advance is deleted', function () {
    $order = pricedOrder($this);

    advanceOf($this, $order, 15000);
    $voucher = Voucher::latest('id')->firstOrFail();

    expect($order->fresh()->balance())->toBe(75000.0);

    $this->actingAs($this->admin)->delete(route('vouchers.destroy', $voucher))->assertRedirect();

    expect($order->fresh()->balance())->toBe(90000.0);
});

it('keeps advances against one order off another', function () {
    $first = pricedOrder($this);
    $second = pricedOrder($this);

    advanceOf($this, $first, 15000);

    expect($first->fresh()->advancesPaid())->toBe(15000.0)
        ->and($second->fresh()->advancesPaid())->toBe(0.0);
});

it('counts no advance for a voucher tied to a direction', function () {
    $order = pricedOrder($this);

    $this->actingAs($this->admin)->post(route('vouchers.store'), [
        'voucher_date' => today()->toDateString(),
        'sales_person_id' => $this->person->id,
        'payment_mode' => 'cash',
        'order_reference' => 'out',
        'description' => 'Sundry',
        'amount' => '2000',
    ])->assertRedirect();

    expect($order->fresh()->advancesPaid())->toBe(0.0);
});

it('leaves the voucher standing when its order is archived', function () {
    $order = pricedOrder($this);
    advanceOf($this, $order, 15000);

    $voucher = Voucher::latest('id')->firstOrFail();

    // Orders soft delete, so the row survives and the foreign key never fires — the
    // voucher keeps pointing at an order that is merely archived.
    $order->delete();

    $voucher = $voucher->fresh();

    expect($voucher->order_form_id)->toBe($order->id)
        ->and((float) $voucher->amount)->toBe(15000.0)
        // The relation excludes archived orders, so the label has to cope with the
        // gap rather than blow up on the listing.
        ->and($voucher->orderForm)->toBeNull()
        ->and($voucher->orderReferenceLabel())->toBe('—');
});

it('prints a voucher whose order has been archived', function () {
    $order = pricedOrder($this);
    advanceOf($this, $order, 15000);

    $order->delete();

    $voucher = Voucher::latest('id')->firstOrFail();

    // rateBlock() reads the order, so an archived one must not break the document.
    // The labels still print — with nothing pinned they are blanks to write on, which
    // is what the paper voucher does; dropping them would lose the block entirely.
    $block = $voucher->rateBlock();

    expect($block['fixed'])->toBeFalse()
        ->and($block['rows'])->not->toBeEmpty()
        ->and(collect($block['rows'])->pluck('label')->filter())->toHaveCount(count($block['rows']))
        ->and(collect($block['rows'])->pluck('rate')->filter())->toBeEmpty();

    $this->actingAs($this->admin)->post(route('vouchers.print'), ['ids' => [$voucher->id]])->assertOk();
});
