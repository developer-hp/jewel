<?php

use App\Models\AppSetting;
use App\Models\MetalType;
use App\Models\OrderForm;
use App\Models\Purity;
use App\Models\SalesPerson;
use App\Models\User;
use App\Models\Voucher;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->sales = User::factory()->create();
    $this->sales->assignRole('Sales');

    $this->person = SalesPerson::create(['name' => 'Zubin']);
    $this->gold = MetalType::where('code', 'GOLD')->firstOrFail();
    $this->purity = Purity::where('metal_type_id', $this->gold->id)->where('name', '22K')->firstOrFail();

    AppSetting::current()->update(['voucher_next_ref_no' => 42]);
});

function postVoucher($test, array $overrides = [])
{
    return $test->actingAs($test->admin)->post(route('vouchers.store'), array_merge([
        'voucher_date' => today()->toDateString(),
        'sales_person_id' => $test->person->id,
        'payment_mode' => 'cash',
        'order_reference' => 'out',
        'description' => 'BF 146',
        'amount' => '15000',
    ], $overrides));
}

/**
 * An order form, optionally with a line whose rate is already pinned.
 */
function voucherOrderFixture($test, bool $pinned = false): OrderForm
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

    $line = $form->lines()->create([
        'description' => 'Bangle',
        'net_weight' => 10,
        'metal_type_id' => $test->gold->id,
        'purity_id' => $test->purity->id,
        'lc_amount' => 0,
        'lc_type' => 'per_gram',
        'oc_amount' => 0,
        'sort_order' => 0,
    ]);

    if ($pinned) {
        $line->forceFill(['fixed_rate_per_gram' => 9160, 'rate_fixed_at' => now()])->save();
    }

    return $form->refresh();
}

// --- the document ----------------------------------------------------------------------

it('issues its own reference and snapshots the sales person', function () {
    postVoucher($this)->assertRedirect(route('vouchers.index'));

    $voucher = Voucher::firstOrFail();

    expect($voucher->ref_no)->toBe(42)
        ->and($voucher->reference())->toBe('42')
        ->and($voucher->sales_person_name)->toBe('Zubin')
        ->and((float) $voucher->amount)->toBe(15000.0)
        ->and($voucher->modeLabel())->toBe('Cash')
        ->and((int) AppSetting::current()->voucher_next_ref_no)->toBe(43);
});

it('needs an amount above nothing', function () {
    postVoucher($this, ['amount' => '0'])->assertSessionHasErrors('amount');

    expect(Voucher::count())->toBe(0);
});

it('copies onto a new reference', function () {
    postVoucher($this)->assertRedirect();

    $original = Voucher::firstOrFail();

    $this->actingAs($this->admin)->post(route('vouchers.copy', $original))->assertRedirect();

    $copy = Voucher::where('id', '!=', $original->id)->firstOrFail();

    expect($copy->ref_no)->toBe(43)
        ->and((float) $copy->amount)->toBe(15000.0)
        ->and($original->fresh()->ref_no)->toBe(42);
});

// --- the amount in words ------------------------------------------------------------------

it('spells the amount out', function (float $amount, string $words) {
    expect((new Voucher(['amount' => $amount]))->amountInWords())->toBe($words);
})->with([
    [15000, 'Fifteen Thousand Rupees Only'],
    // Indian numbering, so a lakh rather than a hundred thousand.
    [100000, 'One Lakh Rupees Only'],
    [125000.50, 'One Lakh Twenty-five Thousand Rupees and Fifty Paise Only'],
]);

// --- the rate block ------------------------------------------------------------------------

it('takes the rate block from the order it is against', function () {
    $order = voucherOrderFixture($this, pinned: true);

    postVoucher($this, ['order_reference' => 'order:'.$order->id])->assertRedirect();

    $block = Voucher::firstOrFail()->rateBlock();

    expect($block['fixed'])->toBeTrue();

    $rows = collect($block['rows'])->keyBy('label');

    expect($rows->get('22K')['rate'])->toBe(9160.0)
        // A purity the order never pinned stays blank to write on.
        ->and($rows->get('24K')['rate'])->toBeNull();
});

it('reads open when the order has pinned nothing', function () {
    $order = voucherOrderFixture($this);

    postVoucher($this, ['order_reference' => 'order:'.$order->id])->assertRedirect();

    $block = Voucher::firstOrFail()->rateBlock();

    expect($block['fixed'])->toBeFalse()
        ->and(collect($block['rows'])->pluck('rate')->filter())->toBeEmpty();
});

it('prints no rate block at all when it is not against an order', function () {
    postVoucher($this, ['order_reference' => 'in'])->assertRedirect();

    $voucher = Voucher::firstOrFail();

    expect($voucher->rateBlock())->toBeNull()
        ->and($voucher->direction)->toBe('in')
        ->and($voucher->orderReferenceLabel())->toBe('IN');
});

// --- the screens ------------------------------------------------------------------------------

it('renders the listing, its payload and both screens', function () {
    postVoucher($this)->assertRedirect();

    $this->actingAs($this->admin)->get(route('vouchers.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('vouchers.create'))->assertOk()->assertSee('Ref No');
    $this->actingAs($this->admin)->get(route('vouchers.edit', Voucher::firstOrFail()))
        ->assertOk()
        ->assertSee('BF 146');

    $response = $this->actingAs($this->admin)
        ->getJson(route('vouchers.index', dtParams(['ref', 'description'])));

    $response->assertOk()->assertJsonPath('recordsTotal', 1);

    expect($response->json('data.0'))->toHaveKeys(['select', 'ref', 'order_ref', 'amount', 'action'])
        ->and($response->json('data.0.amount'))->toBe('15,000.00');
});

it('prints one and several', function () {
    postVoucher($this)->assertRedirect();
    postVoucher($this, ['description' => 'Second'])->assertRedirect();

    $ids = Voucher::pluck('id')->all();

    foreach ([[$ids[0]], $ids] as $set) {
        $response = $this->actingAs($this->admin)->post(route('vouchers.print'), ['ids' => $set]);

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        expect($response->getContent())->toStartWith('%PDF-');
    }
});

// --- permissions ---------------------------------------------------------------------------------

it('lets a sales user write and print but not edit or delete', function () {
    postVoucher($this)->assertRedirect();

    $voucher = Voucher::firstOrFail();

    $this->actingAs($this->sales)->get(route('vouchers.index'))->assertOk();
    $this->actingAs($this->sales)->get(route('vouchers.create'))->assertOk();
    $this->actingAs($this->sales)->post(route('vouchers.print'), ['ids' => [$voucher->id]])->assertOk();

    $this->actingAs($this->sales)->get(route('vouchers.edit', $voucher))->assertForbidden();
    $this->actingAs($this->sales)->delete(route('vouchers.destroy', $voucher))->assertForbidden();
});
