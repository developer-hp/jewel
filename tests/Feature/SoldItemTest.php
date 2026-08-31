<?php

use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\MetalType;
use App\Models\OrderForm;
use App\Models\Purity;
use App\Models\SalesPerson;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;

/**
 * The sold-items screen: pieces that have left the shelf and need writing out of
 * stock, by either route in.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

/**
 * A piece in stock.
 *
 * Named distinctly: Pest's global helpers share one namespace across the whole
 * suite, and a redeclaration is a fatal, not a failure.
 */
function soldScreenItem(): Item
{
    $group = ItemGroup::where('prefix', 'RNG')->firstOrFail();
    $gold = MetalType::where('code', 'GOLD')->firstOrFail();
    $purity = Purity::where('metal_type_id', $gold->id)->firstOrFail();

    $item = new Item([
        'item_group_id' => $group->id,
        'metal_type_id' => $gold->id,
        'purity_id' => $purity->id,
        'name' => 'Solitaire Ring',
        'gross_weight' => 9,
        'other_deduction' => 0,
    ]);

    // NOT NULL, and issued by the group rather than invented.
    $item->code = $group->nextItemCode();
    $item->net_weight = 9;
    $item->save();

    return $item->fresh();
}

/**
 * An order form holding that piece, exactly as the order screen's reserve() does.
 */
function soldScreenHeldBy(Item $item): OrderForm
{
    $person = SalesPerson::firstOrCreate(['name' => 'Counter']);

    $form = new OrderForm([
        'form_date' => today(),
        'delivery_date' => today()->addWeek(),
        'customer_name' => 'Nilesh Shah',
        'contact_no' => '9824237636',
        'sales_person_id' => $person->id,
    ]);

    $form->ref_no = OrderForm::nextRefNo();
    $form->save();

    $line = $form->lines()->create([
        'description' => 'Ring from stock',
        'source_item_id' => $item->id,
        'sort_order' => 0,
    ]);

    $item->forceFill(['order_form_line_id' => $line->id])->save();

    return $form->fresh();
}

it('shows the screen', function () {
    $this->actingAs($this->admin)->get(route('sold-items.index'))->assertOk();
});

it('leaves a piece held by a live order off the screen', function () {
    $item = soldScreenItem();
    soldScreenHeldBy($item);

    $rows = $this->actingAs($this->admin)
        ->getJson(route('sold-items.index', dtParams(['code'])))
        ->assertOk()
        ->json('data');

    // That order still wants the piece — it has not left the shelf.
    expect($rows)->toBeEmpty();
});

// Order forms soft-delete but their lines do not, so a deleted order leaves its held
// pieces pointing at a line whose order is gone. Without this the piece is out of
// available stock and on no screen at all.
it('shows a piece stranded by a deleted order', function () {
    $item = soldScreenItem();
    $form = soldScreenHeldBy($item);

    $form->delete();

    $rows = $this->actingAs($this->admin)
        ->getJson(route('sold-items.index', dtParams(['code'])))
        ->assertOk()
        ->json('data');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['code'])->toContain($item->code)
        // The column names the order it came off, which is the only clue the counter
        // has about a stranded piece.
        ->and($rows[0]['settled'])->toContain('Order deleted')
        ->and($rows[0]['settled'])->toContain($form->reference());
});

it('marks a stranded piece sold and lets go of the dead hold', function () {
    $item = soldScreenItem();
    soldScreenHeldBy($item)->delete();

    $this->actingAs($this->admin)
        ->post(route('sold-items.sold', $item))
        ->assertRedirect();

    $item->refresh();

    expect($item->isSold())->toBeTrue()
        ->and($item->order_form_line_id)->toBeNull();
});

// "Back in stock" has to mean it. Clearing sold_at alone would leave the piece held
// against an order that no longer exists — invisible in stock, and straight back
// onto this screen.
it('puts a stranded piece properly back into stock', function () {
    $item = soldScreenItem();
    soldScreenHeldBy($item)->delete();

    $this->actingAs($this->admin)->post(route('sold-items.sold', $item))->assertRedirect();
    $this->actingAs($this->admin)->post(route('sold-items.available', $item))->assertRedirect();

    $item->refresh();

    expect($item->isSold())->toBeFalse()
        ->and($item->order_form_line_id)->toBeNull()
        ->and(Item::inStock()->whereKey($item->id)->exists())->toBeTrue();

    // And it is off the screen, because nothing is stranding it any more.
    expect($this->actingAs($this->admin)
        ->getJson(route('sold-items.index', dtParams(['code'])))
        ->json('data'))->toBeEmpty();
});

it('never lets go of a live hold', function () {
    $item = soldScreenItem();
    $form = soldScreenHeldBy($item);

    expect($item->fresh()->releaseStrandedHold())->toBeFalse()
        ->and($item->fresh()->order_form_line_id)->not->toBeNull();

    // Only once the order is actually gone.
    $form->delete();

    expect($item->fresh()->releaseStrandedHold())->toBeTrue();
});

it('refuses to sell a piece with no reason behind it', function () {
    $item = soldScreenItem();

    $this->actingAs($this->admin)
        ->post(route('sold-items.sold', $item))
        ->assertSessionHas('error');

    expect($item->fresh()->isSold())->toBeFalse();
});

it('keeps writing a piece out of stock behind item.edit', function () {
    $item = soldScreenItem();
    soldScreenHeldBy($item)->delete();

    $sales = User::factory()->create();
    $sales->assignRole('Sales');

    // Sales can look at the screen but not change what is on it.
    $this->actingAs($sales)->get(route('sold-items.index'))->assertOk();
    $this->actingAs($sales)->post(route('sold-items.sold', $item))->assertForbidden();
    $this->actingAs($sales)->post(route('sold-items.available', $item))->assertForbidden();
});
