<?php

use App\Models\ItemGroup;
use App\Models\LabelSetting;
use App\Models\MakingCharge;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\StoneMaster;
use App\Models\User;
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
});

it('seeds the master data', function () {
    expect(MetalType::pluck('name')->all())->toContain('Gold', 'Silver', 'Antique (Jadtar)', 'Diamond')
        ->and(Purity::count())->toBeGreaterThan(8)
        ->and(ItemGroup::pluck('prefix')->all())->toContain('RNG', 'NCK', 'BNG')
        ->and(StoneMaster::where('kind', 'stone')->count())->toBeGreaterThan(0)
        ->and(StoneMaster::where('kind', 'diamond')->count())->toBeGreaterThan(0)
        ->and(MakingCharge::pluck('charge_type')->unique()->values()->all())
        ->toEqualCanonicalizing(['fixed', 'per_gram', 'percentage']);
});

it('retires the unused category permissions', function () {
    expect(Permission::where('name', 'like', 'category.%')->count())->toBe(0)
        ->and(Permission::where('name', 'metal_type.view')->exists())->toBeTrue();
});

it('renders every master listing', function (string $route) {
    $this->actingAs($this->admin)->get(route($route))->assertOk();
})->with([
    'metal-types.index', 'purities.index', 'rates.index', 'rates.today',
    'item-groups.index', 'stones.index', 'diamonds.index', 'making-charges.index', 'items.index',
]);

it('returns a datatables payload for every master listing', function (string $route, array $columns) {
    $response = $this->actingAs($this->admin)->getJson(route($route, dtParams($columns)));

    $response->assertOk()->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

    expect($response->json('recordsTotal'))->toBeGreaterThan(0)
        ->and($response->json('data.0'))->toHaveKeys($columns);
})->with([
    ['metal-types.index', ['name', 'code', 'purities_count', 'action']],
    ['purities.index', ['metal_type', 'name', 'rate', 'action']],
    ['item-groups.index', ['name', 'prefix', 'next_code', 'action']],
    ['stones.index', ['name', 'code', 'rate', 'action']],
    ['diamonds.index', ['name', 'code', 'rate', 'action']],
    ['making-charges.index', ['code', 'name', 'type', 'applies', 'action']],
]);

it('keeps the stones and diamonds listings scoped to their own kind', function () {
    $stones = $this->actingAs($this->admin)->getJson(route('stones.index', dtParams(['name'])));
    $diamonds = $this->actingAs($this->admin)->getJson(route('diamonds.index', dtParams(['name'])));

    expect($stones->json('recordsTotal'))->toBe(StoneMaster::where('kind', 'stone')->count())
        ->and($diamonds->json('recordsTotal'))->toBe(StoneMaster::where('kind', 'diamond')->count())
        ->and($stones->json('recordsTotal'))->not->toBe($diamonds->json('recordsTotal'));
});

it('files a new record under the kind of the screen it was created from', function () {
    $this->actingAs($this->admin)->post(route('diamonds.store'), [
        'name' => 'Baguette VS',
        'rate_unit' => 'carat',
        'default_rate' => 40000,
        'is_active' => '1',
    ])->assertRedirect(route('diamonds.index'));

    expect(StoneMaster::where('name', 'Baguette VS')->firstOrFail()->kind)->toBe('diamond');
});

it('does not expose a diamond through the stones screen', function () {
    $diamond = StoneMaster::where('kind', 'diamond')->firstOrFail();

    $this->actingAs($this->admin)->get(route('stones.edit', $diamond))->assertNotFound();
    $this->actingAs($this->admin)->get(route('diamonds.edit', $diamond))->assertOk();
});

it('creates and updates a metal type', function () {
    $this->actingAs($this->admin)->post(route('metal-types.store'), [
        'name' => 'Platinum',
        'code' => 'plat',
        'is_active' => '1',
    ])->assertRedirect(route('metal-types.index'));

    $platinum = MetalType::where('name', 'Platinum')->firstOrFail();
    expect($platinum->code)->toBe('PLAT');

    $this->actingAs($this->admin)->put(route('metal-types.update', $platinum), [
        'name' => 'Platinum 950',
        'code' => 'PLAT',
        'is_active' => '1',
    ])->assertRedirect();

    expect($platinum->fresh()->name)->toBe('Platinum 950');
});

it('scopes purity name uniqueness to the metal type', function () {
    $antique = MetalType::where('code', 'ANTQ')->firstOrFail();
    $gold = MetalType::where('code', 'GOLD')->firstOrFail();

    // 22K already exists under both Gold and Antique, which is legitimate.
    expect(Purity::where('name', '22K')->count())->toBe(2);

    $this->actingAs($this->admin)->post(route('purities.store'), [
        'metal_type_id' => $gold->id,
        'name' => '22K',
        'default_per_grams' => 10,
    ])->assertSessionHasErrors('name');

    $this->actingAs($this->admin)->post(route('purities.store'), [
        'metal_type_id' => $antique->id,
        'name' => '18K',
        'default_per_grams' => 10,
    ])->assertRedirect();
});

it('requires a weight basis only for per-gram making charges', function () {
    $this->actingAs($this->admin)->post(route('making-charges.store'), [
        'code' => 'MC-TEST',
        'name' => 'Test',
        'charge_type' => 'per_gram',
        'rate' => 300,
    ])->assertSessionHasErrors('weight_basis');

    $this->actingAs($this->admin)->post(route('making-charges.store'), [
        'code' => 'MC-TEST',
        'name' => 'Test',
        'charge_type' => 'fixed',
        'rate' => 300,
    ])->assertRedirect();

    expect(MakingCharge::where('code', 'MC-TEST')->firstOrFail()->weight_basis)->toBeNull();
});

it('rejects a percentage making charge above 100', function () {
    $this->actingAs($this->admin)->post(route('making-charges.store'), [
        'code' => 'MC-BAD',
        'name' => 'Bad',
        'charge_type' => 'percentage',
        'rate' => 150,
    ])->assertSessionHasErrors('rate');
});

it('blocks deleting a master that is still in use', function () {
    $gold = MetalType::where('code', 'GOLD')->firstOrFail();

    $this->actingAs($this->admin)->delete(route('metal-types.destroy', $gold))->assertSessionHas('error');

    expect(MetalType::whereKey($gold->id)->exists())->toBeTrue();
});

it('allows deleting an unused master', function () {
    $charge = MakingCharge::create(['code' => 'MC-UNUSED', 'name' => 'Unused', 'charge_type' => 'fixed', 'rate' => 1]);

    $this->actingAs($this->admin)->delete(route('making-charges.destroy', $charge))->assertRedirect();

    expect(MakingCharge::whereKey($charge->id)->exists())->toBeFalse();
});

it('lets a sales user read the masters but not change them', function () {
    $this->actingAs($this->sales)->get(route('metal-types.index'))->assertOk();
    $this->actingAs($this->sales)->getJson(route('stones.index', dtParams(['name'])))->assertOk();

    $this->actingAs($this->sales)->get(route('metal-types.create'))->assertForbidden();
    $this->actingAs($this->sales)->post(route('metal-types.store'), ['name' => 'X', 'code' => 'X'])->assertForbidden();
    $this->actingAs($this->sales)->post(route('rates.today.store'), ['date' => today()->toDateString()])->assertForbidden();

    $charge = MakingCharge::first();
    $this->actingAs($this->sales)->delete(route('making-charges.destroy', $charge))->assertForbidden();
});

it('shows the masters menu only to users who can read them', function () {
    $none = User::factory()->create();

    $this->actingAs($this->admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('metal-types.index'));

    $this->actingAs($none)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(route('metal-types.index'))
        ->assertDontSee(route('making-charges.index'));
});

// --- the label template a metal type prints with -------------------------------------

it('renders the metal type form with the label templates to choose from', function () {
    $template = LabelSetting::create([
        'name' => 'Jadtar Tag',
        'layout' => LabelSetting::LAYOUT_STONE_DETAIL,
        'tag_height_mm' => 32,
    ]);

    // Both screens 500 if the controller forgets to pass $labelSettings.
    $this->actingAs($this->admin)->get(route('metal-types.create'))
        ->assertOk()
        ->assertSee('Use the default template')
        ->assertSee('Jadtar Tag');

    $metalType = MetalType::firstOrFail();

    $this->actingAs($this->admin)->get(route('metal-types.edit', $metalType))
        ->assertOk()
        ->assertSee($template->name);
});

it('saves the label template chosen for a metal type, and lets it be cleared', function () {
    $template = LabelSetting::create([
        'name' => 'Diamond Tag',
        'layout' => LabelSetting::LAYOUT_DIAMOND_DETAIL,
        'tag_height_mm' => 30,
    ]);

    $metalType = MetalType::where('code', 'DIAM')->firstOrFail();

    $payload = fn (array $overrides = []) => array_merge([
        'name' => $metalType->name,
        'code' => $metalType->code,
        'sort_order' => $metalType->sort_order,
        'is_active' => '1',
    ], $overrides);

    $this->actingAs($this->admin)
        ->put(route('metal-types.update', $metalType), $payload(['label_setting_id' => $template->id]))
        ->assertRedirect();

    expect($metalType->fresh()->label_setting_id)->toBe($template->id);

    // Blank means "use the default", not "keep what was there".
    $this->actingAs($this->admin)
        ->put(route('metal-types.update', $metalType), $payload(['label_setting_id' => '']))
        ->assertRedirect();

    expect($metalType->fresh()->label_setting_id)->toBeNull();
});

it('rejects a label template that does not exist', function () {
    $metalType = MetalType::firstOrFail();

    $this->actingAs($this->admin)->put(route('metal-types.update', $metalType), [
        'name' => $metalType->name,
        'code' => $metalType->code,
        'label_setting_id' => 999999,
    ])->assertSessionHasErrors('label_setting_id');
});
