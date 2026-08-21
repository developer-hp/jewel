<?php

use App\Models\AppSetting;
use App\Models\Item;
use App\Models\ItemGroup;
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

    $this->person = SalesPerson::create(['name' => 'Shilpa Soni']);
    $this->gold = MetalType::where('code', 'GOLD')->firstOrFail();
    $this->purity = Purity::where('metal_type_id', $this->gold->id)->where('name', '22K')->firstOrFail();

    AppSetting::current()->update(['order_next_ref_no' => 160, 'order_ref_prefix' => 'CF']);
});

/**
 * An order with one made-to-order line, optionally carrying stones.
 */
function orderAwaitingPiece($test, array $stones = []): OrderFormLine
{
    $form = new OrderForm([
        'form_date' => today()->toDateString(),
        'delivery_date' => today()->addWeeks(2)->toDateString(),
        'customer_name' => 'NIKHILBHAI PATEL',
        'contact_no' => '9925747799',
        'sales_person_id' => $test->person->id,
    ]);

    $form->ref_no = OrderForm::nextRefNo();
    $form->save();

    $line = $form->lines()->create([
        'made_to_order' => true,
        'description' => '18kt rosegold pendant',
        'size_pcs' => '16',
        'metal_type_id' => $test->gold->id,
        'purity_id' => $test->purity->id,
        'net_weight' => 6,
        'lc_amount' => 25,
        'lc_type' => 'percentage',
        'sort_order' => 0,
    ]);

    foreach ($stones as $i => $stone) {
        $line->stones()->create($stone + ['sort_order' => $i]);
    }

    return $line->refresh();
}

/**
 * Make the piece for a line.
 */
function makeOrderPiece($test, OrderFormLine $line, array $overrides = [])
{
    return $test->actingAs($test->admin)->post(route('order-items.store'), array_merge([
        'order_form_line_id' => $line->id,
        'metal_type_id' => $test->gold->id,
        'purity_id' => $test->purity->id,
        'name' => '18kt rosegold pendant',
        'gross_weight' => '6.000',
        'other_deduction' => '0',
    ], $overrides));
}

it('lists only orders with a piece still to be made', function () {
    $line = orderAwaitingPiece($this);

    $this->actingAs($this->admin)->get(route('order-items.create'))
        ->assertOk()
        ->assertSee('CF 160')
        ->assertSee('18kt rosegold pendant');

    makeOrderPiece($this, $line)->assertRedirect();

    $this->actingAs($this->admin)->get(route('order-items.create'))
        ->assertOk()
        ->assertSee('Nothing is waiting to be made');
});

it('leaves a stock-only order out of the picker', function () {
    // A line that is not made to order has nothing for this screen to build.
    $form = new OrderForm([
        'form_date' => today()->toDateString(),
        'delivery_date' => today()->addWeek()->toDateString(),
        'customer_name' => 'Walk in',
        'contact_no' => '900',
        'sales_person_id' => $this->person->id,
    ]);
    $form->ref_no = OrderForm::nextRefNo();
    $form->save();
    $form->lines()->create(['description' => 'From the case', 'made_to_order' => false, 'sort_order' => 0]);

    $this->actingAs($this->admin)->get(route('order-items.create'))
        ->assertOk()
        ->assertSee('Nothing is waiting to be made');
});

it('makes the piece under the reserved order group and holds it against the line', function () {
    $line = orderAwaitingPiece($this);

    makeOrderPiece($this, $line)->assertRedirect(route('order-forms.index'));

    $item = Item::firstOrFail();

    expect($item->code)->toBe('ORDER0001')
        ->and($item->item_group_id)->toBe(ItemGroup::system(ItemGroup::SYSTEM_ORDER)->id)
        ->and($item->order_form_line_id)->toBe($line->id)
        ->and($item->isReserved())->toBeTrue()
        ->and((float) $item->net_weight)->toBe(6.0)
        ->and($line->fresh()->isReady())->toBeTrue()
        ->and($line->orderForm->fresh()->isReady())->toBeTrue();
});

it('carries the stones that were ordered onto the piece', function () {
    $stone = StoneMaster::where('kind', 'stone')->firstOrFail();
    $diamond = StoneMaster::where('kind', 'diamond')->firstOrFail();

    $line = orderAwaitingPiece($this, [
        ['stone_master_id' => $stone->id, 'kind' => 'stone', 'pieces' => 4,
            'weight_carat' => 1.5, 'weight_grams' => 0.3, 'rate_unit' => 'carat',
            'rate' => 1200, 'amount' => 1800, 'deduct_from_gross' => true],
        ['stone_master_id' => $diamond->id, 'kind' => 'diamond', 'pieces' => 1,
            'weight_carat' => 0.5, 'weight_grams' => 0.1, 'rate_unit' => 'carat',
            'rate' => 65000, 'amount' => 32500, 'deduct_from_gross' => true],
    ]);

    // Gross is the ordered net plus what the stones deduct, so the net comes back out
    // at what the customer asked for.
    expect($line->grossFromStones())->toBe(6.4);

    makeOrderPiece($this, $line, [
        'gross_weight' => '6.400',
        'stones' => [
            ['stone_master_id' => $stone->id, 'pieces' => 4, 'weight_carat' => '1.500', 'deduct_from_gross' => '1'],
            ['stone_master_id' => $diamond->id, 'pieces' => 1, 'weight_carat' => '0.500', 'deduct_from_gross' => '1'],
        ],
    ])->assertRedirect();

    $item = Item::with('itemStones')->firstOrFail();

    expect($item->itemStones)->toHaveCount(2)
        ->and($item->itemStones->pluck('kind')->all())->toEqualCanonicalizing(['stone', 'diamond'])
        // ItemCalculator owns the derivation; the net lands on what was ordered.
        ->and((float) $item->net_weight)->toBe(6.0)
        ->and((float) $item->stone_weight_grams)->toBe(0.3)
        ->and((float) $item->diamond_weight_grams)->toBe(0.1);
});

it('will not let two pieces claim the same line', function () {
    $line = orderAwaitingPiece($this);

    makeOrderPiece($this, $line)->assertRedirect();
    makeOrderPiece($this, $line)->assertSessionHasErrors('order_form_line_id');

    expect(Item::count())->toBe(1);
});

it('will not make a piece against a stock line', function () {
    $form = new OrderForm([
        'form_date' => today()->toDateString(),
        'delivery_date' => today()->addWeek()->toDateString(),
        'customer_name' => 'Walk in',
        'contact_no' => '900',
        'sales_person_id' => $this->person->id,
    ]);
    $form->ref_no = OrderForm::nextRefNo();
    $form->save();

    $line = $form->lines()->create(['description' => 'From the case', 'made_to_order' => false, 'sort_order' => 0]);

    makeOrderPiece($this, $line)->assertSessionHasErrors('order_form_line_id');

    expect(Item::count())->toBe(0);
});

it('frees the line when the piece is deleted rather than orphaning it', function () {
    $line = orderAwaitingPiece($this);
    makeOrderPiece($this, $line)->assertRedirect();

    Item::firstOrFail()->forceDelete();

    expect($line->fresh()->isReady())->toBeFalse()
        ->and($line->orderForm->fresh()->isReady())->toBeFalse();
});

it('rejects deductions that swallow the whole piece', function () {
    $stone = StoneMaster::where('kind', 'stone')->firstOrFail();
    $line = orderAwaitingPiece($this);

    makeOrderPiece($this, $line, [
        'gross_weight' => '1.000',
        'stones' => [['stone_master_id' => $stone->id, 'weight_carat' => '10', 'deduct_from_gross' => '1']],
    ])->assertSessionHasErrors('gross_weight');

    expect(Item::count())->toBe(0);
});

it('goes back to the screen when adding another', function () {
    $line = orderAwaitingPiece($this);

    makeOrderPiece($this, $line, ['save_and_add_another' => '1'])
        ->assertRedirect(route('order-items.create'));
});
