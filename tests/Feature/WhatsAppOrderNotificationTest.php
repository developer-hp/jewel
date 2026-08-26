<?php

use App\Jobs\SendWhatsAppTemplate;
use App\Models\AppSetting;
use App\Models\MetalType;
use App\Models\OrderForm;
use App\Models\Purity;
use App\Models\RepairForm;
use App\Models\SalesPerson;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppClient;
use App\Services\WhatsAppTransientException;
use App\Support\WhatsAppEvent;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * The order confirmation.
 *
 * This file posts its own orders rather than reusing OrderFormTest's postOrder():
 * that one is a global function, so it only exists when the whole suite is loaded
 * and this file could not be run on its own. ItemEstimateTest's postEstimate2() is
 * the same compromise under a different name.
 */
function postWhatsAppOrder($test, array $overrides = [])
{
    return $test->actingAs($test->admin)->post(route('order-forms.store'), array_merge([
        'form_date' => today()->toDateString(),
        'delivery_date' => today()->addWeeks(2)->toDateString(),
        'customer_name' => 'NIKHILBHAI PATEL',
        'contact_no' => '9925747799',
        'sales_person_id' => $test->person->id,
        'lines' => [[
            'description' => 'Ring',
            'net_weight' => '5.5',
            'lc_amount' => '350',
            'lc_type' => 'per_gram',
            'oc_amount' => '0',
            'size_pcs' => '14',
        ]],
    ], $overrides));
}

beforeEach(function () {
    // This is the app's only outbound HTTP call, and nothing else in the suite fakes
    // it. Stray requests fail loudly rather than reaching Meta.
    Http::preventStrayRequests();

    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->person = SalesPerson::create(['name' => 'Shilpa Soni']);
    $this->gold = MetalType::where('code', 'GOLD')->firstOrFail();
    $this->purity = Purity::where('metal_type_id', $this->gold->id)->where('name', '22K')->firstOrFail();

    AppSetting::current()->update([
        'order_next_ref_no' => 160,
        'order_ref_prefix' => 'CF',
        'app_name' => 'Shree Jewellers',
    ]);

    config([
        'services.whatsapp.token' => 'test-token',
        'services.whatsapp.phone_number_id' => '1051600000',
        'services.whatsapp.base_url' => 'https://graph.facebook.com',
        'services.whatsapp.api_version' => 'v23.0',
        'services.whatsapp.country_code' => '91',
    ]);
});

/** The configured, switched-on template for the order event. */
function activeOrderTemplate(array $overrides = []): WhatsAppTemplate
{
    return WhatsAppTemplate::create(array_merge([
        'event' => WhatsAppEvent::OrderCreated->value,
        'name' => 'customerorder',
        'language' => 'en',
        'is_active' => true,
    ], $overrides));
}

// --- when it fires ----------------------------------------------------------------

it('queues the message rather than sending it during the save', function () {
    activeOrderTemplate();
    Queue::fake();

    postWhatsAppOrder($this)->assertRedirect();

    Queue::assertPushed(SendWhatsAppTemplate::class, 1);

    Queue::assertPushed(SendWhatsAppTemplate::class, function (SendWhatsAppTemplate $job) {
        return $job->to === '919925747799'
            && $job->template === 'customerorder'
            && $job->language === 'en'
            && $job->header === ['Shree Jewellers']
            && $job->body === ['NIKHILBHAI PATEL', 'CF 160', today()->addWeeks(2)->format('d-m-Y'), 'Shilpa Soni'];
    });
});

it('sends nothing when the event has no template', function () {
    Queue::fake();

    postWhatsAppOrder($this)->assertRedirect();

    Queue::assertNothingPushed();
});

it('sends nothing when the template is switched off', function () {
    activeOrderTemplate(['is_active' => false]);
    Queue::fake();

    postWhatsAppOrder($this)->assertRedirect();

    Queue::assertNothingPushed();
});

it('sends nothing without credentials', function (string $missing) {
    activeOrderTemplate();
    config(["services.whatsapp.{$missing}" => null]);
    Queue::fake();

    postWhatsAppOrder($this)->assertRedirect();

    Queue::assertNothingPushed();
})->with(['token', 'phone_number_id']);

it('skips a number it cannot send to, and still saves the order', function () {
    activeOrderTemplate();
    Queue::fake();

    postWhatsAppOrder($this, ['contact_no' => '12345'])
        ->assertRedirect()
        ->assertSessionHas('success');

    Queue::assertNothingPushed();

    expect(OrderForm::count())->toBe(1);
});

it('normalises however the number was typed', function () {
    activeOrderTemplate();
    Queue::fake();

    postWhatsAppOrder($this, ['contact_no' => '+91 96012 63350'])->assertRedirect();

    Queue::assertPushed(SendWhatsAppTemplate::class,
        fn (SendWhatsAppTemplate $job) => $job->to === '919601263350');
});

it('does not message when an order is edited', function () {
    activeOrderTemplate();
    postWhatsAppOrder($this)->assertRedirect();

    $form = OrderForm::firstOrFail();

    Queue::fake();

    $this->actingAs($this->admin)->put(route('order-forms.update', $form), [
        'form_date' => today()->toDateString(),
        'delivery_date' => today()->addWeeks(2)->toDateString(),
        'customer_name' => 'NIKHILBHAI PATEL',
        'contact_no' => '9925747799',
        'sales_person_id' => $this->person->id,
        'lines' => [[
            'description' => 'Ring',
            'net_weight' => '5.5',
            'lc_amount' => '350',
            'lc_type' => 'per_gram',
            'oc_amount' => '0',
            'size_pcs' => '14',
        ]],
    ])->assertRedirect();

    Queue::assertNothingPushed();
});

// --- what reaches Meta -------------------------------------------------------------

it('posts the template exactly as meta expects it', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.X']]])]);

    $sent = app(WhatsAppClient::class)->sendTemplate(
        '919925747799', 'customerorder', 'en',
        ['Shree Jewellers'],
        ['NIKHILBHAI PATEL', 'CF 160', '09-09-2026', 'Shilpa Soni'],
    );

    expect($sent)->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://graph.facebook.com/v23.0/1051600000/messages'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request->data() === [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => '919925747799',
                'type' => 'template',
                'template' => [
                    'name' => 'customerorder',
                    'language' => ['code' => 'en'],
                    'components' => [
                        ['type' => 'header', 'parameters' => [
                            ['type' => 'text', 'text' => 'Shree Jewellers'],
                        ]],
                        ['type' => 'body', 'parameters' => [
                            ['type' => 'text', 'text' => 'NIKHILBHAI PATEL'],
                            ['type' => 'text', 'text' => 'CF 160'],
                            ['type' => 'text', 'text' => '09-09-2026'],
                            ['type' => 'text', 'text' => 'Shilpa Soni'],
                        ]],
                    ],
                ],
            ];
    });
});

it('leaves the header component out when the template has no header', function () {
    $payload = WhatsAppClient::payload('919925747799', 'plain', 'en', [], ['One']);

    expect($payload['template']['components'])->toHaveCount(1)
        ->and($payload['template']['components'][0]['type'])->toBe('body');
});

// --- when Meta says no --------------------------------------------------------------

it('treats a rejection as an answer, not a crash', function () {
    Http::fake(['graph.facebook.com/*' => Http::response([
        'error' => ['code' => 132001, 'message' => 'Template name does not exist'],
    ], 400)]);

    // A wrong template name fails identically however many times it is tried, so it
    // is logged and swallowed rather than thrown for the job to retry.
    $sent = app(WhatsAppClient::class)->sendTemplate('919925747799', 'wrong', 'en', ['A'], ['B']);

    expect($sent)->toBeFalse();
});

it('throws for a failure worth retrying', function () {
    Http::fake(['graph.facebook.com/*' => Http::response('upstream down', 503)]);

    app(WhatsAppClient::class)->sendTemplate('919925747799', 'customerorder', 'en', ['A'], ['B']);
})->throws(WhatsAppTransientException::class);

it('keeps the order when the send blows up entirely', function () {
    activeOrderTemplate();

    // Not Queue::fake(): the dispatch itself is what must not be able to take the
    // order down with it.
    Queue::shouldReceive('connection')->andThrow(new RuntimeException('queue is gone'));

    postWhatsAppOrder($this)
        ->assertRedirect(route('order-forms.index'))
        ->assertSessionHas('success');

    expect(OrderForm::count())->toBe(1);
});

// --- repairs ---------------------------------------------------------------------

/** The switched-on template for the repair event. */
function activeRepairTemplate(array $overrides = []): WhatsAppTemplate
{
    return WhatsAppTemplate::create(array_merge([
        'event' => WhatsAppEvent::RepairCreated->value,
        'name' => 'customerrepair',
        'language' => 'en',
        'is_active' => true,
    ], $overrides));
}

function postRepair($test, array $overrides = [])
{
    return $test->actingAs($test->admin)->post(route('repair-forms.store'), array_merge([
        'form_date' => today()->toDateString(),
        'delivery_date' => today()->addWeeks(2)->toDateString(),
        'customer_name' => 'MAMTA BEN GOHEL',
        'contact_no' => '9601263350',
        'sales_person_ids' => [$test->person->id],
        'lines' => [['description' => 'GKL SINGLE PCS', 'net_weight' => '12.360']],
    ], $overrides));
}

it('queues the repair confirmation with the repair reference', function () {
    activeRepairTemplate();
    Queue::fake();

    postRepair($this)->assertRedirect();

    Queue::assertPushed(SendWhatsAppTemplate::class, 1);

    Queue::assertPushed(SendWhatsAppTemplate::class, function (SendWhatsAppTemplate $job) {
        return $job->to === '919601263350'
            && $job->template === 'customerrepair'
            && $job->header === ['Shree Jewellers']
            && $job->body === [
                'MAMTA BEN GOHEL',
                'RG '.RepairForm::firstOrFail()->ref_no,
                today()->addWeeks(2)->format('d-m-Y'),
                'Shilpa Soni',
            ];
    });
});

it('lists every sales person a repair was booked against', function () {
    // A repair carries several through a pivot, unlike an order's single snapshot.
    $second = SalesPerson::create(['name' => 'Pankaj']);

    activeRepairTemplate();
    Queue::fake();

    postRepair($this, ['sales_person_ids' => [$this->person->id, $second->id]])->assertRedirect();

    Queue::assertPushed(SendWhatsAppTemplate::class,
        fn (SendWhatsAppTemplate $job) => $job->body[3] === 'Shilpa Soni, Pankaj');
});

it('keeps the order and repair templates independent', function () {
    // Only the order template is on, so booking a repair must send nothing.
    activeOrderTemplate();
    Queue::fake();

    postRepair($this)->assertRedirect();

    Queue::assertNothingPushed();
});
