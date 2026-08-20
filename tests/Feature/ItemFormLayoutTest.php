<?php

use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\Purity;
use App\Models\StoneMaster;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->gold22 = Purity::whereRelation('metalType', 'code', 'GOLD')->where('name', '22K')->firstOrFail();
    $this->ring = ItemGroup::where('prefix', 'RNG')->firstOrFail();
});

function itemFormPayload(array $overrides = []): array
{
    return array_merge([
        'item_group_id' => test()->ring->id,
        'metal_type_id' => test()->gold22->metal_type_id,
        'purity_id' => test()->gold22->id,
        'name' => 'Layout Test',
        'gross_weight' => 12.5,
        'other_deduction' => 0,
        'is_active' => '1',
    ], $overrides);
}

it('puts the stone and diamond tables in their own popups', function () {
    $this->actingAs($this->admin)->get(route('items.create'))
        ->assertOk()
        ->assertSee('id="stone-modal"', false)
        ->assertSee('id="diamond-modal"', false)
        ->assertSee('data-bs-target="#stone-modal"', false)
        ->assertSee('data-bs-target="#diamond-modal"', false)
        ->assertSee('id="stone-trigger-summary"', false)
        ->assertSee('id="diamond-trigger-summary"', false);
});

it('drops required from the stone select so a blank row cannot block saving', function () {
    // A required control inside a closed modal is unfocusable, and the browser
    // refuses to submit the form at all — silently, with no error shown.
    $this->actingAs($this->admin)->get(route('items.create'))
        ->assertOk()
        ->assertDontSee('stone-master" required', false);
});

it('orders the page details, stones and diamonds, then extra charges', function () {
    $body = $this->actingAs($this->admin)->get(route('items.create'))->assertOk()->getContent();

    $positions = [
        'details' => strpos($body, '>Item Details<'),
        'triggers' => strpos($body, 'Stones &amp; Diamonds'),
        'stoneModal' => strpos($body, 'id="stone-modal"'),
        'diamondModal' => strpos($body, 'id="diamond-modal"'),
        'extraCharges' => strpos($body, '>Extra Charges<'),
        'submit' => strpos($body, 'Create Item'),
    ];

    expect(collect($positions)->every(fn ($p) => $p !== false))->toBeTrue();

    // Extra charges must come after both popups, and everything before the button.
    expect($positions['triggers'])->toBeGreaterThan($positions['details'])
        ->and($positions['stoneModal'])->toBeGreaterThan($positions['triggers'])
        ->and($positions['diamondModal'])->toBeGreaterThan($positions['stoneModal'])
        ->and($positions['extraCharges'])->toBeGreaterThan($positions['diamondModal'])
        ->and($positions['submit'])->toBeGreaterThan($positions['extraCharges']);
});

it('exposes the bare group name for the item-name autofill', function () {
    $body = $this->actingAs($this->admin)->get(route('items.create'))->assertOk()->getContent();

    foreach (ItemGroup::active()->pluck('name') as $name) {
        expect($body)->toContain('data-name="'.$name.'"');
    }

    // The visible option text carries the prefix, which must not reach the name.
    expect($body)->toContain('data-name="Ring"')
        ->and($body)->toContain('Ring (RNG)');
});

it('keeps the popups on the edit screen too', function () {
    $this->actingAs($this->admin)->post(route('items.store'), itemFormPayload());

    $this->actingAs($this->admin)->get(route('items.edit', Item::firstOrFail()))
        ->assertOk()
        ->assertSee('id="stone-modal"', false)
        ->assertSee('id="diamond-modal"', false)
        ->assertSee('>Extra Charges<', false);
});

it('pre-populates saved rows inside the popups', function () {
    $ruby = StoneMaster::where('name', 'Ruby')->firstOrFail();
    $diamond = StoneMaster::where('name', 'Round Brilliant SI')->firstOrFail();

    $this->actingAs($this->admin)->post(route('items.store'), itemFormPayload([
        'extra_charge_1_label' => 'Polish',
        'extra_charge_1' => 1200,
        'stones' => [
            ['stone_master_id' => $ruby->id, 'pieces' => 2, 'weight_carat' => 5, 'deduct_from_gross' => '1'],
            1000 => ['stone_master_id' => $diamond->id, 'pieces' => 1, 'weight_carat' => 2, 'deduct_from_gross' => '1'],
        ],
    ]));

    $body = $this->actingAs($this->admin)->get(route('items.edit', Item::firstOrFail()))
        ->assertOk()->getContent();

    $stoneBlock = substr($body, strpos($body, 'id="stone-modal"'), strpos($body, 'id="diamond-modal"') - strpos($body, 'id="stone-modal"'));
    $diamondBlock = substr($body, strpos($body, 'id="diamond-modal"'), strpos($body, '>Extra Charges<') - strpos($body, 'id="diamond-modal"'));

    expect($stoneBlock)->toContain('value="'.$ruby->id.'"')
        ->and($diamondBlock)->toContain('value="'.$diamond->id.'"')
        ->and($body)->toContain('value="Polish"');
});

it('still ignores a blank popup row on save', function () {
    $ruby = StoneMaster::where('name', 'Ruby')->firstOrFail();

    // The exact shape produced by opening the popup, clicking Add Row, then closing
    // it without picking anything.
    $this->actingAs($this->admin)->post(route('items.store'), itemFormPayload([
        'stones' => [
            ['stone_master_id' => $ruby->id, 'pieces' => 2, 'weight_carat' => 5, 'deduct_from_gross' => '1'],
            ['stone_master_id' => '', 'pieces' => 0, 'weight_carat' => 0, 'weight_grams' => 0],
        ],
    ]))->assertRedirect(route('items.index'));

    $item = Item::firstOrFail();

    expect($item->itemStones)->toHaveCount(1)
        ->and((float) $item->net_weight)->toBe(11.5);
});

it('saves stones, diamonds and extra charges together unchanged', function () {
    $ruby = StoneMaster::where('name', 'Ruby')->firstOrFail();
    $diamond = StoneMaster::where('name', 'Round Brilliant SI')->firstOrFail();

    $this->actingAs($this->admin)->post(route('items.store'), itemFormPayload([
        'extra_charge_1_label' => 'Polish',
        'extra_charge_1' => 1200,
        'extra_charge_2_label' => 'Cert',
        'extra_charge_2' => 500,
        'stones' => [
            ['stone_master_id' => $ruby->id, 'pieces' => 2, 'weight_carat' => 5, 'deduct_from_gross' => '1'],
            1000 => ['stone_master_id' => $diamond->id, 'pieces' => 1, 'weight_carat' => 2, 'deduct_from_gross' => '1'],
        ],
    ]))->assertRedirect(route('items.index'));

    $item = Item::with('itemStones')->firstOrFail();

    expect((float) $item->stone_weight_grams)->toBe(1.0)
        ->and((float) $item->diamond_weight_grams)->toBe(0.4)
        ->and((float) $item->net_weight)->toBe(11.1)
        ->and($item->extraChargeTotal())->toBe(1700.0)
        ->and($item->itemStones)->toHaveCount(2);
});
