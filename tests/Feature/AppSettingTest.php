<?php

use App\Models\AppSetting;
use App\Models\ItemEstimate;
use App\Models\OgEstimate;
use App\Models\RepairForm;
use App\Models\User;
use App\Models\Voucher;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        // Every required field on the settings form has to be here: the screen posts
        // them all, so omitting one fails the whole update.
        'angadiya_columns' => 3,
        'angadiya_slip_height_mm' => 45,
        'hallmark_next_lot_no' => 1,
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

// --- table header colour ------------------------------------------------------

it('falls back to the configured default when no colour is set', function () {
    // Config is set here rather than read from the file, so editing the shipped
    // defaults cannot break the suite.
    config(['appearance.table_header' => [
        'light' => ['bg' => '#f2f2f7', 'text' => '#212529'],
        'dark' => ['bg' => '#404954', 'text' => '#f8f9fa'],
    ]]);

    expect(AppSetting::current()->cssVariables())->toBe([
        '--app-thead-bg-light' => '#f2f2f7',
        '--app-thead-color-light' => '#212529',
        '--app-thead-bg-dark' => '#404954',
        '--app-thead-color-dark' => '#f8f9fa',
    ]);
});

it('leaves the header to the theme when config sets no colour', function () {
    config(['appearance.table_header' => ['light' => ['bg' => null], 'dark' => ['bg' => null]]]);

    expect(AppSetting::current()->cssVariables())->toBe([]);

    $this->actingAs($this->admin)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('--app-thead-bg-light', false);
});

it('accepts any css colour from config, not just hex', function () {
    // Config is developer-authored, so named colours and functions are fair game.
    config(['appearance.table_header' => [
        'light' => ['bg' => 'red', 'text' => 'white'],
        'dark' => ['bg' => 'rgb(64, 73, 84)', 'text' => null],
    ]]);

    $vars = AppSetting::current()->cssVariables();

    expect($vars['--app-thead-bg-light'])->toBe('red')
        ->and($vars['--app-thead-color-light'])->toBe('white')
        ->and($vars['--app-thead-bg-dark'])->toBe('rgb(64, 73, 84)')
        // Nothing to contrast against and no text configured, so it is omitted and
        // the stylesheet falls back to the theme's colour.
        ->and($vars)->not->toHaveKey('--app-thead-color-dark');
});

it('refuses a config colour that could break out of the style block', function () {
    config(['appearance.table_header' => [
        'light' => ['bg' => 'red; } body { display:none'],
        'dark' => ['bg' => null],
    ]]);

    expect(AppSetting::current()->cssVariables())->toBe([])
        ->and(AppSetting::cssColour('red; }'))->toBeNull()
        ->and(AppSetting::cssColour('  #fff  '))->toBe('#fff')
        ->and(AppSetting::cssColour(''))->toBeNull()
        ->and(AppSetting::cssColour(null))->toBeNull();
});

it('lets a chosen colour override the configured default for that mode only', function () {
    config(['appearance.table_header' => [
        'light' => ['bg' => '#f2f2f7', 'text' => '#212529'],
        'dark' => ['bg' => '#404954', 'text' => '#f8f9fa'],
    ]]);

    AppSetting::current()->update(['table_header_bg_light' => '#1f2d3d']);

    $vars = AppSetting::current()->cssVariables();

    expect($vars['--app-thead-bg-light'])->toBe('#1f2d3d')
        // A chosen colour still gets its contrast worked out automatically.
        ->and($vars['--app-thead-color-light'])->toBe('#ffffff')
        // Dark was left alone, so it keeps the configured default.
        ->and($vars['--app-thead-bg-dark'])->toBe('#404954')
        ->and($vars['--app-thead-color-dark'])->toBe('#f8f9fa');
});

it('picks a readable text colour for the header', function () {
    // WCAG luminance, so a saturated green counts as light and a blue as dark.
    expect(AppSetting::readableTextOn('#ffffff'))->toBe('#212529')
        ->and(AppSetting::readableTextOn('#f2f2f7'))->toBe('#212529')
        ->and(AppSetting::readableTextOn('#0acf97'))->toBe('#212529')
        ->and(AppSetting::readableTextOn('#1f2d3d'))->toBe('#ffffff')
        ->and(AppSetting::readableTextOn('#0d6efd'))->toBe('#ffffff');
});

it('emits the matching text colour alongside each background', function () {
    config(['appearance.table_header' => ['light' => ['bg' => null], 'dark' => ['bg' => null]]]);

    AppSetting::current()->update([
        'table_header_bg_light' => '#f2f2f7',
        'table_header_bg_dark' => '#404954',
    ]);

    expect(AppSetting::current()->cssVariables())->toBe([
        '--app-thead-bg-light' => '#f2f2f7',
        '--app-thead-color-light' => '#212529',
        '--app-thead-bg-dark' => '#404954',
        '--app-thead-color-dark' => '#ffffff',
    ]);
});

it('clears a colour when the theme default is ticked', function () {
    AppSetting::current()->update(['table_header_bg_light' => '#1f2d3d', 'table_header_bg_dark' => '#0b5ed7']);

    $this->actingAs($this->admin)->put(route('app-settings.update'), appSettingPayload([
        'table_header_bg_light' => '#1f2d3d',
        'table_header_default_light' => '1',
        'table_header_bg_dark' => '#0b5ed7',
    ]))->assertRedirect();

    $settings = AppSetting::current();

    // Only the light one falls back to the configured default; dark keeps its colour.
    expect($settings->table_header_bg_light)->toBeNull()
        ->and($settings->table_header_bg_dark)->toBe('#0b5ed7')
        ->and($settings->cssVariables()['--app-thead-bg-light'])
        ->toBe(config('appearance.table_header.light.bg'))
        ->and($settings->cssVariables()['--app-thead-bg-dark'])->toBe('#0b5ed7');
});

it('rejects a header colour that is not a hex triplet', function () {
    $this->actingAs($this->admin)
        ->put(route('app-settings.update'), appSettingPayload(['table_header_bg_light' => 'notacolour']))
        ->assertSessionHasErrors('table_header_bg_light');

    $this->actingAs($this->admin)
        ->put(route('app-settings.update'), appSettingPayload(['table_header_bg_dark' => '#fff']))
        ->assertSessionHasErrors('table_header_bg_dark');

    expect(AppSetting::current()->table_header_bg_light)->toBeNull();
});

it('offers both pickers on the appearance screen', function () {
    $this->actingAs($this->admin)->get(route('app-settings.edit'))
        ->assertOk()
        ->assertSee('Table Header')
        ->assertSee('table_header_bg_light', false)
        ->assertSee('table_header_bg_dark', false)
        ->assertSee('table_header_default_light', false);
});

// --- caching the settings row ----------------------------------------------------

it('reads the settings from the database while caching is off', function () {
    // Off by default, so nothing changes for anyone who has not asked for it.
    expect(AppSetting::current()->settings_cache_enabled)->toBeFalse()
        ->and(Cache::has(AppSetting::CACHE_KEY))->toBeFalse();

    AppSetting::current();

    expect(Cache::has(AppSetting::CACHE_KEY))->toBeFalse();
});

it('serves the settings from the cache once switched on', function () {
    AppSetting::current()->update(['settings_cache_enabled' => true, 'app_name' => 'Jewel']);

    // Saving clears it; the next read fills it.
    expect(Cache::has(AppSetting::CACHE_KEY))->toBeFalse();

    AppSetting::current();

    expect(Cache::has(AppSetting::CACHE_KEY))->toBeTrue();

    // A change made behind Eloquent's back is exactly what a cache cannot see, which
    // is the point of clearing it on save rather than trusting a timer.
    DB::table('app_settings')->update(['app_name' => 'Changed In The Database']);

    expect(AppSetting::current()->app_name)->toBe('Jewel');

    AppSetting::flushCache();

    expect(AppSetting::current()->app_name)->toBe('Changed In The Database');
});

it('clears the cache whenever the settings are saved', function () {
    AppSetting::current()->update(['settings_cache_enabled' => true]);
    AppSetting::current();

    expect(Cache::has(AppSetting::CACHE_KEY))->toBeTrue();

    AppSetting::current()->update(['app_name' => 'Renamed']);

    expect(Cache::has(AppSetting::CACHE_KEY))->toBeFalse()
        ->and(AppSetting::current()->app_name)->toBe('Renamed');
});

it('drops the cached copy when caching is switched back off', function () {
    AppSetting::current()->update(['settings_cache_enabled' => true]);
    AppSetting::current();

    expect(Cache::has(AppSetting::CACHE_KEY))->toBeTrue();

    AppSetting::current()->update(['settings_cache_enabled' => false]);
    AppSetting::current();

    // Nothing left behind to go stale.
    expect(Cache::has(AppSetting::CACHE_KEY))->toBeFalse();
});

it('turns caching on from the settings page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->put(route('app-settings.update'), appSettingPayload(['settings_cache_enabled' => '1']))
        ->assertRedirect();

    expect(AppSetting::current()->settings_cache_enabled)->toBeTrue();

    $this->actingAs($admin)
        ->put(route('app-settings.update'), appSettingPayload())
        ->assertRedirect();

    // The switch is a checkbox, so an unticked box means off.
    expect(AppSetting::current()->settings_cache_enabled)->toBeFalse();
});

it('still issues counters from the database, not the cached copy', function () {
    AppSetting::current()->update(['settings_cache_enabled' => true, 'repair_next_ref_no' => 205]);
    AppSetting::current();

    // The counters take a row lock, which a cached copy could never provide; two in
    // a row must not hand out the same number.
    expect(RepairForm::nextRefNo())->toBe(205)
        ->and(RepairForm::nextRefNo())->toBe(206)
        ->and((int) AppSetting::current()->repair_next_ref_no)->toBe(207);
});

// --- estimate and voucher numbering ----------------------------------------------------

it('sets the prefix and counter for each estimate and the voucher', function () {
    $this->actingAs($this->admin)->put(route('app-settings.update'), appSettingPayload([
        'og_estimate_ref_prefix' => 'OGE',
        'og_estimate_next_ref_no' => 41,
        'item_estimate_ref_prefix' => 'RE',
        'item_estimate_next_ref_no' => 43,
        'voucher_ref_prefix' => 'VCH',
        'voucher_next_ref_no' => 42,
    ]))->assertRedirect();

    $settings = AppSetting::current()->fresh();

    expect($settings->og_estimate_ref_prefix)->toBe('OGE')
        ->and($settings->og_estimate_next_ref_no)->toBe(41)
        ->and($settings->item_estimate_ref_prefix)->toBe('RE')
        ->and($settings->item_estimate_next_ref_no)->toBe(43)
        ->and($settings->voucher_ref_prefix)->toBe('VCH')
        ->and($settings->voucher_next_ref_no)->toBe(42);

    // Each counter is its own; setting one must not disturb the others.
    expect(OgEstimate::refPrefix())->toBe('OGE')
        ->and(ItemEstimate::refPrefix())->toBe('RE')
        ->and(Voucher::refPrefix())->toBe('VCH');
});

it('allows a blank prefix so the reference prints as a bare number', function () {
    $this->actingAs($this->admin)->put(route('app-settings.update'), appSettingPayload([
        'og_estimate_ref_prefix' => '',
    ]))->assertRedirect();

    expect(OgEstimate::refPrefix())->toBe('');
});

it('rejects a prefix that is not plain letters and digits', function () {
    $this->actingAs($this->admin)->put(route('app-settings.update'), appSettingPayload([
        'voucher_ref_prefix' => 'VC-1/',
    ]))->assertSessionHasErrors('voucher_ref_prefix');
});

it('sets the gst rate estimates snapshot from', function () {
    $this->actingAs($this->admin)->put(route('app-settings.update'), appSettingPayload([
        'gst_percent' => '5',
    ]))->assertRedirect();

    expect((float) AppSetting::current()->fresh()->gst_percent)->toBe(5.0);

    $this->actingAs($this->admin)->put(route('app-settings.update'), appSettingPayload([
        'gst_percent' => '101',
    ]))->assertSessionHasErrors('gst_percent');
});

it('shows all three on the appearance page', function () {
    $this->actingAs($this->admin)->get(route('app-settings.edit'))
        ->assertOk()
        ->assertSee('Estimates, Vouchers &amp; Cash', false)
        ->assertSee('name="og_estimate_ref_prefix"', false)
        ->assertSee('name="og_estimate_next_ref_no"', false)
        ->assertSee('name="item_estimate_ref_prefix"', false)
        ->assertSee('name="item_estimate_next_ref_no"', false)
        ->assertSee('name="voucher_ref_prefix"', false)
        ->assertSee('name="voucher_next_ref_no"', false)
        ->assertSee('name="gst_percent"', false);
});

it('keeps a blank prefix as no prefix, and leaves a cleared counter alone', function () {
    AppSetting::current()->update(['order_next_ref_no' => 160]);

    // These columns are NOT NULL, and ConvertEmptyStringsToNull turns a cleared box
    // into null on the way in — which used to reach the database as a 500.
    $this->actingAs($this->admin)->put(route('app-settings.update'), appSettingPayload([
        'og_estimate_ref_prefix' => '',
        'order_next_ref_no' => '',
        'gst_percent' => '',
    ]))->assertRedirect();

    $settings = AppSetting::current()->fresh();

    // A blank prefix is a real choice: the reference prints as a bare number.
    expect($settings->og_estimate_ref_prefix)->toBe('')
        // A blank counter is not a choice at all, so the saved one stands.
        ->and($settings->order_next_ref_no)->toBe(160)
        ->and((float) $settings->gst_percent)->toBe(3.0);
});
