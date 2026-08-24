<?php

use App\Models\LabelSetting;
use App\Models\MetalType;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

/**
 * @return array<string, mixed>
 */
function settingsPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Standard Tag',
        'layout' => LabelSetting::LAYOUT_STANDARD,
        'shop_name' => 'Shree Jewellers',
        'tag_width_mm' => 110,
        'tag_height_mm' => 18,
        'margin_mm' => 2,
        'font_size_pt' => 6,
        'max_stone_rows' => 6,
        'show_gross' => '1',
        'show_net' => '1',
        'show_purity' => '1',
        'show_stone' => '1',
        'show_diamond' => '1',
        'show_stone_rate' => '1',
        'show_extra_charges' => '1',
        'show_oc' => '1',
        'show_making_charge' => '1',
        'show_item_name' => '1',
        'show_shop_name' => '1',
        'qr_enabled' => '0',
        'qr_content' => 'item_code',
        'qr_size_mm' => 12,
    ], $overrides);
}

/**
 * A saved template, straight through the model — the form is exercised separately.
 */
function makeTemplate(array $attributes = []): LabelSetting
{
    return LabelSetting::create(array_merge([
        'name' => 'Jadtar Tag',
        'layout' => LabelSetting::LAYOUT_STONE_DETAIL,
        'tag_width_mm' => 110,
        'tag_height_mm' => 32,
    ], $attributes));
}

// --- the default template -------------------------------------------------------------

it('creates the default template on first read, with usable defaults', function () {
    expect(LabelSetting::count())->toBe(0);

    $settings = LabelSetting::default();

    // The instance must carry the defaults immediately — a freshly created row
    // with null dimensions would render the first tag as a 0 x 0 page.
    expect(LabelSetting::count())->toBe(1)
        ->and($settings->name)->toBe('Standard Tag')
        ->and($settings->layout)->toBe(LabelSetting::LAYOUT_STANDARD)
        ->and($settings->is_default)->toBeTrue()
        ->and((float) $settings->tag_width_mm)->toBe(110.0)
        ->and((float) $settings->tag_height_mm)->toBe(18.0)
        ->and($settings->paperBox())->toBe([0.0, 0.0, 311.81, 51.02]);
});

it('reuses the existing default rather than creating another', function () {
    LabelSetting::default();
    LabelSetting::default();

    expect(LabelSetting::count())->toBe(1);
});

it('promotes the oldest row when the default flag has been lost', function () {
    $first = LabelSetting::default();

    // A database edited by hand can end up with nothing flagged.
    LabelSetting::query()->update(['is_default' => false]);

    expect(LabelSetting::default()->id)->toBe($first->id)
        ->and(LabelSetting::find($first->id)->is_default)->toBeTrue();
});

it('keeps exactly one default when another is promoted', function () {
    $first = LabelSetting::default();
    $second = makeTemplate();

    $second->makeDefault();

    expect(LabelSetting::where('is_default', true)->count())->toBe(1)
        ->and(LabelSetting::find($second->id)->is_default)->toBeTrue()
        ->and(LabelSetting::find($first->id)->is_default)->toBeFalse();
});

// --- the listing and the form ----------------------------------------------------------

it('renders the listing and its datatables payload', function () {
    $this->actingAs($this->admin)->get(route('label-settings.index'))->assertOk();

    $response = $this->actingAs($this->admin)
        ->getJson(route('label-settings.index', dtParams(['name', 'layout'])));

    // Reading the index creates the default, so there is always one row.
    $response->assertOk()->assertJsonPath('recordsTotal', 1);

    expect($response->json('data.0'))->toHaveKeys(['name', 'layout', 'size', 'qr', 'metal_types_count', 'action'])
        ->and($response->json('data.0.name'))->toContain('Default')
        ->and($response->json('data.0.size'))->toContain('110');
});

it('renders the add and edit forms', function () {
    $template = makeTemplate();

    $this->actingAs($this->admin)->get(route('label-settings.create'))->assertOk();
    $this->actingAs($this->admin)->get(route('label-settings.edit', $template))
        ->assertOk()
        ->assertSee('Jadtar Tag');
});

it('creates a template, which is not the default when one already exists', function () {
    LabelSetting::default();

    $this->actingAs($this->admin)->post(route('label-settings.store'), settingsPayload([
        'name' => 'Diamond Tag',
        'layout' => LabelSetting::LAYOUT_DIAMOND_DETAIL,
        'tag_height_mm' => 30,
    ]))->assertRedirect();

    $created = LabelSetting::where('name', 'Diamond Tag')->firstOrFail();

    expect($created->layout)->toBe(LabelSetting::LAYOUT_DIAMOND_DETAIL)
        ->and($created->is_default)->toBeFalse()
        ->and(LabelSetting::count())->toBe(2);
});

it('makes the very first template the default, or nothing could print', function () {
    expect(LabelSetting::count())->toBe(0);

    $this->actingAs($this->admin)->post(route('label-settings.store'), settingsPayload([
        'name' => 'Only Tag',
    ]))->assertRedirect();

    expect(LabelSetting::where('name', 'Only Tag')->firstOrFail()->is_default)->toBeTrue();
});

it('rejects a duplicate name and an unknown layout', function () {
    makeTemplate(['name' => 'Jadtar Tag']);

    $this->actingAs($this->admin)
        ->post(route('label-settings.store'), settingsPayload(['name' => 'Jadtar Tag']))
        ->assertSessionHasErrors('name');

    $this->actingAs($this->admin)
        ->post(route('label-settings.store'), settingsPayload(['name' => 'Other', 'layout' => 'fancy']))
        ->assertSessionHasErrors('layout');
});

it('round-trips an update', function () {
    $template = makeTemplate(['name' => 'Standard Tag', 'layout' => LabelSetting::LAYOUT_STANDARD]);

    $this->actingAs($this->admin)->put(route('label-settings.update', $template), settingsPayload([
        'tag_width_mm' => 75,
        'tag_height_mm' => 20,
        'show_purity' => '0',
        'qr_enabled' => '1',
        'qr_size_mm' => 10,
    ]))->assertRedirect(route('label-settings.edit', $template));

    $template->refresh();

    expect((float) $template->tag_width_mm)->toBe(75.0)
        ->and((float) $template->tag_height_mm)->toBe(20.0)
        ->and($template->show_purity)->toBeFalse()
        ->and($template->qr_enabled)->toBeTrue()
        ->and($template->shop_name)->toBe('Shree Jewellers')
        ->and(LabelSetting::count())->toBe(1);
});

// --- validation --------------------------------------------------------------------------

it('rejects a tag size that is zero or negative', function () {
    $template = makeTemplate();

    $this->actingAs($this->admin)
        ->put(route('label-settings.update', $template), settingsPayload(['tag_width_mm' => 0]))
        ->assertSessionHasErrors('tag_width_mm');

    $this->actingAs($this->admin)
        ->put(route('label-settings.update', $template), settingsPayload(['tag_height_mm' => -5]))
        ->assertSessionHasErrors('tag_height_mm');
});

it('rejects a qr taller than the tag can hold', function () {
    $template = makeTemplate();

    // 18 mm less 2 mm margins each side leaves 14 mm.
    $this->actingAs($this->admin)
        ->put(route('label-settings.update', $template), settingsPayload(['qr_enabled' => '1', 'qr_size_mm' => 16]))
        ->assertSessionHasErrors('qr_size_mm');

    $this->actingAs($this->admin)
        ->put(route('label-settings.update', $template), settingsPayload(['qr_enabled' => '1', 'qr_size_mm' => 14]))
        ->assertSessionHasNoErrors();
});

it('ignores the qr size when the qr is off', function () {
    $template = makeTemplate();

    $this->actingAs($this->admin)
        ->put(route('label-settings.update', $template), settingsPayload(['qr_enabled' => '0', 'qr_size_mm' => 16]))
        ->assertSessionHasNoErrors();
});

it('refuses a detail layout on a tag too short to hold it', function () {
    $template = makeTemplate();

    // The detail layouts stack a row per stone; on 18 mm stock they run to a
    // second page and waste a label on every print.
    $this->actingAs($this->admin)->put(route('label-settings.update', $template), settingsPayload([
        'layout' => LabelSetting::LAYOUT_STONE_DETAIL,
        'tag_height_mm' => 18,
    ]))->assertSessionHasErrors('tag_height_mm');

    $this->actingAs($this->admin)->put(route('label-settings.update', $template), settingsPayload([
        'layout' => LabelSetting::LAYOUT_STONE_DETAIL,
        'tag_height_mm' => 32,
    ]))->assertSessionHasNoErrors();

    // The standard layout is unaffected — it has always fitted 18 mm.
    $this->actingAs($this->admin)->put(route('label-settings.update', $template), settingsPayload([
        'layout' => LabelSetting::LAYOUT_STANDARD,
        'tag_height_mm' => 18,
    ]))->assertSessionHasNoErrors();
});

it('unticked switches save as false', function () {
    $template = makeTemplate();

    $this->actingAs($this->admin)->put(route('label-settings.update', $template), settingsPayload([
        'show_gross' => '0',
        'show_stone' => '0',
    ]))->assertRedirect();

    $template->refresh();

    expect($template->show_gross)->toBeFalse()
        ->and($template->show_stone)->toBeFalse()
        ->and($template->show_net)->toBeTrue();
});

// --- duplicate, default, delete -----------------------------------------------------------

it('duplicates a template onto a free name, never as the default', function () {
    $original = LabelSetting::default();

    $this->actingAs($this->admin)->post(route('label-settings.duplicate', $original))->assertRedirect();

    $copy = LabelSetting::where('name', 'Copy of Standard Tag')->firstOrFail();

    expect(LabelSetting::count())->toBe(2)
        // replicate() ignores fillable, so this is the assertion that stops the
        // copy stealing the default flag.
        ->and($copy->is_default)->toBeFalse()
        ->and($copy->layout)->toBe($original->layout);

    // A second copy cannot reuse the name — the column is unique.
    $this->actingAs($this->admin)->post(route('label-settings.duplicate', $original))->assertRedirect();

    expect(LabelSetting::where('name', 'Copy of Standard Tag (2)')->exists())->toBeTrue();
});

it('moves the default through its own route', function () {
    $first = LabelSetting::default();
    $second = makeTemplate();

    $this->actingAs($this->admin)->post(route('label-settings.default', $second))->assertRedirect();

    expect(LabelSetting::find($second->id)->is_default)->toBeTrue()
        ->and(LabelSetting::find($first->id)->is_default)->toBeFalse()
        ->and(LabelSetting::where('is_default', true)->count())->toBe(1);
});

it('refuses to delete the default template', function () {
    $default = LabelSetting::default();

    $this->actingAs($this->admin)->delete(route('label-settings.destroy', $default))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(LabelSetting::find($default->id))->not->toBeNull();
});

it('refuses to delete a template a metal type is using', function () {
    LabelSetting::default();
    $template = makeTemplate();

    MetalType::where('code', 'DIAM')->firstOrFail()->update(['label_setting_id' => $template->id]);

    $this->actingAs($this->admin)->delete(route('label-settings.destroy', $template))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(LabelSetting::find($template->id))->not->toBeNull();
});

it('deletes an unused template that is not the default', function () {
    LabelSetting::default();
    $template = makeTemplate();

    $this->actingAs($this->admin)->delete(route('label-settings.destroy', $template))
        ->assertRedirect(route('label-settings.index'))
        ->assertSessionHas('success');

    expect(LabelSetting::find($template->id))->toBeNull();
});

// --- permissions ---------------------------------------------------------------------------

it('lets sales read the templates but not change them', function () {
    $sales = User::factory()->create();
    $sales->assignRole('Sales');

    $template = makeTemplate();

    $this->actingAs($sales)->get(route('label-settings.index'))->assertOk();
    $this->actingAs($sales)->get(route('label-settings.create'))->assertForbidden();
    $this->actingAs($sales)->post(route('label-settings.store'), settingsPayload(['name' => 'X']))->assertForbidden();
    $this->actingAs($sales)->put(route('label-settings.update', $template), settingsPayload())->assertForbidden();
    $this->actingAs($sales)->post(route('label-settings.duplicate', $template))->assertForbidden();
    $this->actingAs($sales)->post(route('label-settings.default', $template))->assertForbidden();
    $this->actingAs($sales)->delete(route('label-settings.destroy', $template))->assertForbidden();
});

it('hides the templates from a user without the permission', function () {
    $nobody = User::factory()->create();

    $this->actingAs($nobody)->get(route('label-settings.index'))->assertForbidden();
});
