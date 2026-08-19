<?php

use App\Models\AppSetting;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

/**
 * @return array<string, mixed>
 */
function appSettingPayload(array $overrides = []): array
{
    return array_merge([
        'app_name' => 'Shree Jewellers',
        'media_disk' => 'public',
        'sidebar_user_bg_from' => '#ff6600',
        'sidebar_user_bg_to' => '#993300',
        'sidebar_user_text_color' => '#ffffff',
    ], $overrides);
}

it('creates the singleton with usable defaults', function () {
    expect(AppSetting::count())->toBe(0);

    $settings = AppSetting::current();

    expect(AppSetting::count())->toBe(1)
        ->and($settings->app_name)->toBe('Jewel')
        ->and($settings->sidebar_user_bg_from)->toBe('#0acf97');
});

it('never creates a second row', function () {
    AppSetting::current();
    AppSetting::current();

    expect(AppSetting::count())->toBe(1);
});

it('renders the appearance screen', function () {
    $this->actingAs($this->admin)->get(route('app-settings.edit'))->assertOk();
});

it('saves the app name and colours', function () {
    $this->actingAs($this->admin)->put(route('app-settings.update'), appSettingPayload())
        ->assertRedirect(route('app-settings.edit'));

    $settings = AppSetting::current();

    expect($settings->app_name)->toBe('Shree Jewellers')
        ->and($settings->sidebar_user_bg_from)->toBe('#ff6600')
        ->and($settings->sidebar_user_bg_to)->toBe('#993300');
});

it('shows the app name in the page title and footer', function () {
    AppSetting::current()->update(['app_name' => 'Shree Jewellers']);

    $this->actingAs($this->admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Shree Jewellers');
});

it('applies the sidebar colours to the rendered page', function () {
    AppSetting::current()->update(['sidebar_user_bg_from' => '#ff6600', 'sidebar_user_bg_to' => '#993300']);

    $this->actingAs($this->admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('linear-gradient(135deg, #ff6600 0%, #993300 100%)', false);
});

it('rejects a colour that is not a hex triplet', function () {
    $this->actingAs($this->admin)
        ->put(route('app-settings.update'), appSettingPayload(['sidebar_user_bg_from' => 'red']))
        ->assertSessionHasErrors('sidebar_user_bg_from');

    $this->actingAs($this->admin)
        ->put(route('app-settings.update'), appSettingPayload(['sidebar_user_bg_to' => '#fff']))
        ->assertSessionHasErrors('sidebar_user_bg_to');
});

it('requires the app name', function () {
    $this->actingAs($this->admin)
        ->put(route('app-settings.update'), appSettingPayload(['app_name' => '']))
        ->assertSessionHasErrors('app_name');
});

it('uploads a logo and serves it in place of the theme default', function () {
    $themeDefault = AppSetting::current()->logoUrl('logo_path');

    $this->actingAs($this->admin)->put(route('app-settings.update'), appSettingPayload([
        'logo' => UploadedFile::fake()->image('brand.png', 200, 40),
    ]))->assertRedirect();

    $settings = AppSetting::current();

    Storage::disk('public')->assertExists($settings->logo_path);

    expect($settings->logo_path)->toStartWith('branding/')
        ->and($settings->hasCustomLogo('logo_path'))->toBeTrue()
        ->and($settings->logoUrl('logo_path'))->not->toBe($themeDefault);
});

it('falls back to the theme logo when a slot is empty', function () {
    $settings = AppSetting::current();

    expect($settings->hasCustomLogo('logo_path'))->toBeFalse()
        ->and($settings->logoUrl('logo_path'))->toContain('theme/assets/images/logo.png')
        ->and($settings->logoUrl('logo_dark_path'))->toContain('logo-dark.png')
        ->and($settings->logoUrl('logo_small_path'))->toContain('logo-sm.png');
});

it('falls back when the stored file has gone missing', function () {
    AppSetting::current()->update(['logo_path' => 'branding/deleted.png']);

    expect(AppSetting::current()->logoUrl('logo_path'))->toContain('theme/assets/images/logo.png');
});

it('deletes the previous file when a logo is replaced', function () {
    $this->actingAs($this->admin)->put(route('app-settings.update'), appSettingPayload([
        'logo' => UploadedFile::fake()->image('first.png'),
    ]));

    $first = AppSetting::current()->logo_path;

    $this->actingAs($this->admin)->put(route('app-settings.update'), appSettingPayload([
        'logo' => UploadedFile::fake()->image('second.png'),
    ]));

    $second = AppSetting::current()->logo_path;

    expect($second)->not->toBe($first);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);
});

it('removes a logo on request and cleans up the file', function () {
    $this->actingAs($this->admin)->put(route('app-settings.update'), appSettingPayload([
        'logo' => UploadedFile::fake()->image('brand.png'),
    ]));

    $path = AppSetting::current()->logo_path;

    $this->actingAs($this->admin)->put(route('app-settings.update'), appSettingPayload([
        'remove_logo' => '1',
    ]))->assertRedirect();

    expect(AppSetting::current()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('keeps the existing logo when nothing is uploaded', function () {
    $this->actingAs($this->admin)->put(route('app-settings.update'), appSettingPayload([
        'logo' => UploadedFile::fake()->image('brand.png'),
    ]));

    $path = AppSetting::current()->logo_path;

    $this->actingAs($this->admin)->put(route('app-settings.update'), appSettingPayload(['app_name' => 'Renamed']));

    expect(AppSetting::current()->logo_path)->toBe($path)
        ->and(AppSetting::current()->app_name)->toBe('Renamed');
    Storage::disk('public')->assertExists($path);
});

it('rejects a non-image and an oversized file', function () {
    $this->actingAs($this->admin)->put(route('app-settings.update'), appSettingPayload([
        'logo' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
    ]))->assertSessionHasErrors('logo');

    $this->actingAs($this->admin)->put(route('app-settings.update'), appSettingPayload([
        'logo' => UploadedFile::fake()->image('huge.png')->size(2048),
    ]))->assertSessionHasErrors('logo');
});

it('gates the appearance screen by permission', function () {
    $sales = User::factory()->create();
    $sales->assignRole('Sales');

    $this->actingAs($sales)->get(route('app-settings.edit'))->assertOk();
    $this->actingAs($sales)->put(route('app-settings.update'), appSettingPayload())->assertForbidden();

    $nobody = User::factory()->create();
    $this->actingAs($nobody)->get(route('app-settings.edit'))->assertForbidden();
});
