<?php

use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Support\WhatsAppEvent;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    config([
        'services.whatsapp.token' => 'test-token',
        'services.whatsapp.phone_number_id' => '1051600000',
    ]);
});

/** @return array<string, mixed> */
function templatePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'customerorder',
        'language' => 'en',
        'is_active' => '1',
    ], $overrides);
}

// --- the listing -------------------------------------------------------------------

it('lists every message the app can send, set up or not', function () {
    // Driven by the enum, not the table, so nothing is invisible just because it has
    // never been configured.
    expect(WhatsAppTemplate::count())->toBe(0);

    $this->actingAs($this->admin)->get(route('whatsapp-templates.index'))
        ->assertOk()
        ->assertSee(WhatsAppEvent::OrderCreated->label())
        ->assertSee('Not set up');
});

it('shows a configured message as on or off', function () {
    $template = WhatsAppTemplate::create([
        'event' => WhatsAppEvent::OrderCreated->value,
        'name' => 'customerorder',
        'language' => 'en',
        'is_active' => true,
    ]);

    // One row per event, and only the configured one loses its "Not set up".
    $notSetUp = fn (string $html) => substr_count($html, 'Not set up');
    $events = count(WhatsAppEvent::all());

    $html = $this->actingAs($this->admin)->get(route('whatsapp-templates.index'))
        ->assertOk()
        ->assertSee('customerorder')
        ->assertSee('>On<', false)
        ->getContent();

    expect($notSetUp($html))->toBe($events - 1);

    $template->update(['is_active' => false]);

    $this->actingAs($this->admin)->get(route('whatsapp-templates.index'))
        ->assertOk()
        ->assertSee('customerorder')
        ->assertSee('>Off<', false);
});

it('warns when the env credentials are missing', function () {
    config(['services.whatsapp.token' => null]);

    $this->actingAs($this->admin)->get(route('whatsapp-templates.index'))
        ->assertOk()
        ->assertSee('No credentials');

    config(['services.whatsapp.token' => 'test-token']);

    $this->actingAs($this->admin)->get(route('whatsapp-templates.index'))
        ->assertOk()
        ->assertDontSee('No credentials');
});

it('always warns that a worker has to be running', function () {
    // The single most useful thing on the screen: without a worker nothing sends and
    // nothing says so.
    $this->actingAs($this->admin)->get(route('whatsapp-templates.index'))
        ->assertOk()
        ->assertSee('queue worker');
});

// --- the form ----------------------------------------------------------------------

it('creates the row on first edit, and shows the placeholders', function () {
    expect(WhatsAppTemplate::count())->toBe(0);

    $this->actingAs($this->admin)
        ->get(route('whatsapp-templates.edit', WhatsAppEvent::OrderCreated->value))
        ->assertOk()
        // Numbered as Meta numbers them. Written with the braces split apart because
        // Blade ends an echo at the first "}}" in a string — which is how this page
        // first shipped broken.
        ->assertSee('{'.'{'.'1'.'}'.'}')
        ->assertSee('{'.'{'.'4'.'}'.'}')
        // From the enum, so the shop can register a matching template with Meta.
        ->assertSee('App name')
        ->assertSee('Customer name')
        ->assertSee('Order number')
        ->assertSee('Delivery date')
        ->assertSee('Sales person');

    expect(WhatsAppTemplate::count())->toBe(1)
        ->and(WhatsAppTemplate::first()->is_active)->toBeFalse();
});

it('404s for an event that does not exist', function () {
    $this->actingAs($this->admin)->get(route('whatsapp-templates.edit', 'nonsense'))->assertNotFound();
});

it('saves the template name, language and switch', function () {
    $this->actingAs($this->admin)->put(
        route('whatsapp-templates.update', WhatsAppEvent::OrderCreated->value),
        templatePayload(),
    )->assertRedirect(route('whatsapp-templates.index'));

    $template = WhatsAppTemplate::firstOrFail();

    expect($template->event)->toBe(WhatsAppEvent::OrderCreated)
        ->and($template->name)->toBe('customerorder')
        ->and($template->language)->toBe('en')
        ->and($template->is_active)->toBeTrue()
        ->and(WhatsAppTemplate::count())->toBe(1);
});

it('turns the message off when the switch is unticked', function () {
    $this->actingAs($this->admin)->put(
        route('whatsapp-templates.update', WhatsAppEvent::OrderCreated->value),
        templatePayload(['is_active' => '0']),
    )->assertRedirect();

    expect(WhatsAppTemplate::firstOrFail()->is_active)->toBeFalse();
});

it('rejects a template name Meta would not accept', function (string $name) {
    $this->actingAs($this->admin)->put(
        route('whatsapp-templates.update', WhatsAppEvent::OrderCreated->value),
        templatePayload(['name' => $name]),
    )->assertSessionHasErrors('name');
})->with(['', 'Customer Order', 'customer-order', 'CUSTOMERORDER']);

it('rejects a language code that is not a locale', function () {
    $this->actingAs($this->admin)->put(
        route('whatsapp-templates.update', WhatsAppEvent::OrderCreated->value),
        templatePayload(['language' => 'english']),
    )->assertSessionHasErrors('language');

    $this->actingAs($this->admin)->put(
        route('whatsapp-templates.update', WhatsAppEvent::OrderCreated->value),
        templatePayload(['language' => 'en_US']),
    )->assertSessionHasNoErrors();
});

// --- what makes a template sendable --------------------------------------------------

it('is only sendable when it is on, named and backed by credentials', function () {
    $template = WhatsAppTemplate::create([
        'event' => WhatsAppEvent::OrderCreated->value,
        'name' => 'customerorder',
        'language' => 'en',
        'is_active' => true,
    ]);

    expect($template->isSendable())->toBeTrue()
        ->and(WhatsAppTemplate::activeFor(WhatsAppEvent::OrderCreated))->not->toBeNull();

    $template->update(['is_active' => false]);
    expect(WhatsAppTemplate::activeFor(WhatsAppEvent::OrderCreated))->toBeNull();

    $template->update(['is_active' => true, 'name' => '']);
    expect(WhatsAppTemplate::activeFor(WhatsAppEvent::OrderCreated))->toBeNull();

    $template->update(['name' => 'customerorder']);
    config(['services.whatsapp.token' => null]);
    expect(WhatsAppTemplate::activeFor(WhatsAppEvent::OrderCreated))->toBeNull();
});

// --- permissions -----------------------------------------------------------------------

it('lets sales look but not change', function () {
    $sales = User::factory()->create();
    $sales->assignRole('Sales');

    $this->actingAs($sales)->get(route('whatsapp-templates.index'))->assertOk();
    $this->actingAs($sales)->put(
        route('whatsapp-templates.update', WhatsAppEvent::OrderCreated->value),
        templatePayload(),
    )->assertForbidden();
});

it('hides the screen from a user without the permission', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('whatsapp-templates.index'))
        ->assertForbidden();
});
