<?php

use App\Models\LabelSetting;
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
        'shop_name' => 'Shree Jewellers',
        'tag_width_mm' => 110,
        'tag_height_mm' => 18,
        'margin_mm' => 2,
        'font_size_pt' => 6,
        'show_gross' => '1',
        'show_net' => '1',
        'show_purity' => '1',
        'show_stone' => '1',
        'show_diamond' => '1',
        'show_extra_charges' => '1',
        'show_shop_name' => '1',
        'qr_enabled' => '0',
        'qr_content' => 'item_code',
        'qr_size_mm' => 12,
    ], $overrides);
}

it('creates the singleton on first read, with usable defaults', function () {
    expect(LabelSetting::count())->toBe(0);

    $settings = LabelSetting::current();

    // The instance must carry the defaults immediately — a freshly created row
    // with null dimensions would render the first tag as a 0 x 0 page.
    expect(LabelSetting::count())->toBe(1)
        ->and((float) $settings->tag_width_mm)->toBe(110.0)
        ->and((float) $settings->tag_height_mm)->toBe(18.0)
        ->and($settings->paperBox())->toBe([0.0, 0.0, 311.81, 51.02]);
});

it('never creates a second row', function () {
    LabelSetting::current();
    LabelSetting::current();

    expect(LabelSetting::count())->toBe(1);
});

it('renders the settings screen', function () {
    $this->actingAs($this->admin)->get(route('label-settings.edit'))->assertOk();
});

it('round-trips an update', function () {
    $this->actingAs($this->admin)->put(route('label-settings.update'), settingsPayload([
        'tag_width_mm' => 75,
        'tag_height_mm' => 20,
        'show_purity' => '0',
        'qr_enabled' => '1',
        'qr_size_mm' => 10,
    ]))->assertRedirect(route('label-settings.edit'));

    $settings = LabelSetting::current();

    expect((float) $settings->tag_width_mm)->toBe(75.0)
        ->and((float) $settings->tag_height_mm)->toBe(20.0)
        ->and($settings->show_purity)->toBeFalse()
        ->and($settings->qr_enabled)->toBeTrue()
        ->and($settings->shop_name)->toBe('Shree Jewellers')
        ->and(LabelSetting::count())->toBe(1);
});

it('rejects a tag size that is zero or negative', function () {
    $this->actingAs($this->admin)
        ->put(route('label-settings.update'), settingsPayload(['tag_width_mm' => 0]))
        ->assertSessionHasErrors('tag_width_mm');

    $this->actingAs($this->admin)
        ->put(route('label-settings.update'), settingsPayload(['tag_height_mm' => -5]))
        ->assertSessionHasErrors('tag_height_mm');
});

it('rejects a qr taller than the tag can hold', function () {
    // 18 mm less 2 mm margins each side leaves 14 mm.
    $this->actingAs($this->admin)
        ->put(route('label-settings.update'), settingsPayload(['qr_enabled' => '1', 'qr_size_mm' => 16]))
        ->assertSessionHasErrors('qr_size_mm');

    $this->actingAs($this->admin)
        ->put(route('label-settings.update'), settingsPayload(['qr_enabled' => '1', 'qr_size_mm' => 14]))
        ->assertSessionHasNoErrors();
});

it('ignores the qr size when the qr is off', function () {
    $this->actingAs($this->admin)
        ->put(route('label-settings.update'), settingsPayload(['qr_enabled' => '0', 'qr_size_mm' => 16]))
        ->assertSessionHasNoErrors();
});

it('unticked switches save as false', function () {
    $this->actingAs($this->admin)->put(route('label-settings.update'), settingsPayload([
        'show_gross' => '0',
        'show_stone' => '0',
    ]))->assertRedirect();

    expect(LabelSetting::current()->show_gross)->toBeFalse()
        ->and(LabelSetting::current()->show_stone)->toBeFalse()
        ->and(LabelSetting::current()->show_net)->toBeTrue();
});

it('lets sales read the settings but not change them', function () {
    $sales = User::factory()->create();
    $sales->assignRole('Sales');

    $this->actingAs($sales)->get(route('label-settings.edit'))->assertOk();
    $this->actingAs($sales)->put(route('label-settings.update'), settingsPayload())->assertForbidden();
});

it('hides the settings screen from a user without the permission', function () {
    $nobody = User::factory()->create();

    $this->actingAs($nobody)->get(route('label-settings.edit'))->assertForbidden();
});
