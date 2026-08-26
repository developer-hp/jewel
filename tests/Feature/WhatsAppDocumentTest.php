<?php

use App\Jobs\SendWhatsAppTemplate;
use App\Models\Customer;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppClient;
use App\Support\WhatsAppEvent;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Http::preventStrayRequests();
    Storage::fake('public');

    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    config([
        'services.whatsapp.token' => 'test-token',
        'services.whatsapp.phone_number_id' => '1051600000',
        'services.whatsapp.country_code' => '91',
    ]);
});

function activeDocumentTemplate(array $overrides = []): WhatsAppTemplate
{
    return WhatsAppTemplate::create(array_merge([
        'event' => WhatsAppEvent::DocumentSent->value,
        'name' => 'file_message',
        'language' => 'en',
        'is_active' => true,
    ], $overrides));
}

function sendDocument($test, array $overrides = [])
{
    return $test->actingAs($test->admin)->post(route('whatsapp-documents.send'), array_merge([
        'contact_no' => '9601263350',
        'customer_name' => 'RAVIBHAI',
        'described_as' => 'Ledger Report',
        'document' => UploadedFile::fake()->create('ledger august.pdf', 40, 'application/pdf'),
    ], $overrides));
}

// --- the screen ---------------------------------------------------------------------

it('renders the send screen', function () {
    activeDocumentTemplate();

    $this->actingAs($this->admin)->get(route('whatsapp-documents.create'))
        ->assertOk()
        ->assertSee('Send Document')
        ->assertSee(route('customers.search'), false);
});

it('says so when the message is not switched on', function () {
    $this->actingAs($this->admin)->get(route('whatsapp-documents.create'))
        ->assertOk()
        ->assertSee('is not switched on');
});

// --- sending ---------------------------------------------------------------------------

it('queues the document with a link meta can fetch', function () {
    activeDocumentTemplate();
    Queue::fake();

    sendDocument($this)->assertRedirect()->assertSessionHas('success');

    Queue::assertPushed(SendWhatsAppTemplate::class, function (SendWhatsAppTemplate $job) {
        return $job->to === '919601263350'
            && $job->template === 'file_message'
            // The header is the PDF, so there are no text header parameters.
            && $job->header === []
            && $job->body === ['RAVIBHAI', 'Ledger Report']
            // Absolute, rooted at the app: Meta fetches this from its own network,
            // so the relative path the local disk hands back is no use.
            && str_starts_with($job->document['link'], url('/storage/whatsapp-documents/'))
            && str_starts_with($job->document['link'], 'http')
            // The name the customer sees is the one they were sent, tidied.
            && $job->document['filename'] === 'ledger august.pdf';
    });
});

it('stores the pdf under an unguessable name', function () {
    activeDocumentTemplate();
    Queue::fake();

    sendDocument($this)->assertRedirect();

    $stored = Storage::disk('public')->files('whatsapp-documents');

    // Anyone with the link can read it, so the name must not be derivable from
    // anything the customer or the clerk typed.
    expect($stored)->toHaveCount(1)
        ->and($stored[0])->not->toContain('ledger')
        ->and($stored[0])->toEndWith('.pdf');
});

it('takes the number and name from a chosen customer', function () {
    activeDocumentTemplate();
    Queue::fake();

    $customer = Customer::create([
        'name' => 'CHETANBHAI DOBARIYA',
        'phone' => '9825143759',
        'is_active' => true,
    ]);

    sendDocument($this, ['customer_id' => $customer->id, 'contact_no' => '', 'customer_name' => ''])
        ->assertRedirect();

    Queue::assertPushed(SendWhatsAppTemplate::class, function (SendWhatsAppTemplate $job) {
        return $job->to === '919825143759' && $job->body[0] === 'CHETANBHAI DOBARIYA';
    });
});

// --- what it refuses ---------------------------------------------------------------------

it('needs somebody to send to', function () {
    activeDocumentTemplate();
    Queue::fake();

    sendDocument($this, ['contact_no' => '', 'customer_id' => ''])
        ->assertSessionHasErrors('contact_no');

    Queue::assertNothingPushed();
});

it('needs a description and a pdf', function () {
    activeDocumentTemplate();

    sendDocument($this, ['described_as' => ''])->assertSessionHasErrors('described_as');

    sendDocument($this, ['document' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain')])
        ->assertSessionHasErrors('document');
});

it('refuses a number it cannot send to, without storing the file', function () {
    activeDocumentTemplate();
    Queue::fake();

    sendDocument($this, ['contact_no' => '12345'])
        ->assertRedirect()
        ->assertSessionHas('error');

    Queue::assertNothingPushed();

    // Nothing publicly readable is left behind for a message that never went.
    expect(Storage::disk('public')->files('whatsapp-documents'))->toBeEmpty();
});

it('refuses when the message is switched off', function () {
    activeDocumentTemplate(['is_active' => false]);
    Queue::fake();

    sendDocument($this)->assertRedirect()->assertSessionHas('error');

    Queue::assertNothingPushed();
    expect(Storage::disk('public')->files('whatsapp-documents'))->toBeEmpty();
});

// --- the payload -----------------------------------------------------------------------

it('sends the document as the header component', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.X']]])]);

    app(WhatsAppClient::class)->sendTemplate(
        '919601263350', 'file_message', 'en', [], ['RAVIBHAI', 'Ledger Report'],
        ['link' => 'https://jewel.example.com/storage/whatsapp-documents/abc.pdf', 'filename' => 'ledger.pdf'],
    );

    Http::assertSent(fn ($request) => $request->data()['template']['components'] === [
        ['type' => 'header', 'parameters' => [[
            'type' => 'document',
            'document' => [
                'link' => 'https://jewel.example.com/storage/whatsapp-documents/abc.pdf',
                'filename' => 'ledger.pdf',
            ],
        ]]],
        ['type' => 'body', 'parameters' => [
            ['type' => 'text', 'text' => 'RAVIBHAI'],
            ['type' => 'text', 'text' => 'Ledger Report'],
        ]],
    ]);
});

// --- permissions -------------------------------------------------------------------------

it('lets sales look but not send', function () {
    $sales = User::factory()->create();
    $sales->assignRole('Sales');

    activeDocumentTemplate();

    $this->actingAs($sales)->get(route('whatsapp-documents.create'))->assertOk();
    $this->actingAs($sales)->post(route('whatsapp-documents.send'), [
        'contact_no' => '9601263350',
        'described_as' => 'Ledger',
        'document' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
    ])->assertForbidden();
});
