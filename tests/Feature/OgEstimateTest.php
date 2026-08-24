<?php

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\OgEstimate;
use App\Models\OgEstimateLine;
use App\Models\OrderForm;
use App\Models\SalesPerson;
use App\Models\User;
use App\Services\EstimateLineMath;
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

    AppSetting::current()->update(['og_estimate_next_ref_no' => 41]);
});

/**
 * The payload one estimate line posts.
 */
function estimateLine(array $overrides = []): array
{
    return array_merge([
        'description' => 'ring',
        'gross_weight' => '10',
        'net_weight' => '10',
        'touch_percent' => '91.6',
        'rate' => '150000',
    ], $overrides);
}

/**
 * A saved order form, for the "against an order" case.
 */
function estimateOrderFixture($test): OrderForm
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

    return $form;
}

function postEstimate($test, array $lines, array $overrides = [])
{
    return $test->actingAs($test->admin)->post(route('og-estimates.store'), array_merge([
        'estimate_date' => today()->toDateString(),
        'customer_name' => 'Ravibhai Bhalodiya',
        'contact_no' => '9601263350',
        'address' => 'Ahmedabad',
        'sales_person_id' => $test->person->id,
        'order_reference' => 'in',
        'lines' => $lines,
    ], $overrides));
}

// --- the arithmetic -------------------------------------------------------------------

it('values a line the way the paper estimate does', function () {
    // The sample: 10 g net at 91.6%, 150000 the ten grams.
    $math = app(EstimateLineMath::class);

    expect($math->fineWeight(10, 91.6))->toBe(9.16)
        ->and($math->lineValue(10, 91.6, 150000))->toBe(137400.0);
});

it('totals gross, net, fine weight and value', function () {
    postEstimate($this, [
        estimateLine(),
        estimateLine(['description' => 'chain', 'gross_weight' => '5', 'net_weight' => '4', 'touch_percent' => '75']),
    ])->assertRedirect();

    $totals = OgEstimate::firstOrFail()->totals();

    expect($totals->gross)->toBe(15.0)
        ->and($totals->net)->toBe(14.0)
        // 9.160 + 3.000
        ->and($totals->fine)->toBe(12.16)
        // 137400 + 45000
        ->and($totals->value)->toBe(182400.0);
});

it('refuses a purity over a whole', function () {
    postEstimate($this, [estimateLine(['touch_percent' => '101'])])
        ->assertSessionHasErrors('lines.0.touch_percent');

    expect(OgEstimate::count())->toBe(0);
});

it('drops blank rows and needs at least one line', function () {
    postEstimate($this, [estimateLine(), ['description' => '', 'net_weight' => '']])->assertRedirect();

    expect(OgEstimate::firstOrFail()->lines)->toHaveCount(1);

    postEstimate($this, [['description' => '', 'net_weight' => '']])->assertSessionHasErrors('lines');
});

it('will not take a line with no weight', function () {
    postEstimate($this, [estimateLine(['net_weight' => '0'])])
        ->assertSessionHasErrors('lines.0.net_weight');
});

// --- the document ----------------------------------------------------------------------

it('issues its own reference and snapshots the sales person', function () {
    postEstimate($this, [estimateLine()])->assertRedirect(route('og-estimates.index'));

    $estimate = OgEstimate::firstOrFail();

    expect($estimate->ref_no)->toBe(41)
        ->and($estimate->reference())->toBe('41')
        ->and($estimate->sales_person_name)->toBe('Zubin')
        ->and((int) AppSetting::current()->og_estimate_next_ref_no)->toBe(42);

    // A rename in the master must not rewrite what has already printed.
    $this->person->update(['name' => 'Zubin B']);

    expect($estimate->refresh()->sales_person_name)->toBe('Zubin');
});

it('keeps its counter clear of the voucher counter', function () {
    AppSetting::current()->update(['voucher_next_ref_no' => 7]);

    postEstimate($this, [estimateLine()])->assertRedirect();

    expect(OgEstimate::firstOrFail()->ref_no)->toBe(41)
        ->and((int) AppSetting::current()->voucher_next_ref_no)->toBe(7);
});

it('adds the customer to the register on first contact', function () {
    postEstimate($this, [estimateLine()])->assertRedirect();

    $customer = Customer::firstOrFail();

    expect($customer->name)->toBe('Ravibhai Bhalodiya')
        ->and($customer->phone_key)->toBe('9601263350')
        ->and(OgEstimate::firstOrFail()->customer_id)->toBe($customer->id);
});

it('replaces the lines on edit', function () {
    postEstimate($this, [estimateLine()])->assertRedirect();

    $estimate = OgEstimate::firstOrFail();

    $this->actingAs($this->admin)->put(route('og-estimates.update', $estimate), [
        'estimate_date' => today()->toDateString(),
        'customer_name' => 'Ravibhai Bhalodiya',
        'contact_no' => '9601263350',
        'sales_person_id' => $this->person->id,
        'order_reference' => 'out',
        'lines' => [estimateLine(['description' => 'bangle', 'net_weight' => '20'])],
    ])->assertRedirect();

    $estimate = $estimate->fresh();

    expect($estimate->lines)->toHaveCount(1)
        ->and($estimate->lines->first()->description)->toBe('bangle')
        ->and($estimate->direction)->toBe('out');
});

it('copies onto a new reference and leaves the original alone', function () {
    postEstimate($this, [estimateLine(), estimateLine(['description' => 'chain'])])->assertRedirect();

    $original = OgEstimate::firstOrFail();

    $this->actingAs($this->admin)->post(route('og-estimates.copy', $original))->assertRedirect();

    $copy = OgEstimate::where('id', '!=', $original->id)->firstOrFail();

    expect($copy->ref_no)->toBe(42)
        ->and($copy->lines)->toHaveCount(2)
        ->and($copy->lines->pluck('description')->all())->toBe(['ring', 'chain'])
        ->and($original->fresh()->ref_no)->toBe(41)
        ->and($original->fresh()->lines)->toHaveCount(2);
});

it('deletes an estimate and its lines', function () {
    postEstimate($this, [estimateLine()])->assertRedirect();

    $estimate = OgEstimate::firstOrFail();

    $this->actingAs($this->admin)->delete(route('og-estimates.destroy', $estimate))->assertRedirect();

    expect(OgEstimate::whereKey($estimate->id)->exists())->toBeFalse()
        ->and(OgEstimateLine::where('og_estimate_id', $estimate->id)->count())->toBe(0);
});

// --- the order reference -----------------------------------------------------------------

it('stores a direction or an order, never both', function () {
    $order = estimateOrderFixture($this);

    postEstimate($this, [estimateLine()], ['order_reference' => 'order:'.$order->id])->assertRedirect();

    $estimate = OgEstimate::firstOrFail();

    expect($estimate->order_form_id)->toBe($order->id)
        ->and($estimate->direction)->toBeNull()
        ->and($estimate->orderReferenceLabel())->toBe($order->reference())
        ->and($estimate->orderReferenceValue())->toBe('order:'.$order->id);
});

it('rejects an order reference that is neither a direction nor an order', function () {
    postEstimate($this, [estimateLine()], ['order_reference' => 'rubbish'])
        ->assertSessionHasErrors('order_reference');

    postEstimate($this, [estimateLine()], ['order_reference' => ''])
        ->assertSessionHasErrors('order_reference');

    expect(OgEstimate::count())->toBe(0);
});

// --- the screens --------------------------------------------------------------------------

it('renders the listing, its payload and both screens', function () {
    postEstimate($this, [estimateLine()])->assertRedirect();

    $this->actingAs($this->admin)->get(route('og-estimates.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('og-estimates.create'))->assertOk()->assertSee('Ref No');
    $this->actingAs($this->admin)->get(route('og-estimates.edit', OgEstimate::firstOrFail()))
        ->assertOk()
        ->assertSee('ring');

    $response = $this->actingAs($this->admin)
        ->getJson(route('og-estimates.index', dtParams(['ref', 'customer', 'contact'])));

    $response->assertOk()->assertJsonPath('recordsTotal', 1);

    expect($response->json('data.0'))->toHaveKeys(['select', 'ref', 'customer', 'fine', 'value', 'action'])
        ->and($response->json('data.0.fine'))->toBe('9.160')
        ->and($response->json('data.0.value'))->toBe('137,400.00');
});

it('prints one and several', function () {
    postEstimate($this, [estimateLine()])->assertRedirect();
    postEstimate($this, [estimateLine(['description' => 'chain'])])->assertRedirect();

    $ids = OgEstimate::pluck('id')->all();

    foreach ([[$ids[0]], $ids] as $set) {
        $response = $this->actingAs($this->admin)->post(route('og-estimates.print'), ['ids' => $set]);

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        expect($response->getContent())->toStartWith('%PDF-');
    }
});

// --- permissions ----------------------------------------------------------------------------

it('lets a sales user write and print but not edit or delete', function () {
    postEstimate($this, [estimateLine()])->assertRedirect();

    $estimate = OgEstimate::firstOrFail();

    $this->actingAs($this->sales)->get(route('og-estimates.index'))->assertOk();
    $this->actingAs($this->sales)->get(route('og-estimates.create'))->assertOk();
    $this->actingAs($this->sales)->post(route('og-estimates.print'), ['ids' => [$estimate->id]])->assertOk();

    $this->actingAs($this->sales)->get(route('og-estimates.edit', $estimate))->assertForbidden();
    $this->actingAs($this->sales)->delete(route('og-estimates.destroy', $estimate))->assertForbidden();
});
