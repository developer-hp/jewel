<?php

use App\Models\AppSetting;
use App\Models\OrderType;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->sales = User::factory()->create();
    $this->sales->assignRole('Sales');

    $this->supplier = Supplier::create(['name' => 'MILAN GOLD PVT LTD', 'short_name' => 'MGP']);
    $this->type = OrderType::where('name', 'Stock')->firstOrFail();

    // Continue the shop's existing numbering, as the Appearance screen would.
    AppSetting::current()->update(['supplier_order_next_form_no' => 11143]);
});

/**
 * The payload one karigar order posts.
 */
function orderPayload(array $overrides = []): array
{
    return array_merge([
        'supplier_id' => test()->supplier->id,
        'order_type_id' => test()->type->id,
        'order_date' => today()->toDateString(),
        'customer_delivery_date' => today()->addWeeks(2)->toDateString(),
        'followup_date' => today()->addWeek()->toDateString(),
        'description' => 'Stock ms 8173 ni butti ma dimond nakhva',
        'sample_weight' => '1.220',
    ], $overrides);
}

function postSupplierOrder($test, array $overrides = [])
{
    return $test->actingAs($test->admin)->post(route('supplier-orders.store'), orderPayload($overrides));
}

// --- the form --------------------------------------------------------------------

it('issues the form number from the counter and increments it', function () {
    postSupplierOrder($this)->assertRedirect(route('supplier-orders.index'));

    $order = SupplierOrder::firstOrFail();

    expect($order->form_no)->toBe(11143)
        ->and((int) AppSetting::current()->supplier_order_next_form_no)->toBe(11144)
        ->and($order->isReceived())->toBeFalse()
        ->and($order->statusLabel())->toBe('Pending');

    postSupplierOrder($this)->assertRedirect();

    expect(SupplierOrder::orderByDesc('form_no')->first()->form_no)->toBe(11144);
});

it('snapshots the supplier and type names against a later rename', function () {
    postSupplierOrder($this)->assertRedirect();

    $order = SupplierOrder::firstOrFail();

    expect($order->supplier_name)->toBe('MILAN GOLD PVT LTD')
        ->and($order->order_type_name)->toBe('Stock');

    $this->supplier->update(['name' => 'MILAN GOLD LTD']);
    $this->type->update(['name' => 'Stock Work']);

    expect($order->refresh()->supplier_name)->toBe('MILAN GOLD PVT LTD')
        ->and($order->order_type_name)->toBe('Stock');
});

it('stores the order form reference in capitals and accepts anything', function (string $typed, string $stored) {
    postSupplierOrder($this, ['order_form_ref' => $typed])->assertRedirect();

    expect(SupplierOrder::firstOrFail()->order_form_ref)->toBe($stored);
})->with([
    ['cf 160', 'CF 160'],
    ['  cf160  ', 'CF160'],
    // No format rule at all: digits alone are fine.
    ['12345', '12345'],
    ['ce-277/a', 'CE-277/A'],
]);

it('will not promise the goods before the order was placed', function () {
    postSupplierOrder($this, ['customer_delivery_date' => today()->subDay()->toDateString()])
        ->assertSessionHasErrors('customer_delivery_date');

    postSupplierOrder($this, ['followup_date' => today()->subDay()->toDateString()])
        ->assertSessionHasErrors('followup_date');

    expect(SupplierOrder::count())->toBe(0);
});

it('renders the add and edit screens', function () {
    $this->actingAs($this->admin)->get(route('supplier-orders.create'))
        ->assertOk()
        ->assertSee('Form No')
        ->assertSee('11143');

    postSupplierOrder($this)->assertRedirect();

    $this->actingAs($this->admin)->get(route('supplier-orders.edit', SupplierOrder::firstOrFail()))
        ->assertOk()
        ->assertSee('MILAN GOLD PVT LTD');
});

// --- state ------------------------------------------------------------------------

it('reads pending, overdue and received off the dates and the receipt', function () {
    postSupplierOrder($this)->assertRedirect();

    $order = SupplierOrder::firstOrFail();

    expect($order->statusLabel())->toBe('Pending')
        ->and($order->rowClass())->toBe('row-pending');

    // The day to chase it has passed, and it is still out.
    $order->forceFill(['followup_date' => today()->subDay()])->save();

    expect($order->refresh()->isOverdue())->toBeTrue()
        ->and($order->statusLabel())->toBe('Overdue')
        ->and($order->rowClass())->toBe('row-overdue');

    $this->actingAs($this->admin)->post(route('supplier-orders.received', $order))->assertRedirect();

    $order = $order->fresh();

    // Received wins: a returned order is not chased, however old the date.
    expect($order->isReceived())->toBeTrue()
        ->and($order->isOverdue())->toBeFalse()
        ->and($order->statusLabel())->toBe('Received')
        ->and($order->rowClass())->toBe('row-ready');

    $this->actingAs($this->admin)->post(route('supplier-orders.received', $order))->assertSessionHas('error');
});

it('filters the listing by status, supplier and date range', function () {
    $other = Supplier::create(['name' => 'BHAVESH', 'short_name' => 'BHV']);

    postSupplierOrder($this)->assertRedirect();
    postSupplierOrder($this, ['supplier_id' => $other->id])->assertRedirect();

    $received = SupplierOrder::orderBy('form_no')->first();
    $received->markReceived();

    $columns = ['form_no', 'supplier', 'type', 'description', 'order_form_ref'];
    $ask = fn (array $params) => $this->actingAs($this->admin)
        ->getJson(route('supplier-orders.index', dtParams($columns) + $params));

    expect($ask(['status' => 'pending'])->json('recordsTotal'))->toBe(1)
        ->and($ask(['status' => 'received'])->json('recordsTotal'))->toBe(1)
        ->and($ask(['status' => ''])->json('recordsTotal'))->toBe(2)
        ->and($ask(['supplier_id' => $other->id, 'status' => ''])->json('recordsTotal'))->toBe(1)
        ->and($ask(['from' => today()->addDay()->toDateString(), 'status' => ''])->json('recordsTotal'))->toBe(0)
        ->and($ask(['to' => today()->subDay()->toDateString(), 'status' => ''])->json('recordsTotal'))->toBe(0);

    $row = $ask(['status' => 'received'])->json('data.0');

    expect($row)->toHaveKeys(['select', 'form_no', 'supplier', 'type', 'status', 'action'])
        ->and($row['DT_RowClass'])->toBe('row-ready');
});

// --- the scan token and closing by scan ---------------------------------------------

it('gives every order its own opaque token', function () {
    postSupplierOrder($this)->assertRedirect();
    postSupplierOrder($this)->assertRedirect();

    $tokens = SupplierOrder::pluck('scan_token');

    expect($tokens)->toHaveCount(2)
        ->and($tokens->unique())->toHaveCount(2);

    foreach ($tokens as $token) {
        expect(strlen($token))->toBe(32)
            // Nothing about it is a link or an id.
            ->and($token)->not->toContain('http')
            ->and($token)->not->toContain('/');
    }
});

it('deletes the order the scanned token points at, and can put it back', function () {
    postSupplierOrder($this)->assertRedirect();

    $order = SupplierOrder::firstOrFail();

    $response = $this->actingAs($this->admin)
        ->postJson(route('supplier-orders.scan.destroy'), ['token' => $order->scan_token]);

    $response->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('form_no', 11143);

    expect(SupplierOrder::whereKey($order->id)->exists())->toBeFalse()
        // Soft-deleted, which is what makes a wrong scan survivable.
        ->and(SupplierOrder::withTrashed()->whereKey($order->id)->exists())->toBeTrue();

    $this->actingAs($this->admin)->post($response->json('undo_url'))->assertRedirect();

    expect(SupplierOrder::whereKey($order->id)->exists())->toBeTrue();
});

it('refuses a token that matches nothing, and the same token twice', function () {
    postSupplierOrder($this)->assertRedirect();

    $order = SupplierOrder::firstOrFail();

    $this->actingAs($this->admin)
        ->postJson(route('supplier-orders.scan.destroy'), ['token' => 'not-a-real-token'])
        ->assertStatus(422)
        ->assertJsonPath('ok', false);

    expect(SupplierOrder::count())->toBe(1);

    $this->actingAs($this->admin)
        ->postJson(route('supplier-orders.scan.destroy'), ['token' => $order->scan_token])
        ->assertOk();

    // Scanning the same slip again is refused, not an error.
    $this->actingAs($this->admin)
        ->postJson(route('supplier-orders.scan.destroy'), ['token' => $order->scan_token])
        ->assertStatus(422);
});

it('renders the scan screen with the vendored reader', function () {
    $this->actingAs($this->admin)->get(route('supplier-orders.scan'))
        ->assertOk()
        // Vendored, not a CDN — the till must work without the internet.
        ->assertSee(asset('js/html5-qrcode.min.js'), false)
        ->assertSee('scan-reader', false);

    expect(file_exists(public_path('js/html5-qrcode.min.js')))->toBeTrue();
});

it('will not restore something that was never scanned away', function () {
    postSupplierOrder($this)->assertRedirect();

    $this->actingAs($this->admin)
        ->post(route('supplier-orders.scan.restore', SupplierOrder::firstOrFail()->id))
        ->assertSessionHas('error');
});

// --- printing -------------------------------------------------------------------------

it('prints two copies, with the QR only on the office one', function () {
    AppSetting::current()->update(['supplier_order_header' => 'krsons 079-26925755/50']);

    postSupplierOrder($this)->assertRedirect();
    postSupplierOrder($this)->assertRedirect();

    $ids = SupplierOrder::pluck('id')->all();

    foreach ([[$ids[0]], $ids] as $set) {
        $response = $this->actingAs($this->admin)->post(route('supplier-orders.print'), ['ids' => $set]);

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        expect($response->getContent())->toStartWith('%PDF-');
    }

    // Two KARIGAR RECEIPT blocks per order, and one QR — the office copy's.
    $html = view('supplier-orders.print', [
        'orders' => SupplierOrder::whereKey($ids[0])->get(),
        'header' => 'krsons 079-26925755/50',
        'qrMm' => 22,
        'qrCodes' => [$ids[0] => 'data:image/png;base64,QRHERE'],
        'photos' => [$ids[0] => null],
    ])->render();

    expect(substr_count($html, 'KARIGAR RECEIPT'))->toBe(2)
        ->and(substr_count($html, 'QRHERE'))->toBe(1)
        ->and(substr_count($html, '>IN<'))->toBe(1)
        ->and($html)->toContain('krsons 079-26925755/50');
});

// --- permissions --------------------------------------------------------------------

it('lets a sales user raise and print orders but not remove them', function () {
    postSupplierOrder($this)->assertRedirect();

    $order = SupplierOrder::firstOrFail();

    $this->actingAs($this->sales)->get(route('supplier-orders.index'))->assertOk();
    $this->actingAs($this->sales)->get(route('supplier-orders.create'))->assertOk();
    $this->actingAs($this->sales)->post(route('supplier-orders.print'), ['ids' => [$order->id]])->assertOk();

    $this->actingAs($this->sales)->get(route('supplier-orders.edit', $order))->assertForbidden();
    $this->actingAs($this->sales)->delete(route('supplier-orders.destroy', $order))->assertForbidden();

    // The scan screen deletes, so it is gated with deletion.
    $this->actingAs($this->sales)->get(route('supplier-orders.scan'))->assertForbidden();
    $this->actingAs($this->sales)
        ->postJson(route('supplier-orders.scan.destroy'), ['token' => $order->scan_token])
        ->assertForbidden();

    expect(SupplierOrder::count())->toBe(1);
});

it('hides the module from a user with no permissions', function () {
    $none = User::factory()->create();

    $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->assertSee(route('supplier-orders.index'));
    $this->actingAs($none)->get(route('dashboard'))->assertOk()->assertDontSee(route('supplier-orders.index'));
    $this->actingAs($none)->get(route('supplier-orders.index'))->assertForbidden();
});
