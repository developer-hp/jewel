<?php

use App\Models\AppSetting;
use App\Models\MetalRate;
use App\Models\Purity;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The public landing page and the Appearance tab that drives it.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

/**
 * Switch the page on with whatever else the test needs.
 *
 * Named distinctly: Pest's global helpers share one namespace across the whole
 * suite, and a redeclaration is a fatal, not a failure.
 */
function landingSettings(array $overrides = []): AppSetting
{
    $settings = AppSetting::current();
    $settings->update(array_merge(['landing_enabled' => true], $overrides));

    return $settings->fresh();
}

/** Mark a purity for publication and give it a rate on the given date. */
function landingRateFor(string $name, float $rate, ?string $date = null): Purity
{
    $purity = Purity::whereRelation('metalType', 'code', 'GOLD')->where('name', $name)->firstOrFail();
    $purity->update(['show_on_landing' => true]);

    MetalRate::updateOrCreate(
        ['purity_id' => $purity->id, 'effective_date' => $date ?? today()->toDateString()],
        ['rate' => $rate, 'per_grams' => 10],
    );

    return $purity;
}

// --- the gate ----------------------------------------------------------------------

// Off by default, so a fresh install behaves exactly as it did before this existed.
it('redirects to login until the page is switched on', function () {
    expect(AppSetting::current()->landing_enabled)->toBeFalse();

    $this->get('/')->assertRedirect(route('login'));
});

it('sends a signed-in visitor to the dashboard while it is off', function () {
    $this->actingAs($this->admin)->get('/')->assertRedirect(route('dashboard'));
});

it('is served to a guest with no authentication at all once switched on', function () {
    landingSettings(['firm_name' => 'KR Sons']);

    $this->get('/')
        ->assertOk()
        ->assertSee('KR Sons')
        // Raw: the page writes a literal apostrophe, which assertSee would otherwise
        // escape in the needle and never match.
        ->assertSee("Today's Rate", false);
});

// --- which rates appear ------------------------------------------------------------

it('publishes only the purities that are marked and priced today', function () {
    landingSettings();

    landingRateFor('24K', 159500);
    landingRateFor('22K', 146210);

    // Marked, but the shop has not priced it this morning.
    Purity::whereRelation('metalType', 'code', 'GOLD')->where('name', '18K')
        ->update(['show_on_landing' => true]);

    // Priced today, but never marked for publication.
    $unmarked = Purity::whereRelation('metalType', 'code', 'GOLD')->where('name', '14K')->firstOrFail();
    MetalRate::create([
        'purity_id' => $unmarked->id,
        'effective_date' => today()->toDateString(),
        'rate' => 93310,
        'per_grams' => 10,
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('159,500')
        ->assertSee('146,210')
        ->assertDontSee('18K Rate')
        ->assertDontSee('93,310');
});

/**
 * The whole reason the controller queries today's date rather than calling
 * Purity::rateOn(), which falls back to the latest earlier rate. On a public page
 * that would quietly republish yesterday's price as this morning's.
 */
it('never shows yesterday rate as today', function () {
    landingSettings();

    landingRateFor('22K', 140000, today()->subDay()->toDateString());

    $this->get('/')
        ->assertOk()
        ->assertDontSee('140,000')
        ->assertSee('Rates will be published shortly');
});

it('does not qualify the label when every rate is one metal', function () {
    landingSettings();
    landingRateFor('22K', 146210);

    // "22K Rate", not "Gold — 22K Rate".
    $this->get('/')->assertOk()->assertSee('22K Rate')->assertDontSee('Gold — 22K');
});

it('reads the rates in one query however many purities are published', function () {
    landingSettings();

    foreach (['24K', '22K', '18K', '14K'] as $name) {
        landingRateFor($name, 100000);
    }

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $this->get('/')->assertOk();

    // Settings, purities, metal types, rates — a handful, and flat in the number of
    // purities. A per-row rate lookup would put this in the twenties.
    expect($queries)->toBeLessThan(10);
});

// --- every block is optional -------------------------------------------------------

it('renders with nothing configured but the switch', function () {
    landingSettings();

    $this->get('/')
        ->assertOk()
        ->assertDontSee('Bank Account Details')
        ->assertDontSee('Payment QR')
        ->assertDontSee('Touch to Call')
        ->assertSee('Rates will be published shortly');
});

it('shows the announcement only when one is set', function () {
    landingSettings();
    $this->get('/')->assertOk()->assertDontSee('ri-megaphone-line', false);

    landingSettings(['landing_announcement' => 'Free polish this week']);
    $this->get('/')->assertOk()->assertSee('Free polish this week');
});

it('prints only the bank rows that are filled in', function () {
    landingSettings(['bank_ac_no' => '50200011856421', 'bank_ifsc' => 'HDFC0001285']);

    $this->get('/')
        ->assertOk()
        ->assertSee('Bank Account Details')
        ->assertSee('50200011856421')
        ->assertSee('HDFC0001285')
        // Nine fields exist; the seven left blank must not print their labels.
        ->assertDontSee('SWIFT CODE')
        ->assertDontSee('PURPOSE CODE');
});

it('shows the QR panel only when an image is stored and still on disk', function () {
    Storage::fake('public');

    landingSettings();
    $this->get('/')->assertOk()->assertDontSee('Payment QR');

    $path = UploadedFile::fake()->image('qr.png')->store('branding', 'public');
    landingSettings(['payment_qr_path' => $path]);
    $this->get('/')->assertOk()->assertSee('Payment QR');

    // A row pointing at a file that has since gone is not a broken image on a
    // customer's screen — the panel simply does not render.
    Storage::disk('public')->delete($path);
    $this->get('/')->assertOk()->assertDontSee('Payment QR');
});

it('links only the socials that are filled in', function () {
    landingSettings(['social_facebook' => 'https://facebook.com/krsons']);

    $this->get('/')
        ->assertOk()
        ->assertSee('https://facebook.com/krsons', false)
        ->assertDontSee('ri-linkedin-fill', false);
});

it('falls back to the firm phones when no landing numbers are given', function () {
    landingSettings(['firm_phone' => '9601263350', 'firm_office_phone' => '07926925755']);

    $this->get('/')
        ->assertOk()
        ->assertSee('Touch to Call')
        ->assertSee('9601263350')
        ->assertSee('07926925755');
});

it('prefers the landing numbers over the firm ones', function () {
    landingSettings([
        'firm_phone' => '9601263350',
        'landing_phones' => "7874655115\n07940300441\n",
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('7874655115')
        ->assertSee('07940300441')
        ->assertDontSee('9601263350');
});

it('strips punctuation out of the tel: link but not the label', function () {
    landingSettings(['landing_phones' => '079-2692 5755']);

    $this->get('/')
        ->assertOk()
        ->assertSee('tel:07926925755', false)
        ->assertSee('079-2692 5755');
});

// --- the settings screen -----------------------------------------------------------

it('offers the landing tab on the appearance screen', function () {
    $this->actingAs($this->admin)->get(route('app-settings.edit'))
        ->assertOk()
        ->assertSee('Landing Page')
        ->assertSee('id="tab-landing"', false)
        ->assertSee('name="landing_rate_purities[]"', false);
});

/**
 * The test that catches a key missing from AppSettingController's only([...])
 * allow-list — which otherwise fails completely silently.
 */
it('persists every landing field through the appearance form', function () {
    $this->actingAs($this->admin)->put(route('app-settings.update'), [
        'app_name' => 'Jewel',
        'media_disk' => 'public',
        'angadiya_columns' => 3,
        'angadiya_slip_height_mm' => 45,
        'hallmark_next_lot_no' => 1,
        'sidebar_user_bg_from' => '#0acf97',
        'sidebar_user_bg_to' => '#39afd1',
        'sidebar_user_text_color' => '#ffffff',

        'landing_enabled' => '1',
        'landing_announcement' => 'Diwali offer',
        'landing_rate_note' => '+GST',
        'landing_phones' => "7874655115\n07926925755",
        'firm_address' => 'Shop No.1, Satellite, Ahmedabad',
        'social_facebook' => 'https://facebook.com/krsons',
        'social_whatsapp' => 'https://wa.me/919601263350',
        'bank_ac_no' => '50200011856421',
        'bank_upi_id' => 'krsonsahd@hdfcbank',
    ])->assertRedirect();

    $settings = AppSetting::current();

    expect($settings->landing_enabled)->toBeTrue()
        ->and($settings->landing_announcement)->toBe('Diwali offer')
        ->and($settings->landing_rate_note)->toBe('+GST')
        ->and($settings->firm_address)->toBe('Shop No.1, Satellite, Ahmedabad')
        ->and($settings->social_facebook)->toBe('https://facebook.com/krsons')
        ->and($settings->social_whatsapp)->toBe('https://wa.me/919601263350')
        ->and($settings->bank_ac_no)->toBe('50200011856421')
        ->and($settings->bank_upi_id)->toBe('krsonsahd@hdfcbank')
        ->and($settings->landingPhones())->toBe(['7874655115', '07926925755']);
});

it('sets and clears show_on_landing from the appearance form', function () {
    $gold22 = Purity::whereRelation('metalType', 'code', 'GOLD')->where('name', '22K')->firstOrFail();
    $gold18 = Purity::whereRelation('metalType', 'code', 'GOLD')->where('name', '18K')->firstOrFail();

    $base = [
        'app_name' => 'Jewel',
        'media_disk' => 'public',
        'angadiya_columns' => 3,
        'angadiya_slip_height_mm' => 45,
        'hallmark_next_lot_no' => 1,
        'sidebar_user_bg_from' => '#0acf97',
        'sidebar_user_bg_to' => '#39afd1',
        'sidebar_user_text_color' => '#ffffff',
    ];

    $this->actingAs($this->admin)->put(route('app-settings.update'), $base + [
        'landing_rate_purities' => ['', (string) $gold22->id],
    ])->assertRedirect();

    expect($gold22->fresh()->show_on_landing)->toBeTrue()
        ->and($gold18->fresh()->show_on_landing)->toBeFalse();

    // Unticking every box must clear them — whereNotIn('id', []) matches no rows,
    // so this is the case that catches a missing `?: [0]`.
    $this->actingAs($this->admin)->put(route('app-settings.update'), $base + [
        'landing_rate_purities' => [''],
    ])->assertRedirect();

    expect($gold22->fresh()->show_on_landing)->toBeFalse();
});

it('leaves the flags alone when the form does not send them', function () {
    $gold22 = Purity::whereRelation('metalType', 'code', 'GOLD')->where('name', '22K')->firstOrFail();
    $gold22->update(['show_on_landing' => true]);

    $this->actingAs($this->admin)->put(route('app-settings.update'), [
        'app_name' => 'Jewel',
        'media_disk' => 'public',
        'angadiya_columns' => 3,
        'angadiya_slip_height_mm' => 45,
        'hallmark_next_lot_no' => 1,
        'sidebar_user_bg_from' => '#0acf97',
        'sidebar_user_bg_to' => '#39afd1',
        'sidebar_user_text_color' => '#ffffff',
    ])->assertRedirect();

    expect($gold22->fresh()->show_on_landing)->toBeTrue();
});

it('rejects a social link that is not a url', function () {
    $this->actingAs($this->admin)->put(route('app-settings.update'), [
        'app_name' => 'Jewel',
        'media_disk' => 'public',
        'angadiya_columns' => 3,
        'angadiya_slip_height_mm' => 45,
        'hallmark_next_lot_no' => 1,
        'sidebar_user_bg_from' => '#0acf97',
        'sidebar_user_bg_to' => '#39afd1',
        'sidebar_user_text_color' => '#ffffff',
        'social_facebook' => 'not a url',
    ])->assertSessionHasErrors('social_facebook');
});

// Sales can read the Appearance screen — app_setting.view comes with MASTER_MODULES —
// but must not be able to publish the shop's bank details.
it('lets sales look at the appearance screen but not save it', function () {
    $sales = User::factory()->create();
    $sales->assignRole('Sales');

    $this->actingAs($sales)->get(route('app-settings.edit'))->assertOk();
    $this->actingAs($sales)->put(route('app-settings.update'), [])->assertForbidden();
});

it('keeps the appearance screen away from a user with no role', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('app-settings.edit'))
        ->assertForbidden();
});
