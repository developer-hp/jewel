<?php

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\RepairForm;
use App\Models\SalesPerson;
use App\Models\User;
use App\Services\ItemPhotoStore;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->sales = User::factory()->create();
    $this->sales->assignRole('Sales');

    $this->person = SalesPerson::create(['name' => 'Shilpa Soni', 'city' => 'Ahmedabad']);
    $this->person2 = SalesPerson::create(['name' => 'Pankaj']);

    $this->gold = MetalType::where('code', 'GOLD')->firstOrFail();
    $this->purity = Purity::where('metal_type_id', $this->gold->id)->where('name', '22K')->firstOrFail();

    // Continue the shop's existing numbering, as the Appearance screen would.
    AppSetting::current()->update(['repair_next_ref_no' => 205, 'repair_ref_prefix' => 'RG']);
});

/**
 * A saved repair form with the given line descriptions.
 *
 * @param  array<int, string>  $descriptions
 */
function repairForm(array $descriptions = ['GKL SINGLE PCS REPAIR KARVI'], array $overrides = []): RepairForm
{
    $form = new RepairForm(array_merge([
        'form_date' => today()->toDateString(),
        'delivery_date' => today()->addWeeks(2)->toDateString(),
        'customer_name' => 'MAMTA BEN GOHEL',
        'contact_no' => '8291711357',
    ], $overrides));

    // ref_no is NOT NULL, so it has to be there on the first insert.
    $form->ref_no = RepairForm::nextRefNo();
    $form->save();

    foreach ($descriptions as $i => $description) {
        $form->lines()->create(['description' => $description, 'net_weight' => 12.360, 'sort_order' => $i]);
    }

    return $form->refresh();
}

/**
 * Book a repaired piece back into stock against a line.
 */
function bookRepairItem($test, $line, array $overrides = [])
{
    return $test->actingAs($test->admin)->post(route('repair-items.store'), array_merge([
        'repair_form_line_id' => $line->id,
        'metal_type_id' => $test->gold->id,
        'purity_id' => $test->purity->id,
        'gross_weight' => '12.360',
        'net_weight' => '12.360',
        'name' => 'Repaired chain',
    ], $overrides));
}

// --- reserved item groups --------------------------------------------------------

it('seeds the reserved repair and order groups', function () {
    $repair = ItemGroup::system(ItemGroup::SYSTEM_REPAIR);
    $order = ItemGroup::system(ItemGroup::SYSTEM_ORDER);

    expect($repair->prefix)->toBe('REPAIR')
        ->and($repair->previewNextCode())->toBe('REPAIR0001')
        ->and($repair->isReserved())->toBeTrue()
        ->and($order->previewNextCode())->toBe('ORDER0001')
        ->and($order->isReserved())->toBeTrue();

    // Re-running must not create a second copy of either.
    $this->seed(MasterDataSeeder::class);

    expect(ItemGroup::whereNotNull('system_key')->count())->toBe(2);
});

it('refuses to delete a reserved group but still allows a prefix change', function () {
    $repair = ItemGroup::system(ItemGroup::SYSTEM_REPAIR);

    $this->actingAs($this->admin)->delete(route('item-groups.destroy', $repair))->assertSessionHas('error');

    expect(ItemGroup::whereKey($repair->id)->exists())->toBeTrue();

    $this->actingAs($this->admin)->put(route('item-groups.update', $repair), [
        'name' => 'Repair',
        'prefix' => 'RPR',
        'code_padding' => 4,
        'is_active' => '1',
    ])->assertRedirect();

    expect($repair->refresh()->prefix)->toBe('RPR')
        ->and($repair->system_key)->toBe(ItemGroup::SYSTEM_REPAIR);
});

// --- the form --------------------------------------------------------------------

it('issues the reference from the counter and increments it', function () {
    $this->actingAs($this->admin)->post(route('repair-forms.store'), [
        'form_date' => today()->toDateString(),
        'delivery_date' => today()->addWeeks(2)->toDateString(),
        'customer_name' => 'MAMTA BEN GOHEL',
        'contact_no' => '8291711357',
        'sales_person_ids' => [$this->person->id, $this->person2->id],
        'lines' => [['description' => 'GKL SINGLE PCS', 'net_weight' => '12.360']],
    ])->assertRedirect(route('repair-forms.index'));

    $form = RepairForm::firstOrFail();

    expect($form->ref_no)->toBe(205)
        ->and($form->reference())->toBe('RG 205')
        ->and($form->lines)->toHaveCount(1)
        ->and($form->salesPersons->pluck('name')->all())->toBe(['Shilpa Soni', 'Pankaj'])
        ->and((int) AppSetting::current()->repair_next_ref_no)->toBe(206)
        ->and($form->isReady())->toBeFalse();
});

it('snapshots the sales person names against a later rename', function () {
    $form = repairForm();
    $form->salesPersons()->create(['sales_person_id' => $this->person->id, 'name' => $this->person->name]);

    $this->person->update(['name' => 'Shilpa S.']);

    expect($form->refresh()->salesPersons->first()->name)->toBe('Shilpa Soni');
});

it('drops blank lines and requires at least one', function () {
    $this->actingAs($this->admin)->post(route('repair-forms.store'), [
        'form_date' => today()->toDateString(),
        'delivery_date' => today()->addWeek()->toDateString(),
        'customer_name' => 'Test',
        'contact_no' => '900',
        'sales_person_ids' => [$this->person->id],
        'lines' => [
            ['description' => 'CHAIN CLASP', 'net_weight' => '4.2'],
            ['description' => '', 'net_weight' => ''],
        ],
    ])->assertRedirect();

    expect(RepairForm::firstOrFail()->lines)->toHaveCount(1);

    $this->actingAs($this->admin)->post(route('repair-forms.store'), [
        'form_date' => today()->toDateString(),
        'delivery_date' => today()->addWeek()->toDateString(),
        'customer_name' => 'Test',
        'contact_no' => '900',
        'sales_person_ids' => [$this->person->id],
        'lines' => [['description' => '', 'net_weight' => '']],
    ])->assertSessionHasErrors('lines');
});

it('keeps a line whose piece is already back when the form is edited', function () {
    $form = repairForm(['A', 'B']);
    [$first, $second] = $form->lines->all();

    bookRepairItem($this, $first)->assertRedirect();

    // Post only the second line — the first must survive, since an item points at it.
    $this->actingAs($this->admin)->put(route('repair-forms.update', $form), [
        'form_date' => $form->form_date->toDateString(),
        'delivery_date' => $form->delivery_date->toDateString(),
        'customer_name' => 'MAMTA BEN GOHEL',
        'contact_no' => '8291711357',
        'sales_person_ids' => [$this->person->id],
        'lines' => [['id' => $second->id, 'description' => 'B changed', 'net_weight' => '5']],
    ])->assertRedirect();

    $form = $form->fresh();

    expect($form->lines)->toHaveCount(2)
        ->and($form->lines->pluck('description')->all())->toContain('A', 'B changed')
        ->and(Item::whereNotNull('repair_form_line_id')->count())->toBe(1);
});

it('will not delete a form whose pieces are already in stock', function () {
    $form = repairForm();
    bookRepairItem($this, $form->lines->first())->assertRedirect();

    $this->actingAs($this->admin)->delete(route('repair-forms.destroy', $form))->assertSessionHas('error');

    expect(RepairForm::whereKey($form->id)->exists())->toBeTrue();
});

it('renders the add and edit screens', function () {
    $this->actingAs($this->admin)->get(route('repair-forms.create'))
        ->assertOk()
        ->assertSee('Ref No')
        ->assertSee('Delivery Date');

    $form = repairForm(['A', 'B']);
    bookRepairItem($this, $form->lines->first())->assertRedirect();

    $this->actingAs($this->admin)->get(route('repair-forms.edit', $form))
        ->assertOk()
        ->assertSee('REPAIR0001')
        ->assertSee('B');
});

// --- the customer register ---------------------------------------------------------

it('adds the customer on first contact and links the form to them', function () {
    $this->actingAs($this->admin)->post(route('repair-forms.store'), [
        'form_date' => today()->toDateString(),
        'delivery_date' => today()->addWeeks(2)->toDateString(),
        'customer_name' => 'MAMTA BEN GOHEL',
        'contact_no' => '8291 711357',
        'address' => 'Naranpura, Ahmedabad',
        'sales_person_ids' => [$this->person->id],
        'lines' => [['description' => 'GKL SINGLE PCS', 'net_weight' => '12.360']],
    ])->assertRedirect();

    $customer = Customer::firstOrFail();

    expect($customer->name)->toBe('MAMTA BEN GOHEL')
        ->and($customer->phone_key)->toBe('8291711357')
        ->and($customer->address)->toBe('Naranpura, Ahmedabad')
        ->and(RepairForm::firstOrFail()->customer_id)->toBe($customer->id);
});

it('reuses the existing customer for a number already on the register', function () {
    $existing = Customer::create(['name' => 'Mamta Ben', 'phone' => '8291711357', 'address' => 'Naranpura']);

    // Same person, number punctuated differently and the name typed differently.
    $this->actingAs($this->admin)->post(route('repair-forms.store'), [
        'form_date' => today()->toDateString(),
        'delivery_date' => today()->addWeeks(2)->toDateString(),
        'customer_name' => 'MAMTA B GOHEL',
        'contact_no' => '829-171-1357',
        'sales_person_ids' => [$this->person->id],
        'lines' => [['description' => 'A', 'net_weight' => '1']],
    ])->assertRedirect();

    $form = RepairForm::firstOrFail();

    expect(Customer::count())->toBe(1)
        ->and($form->customer_id)->toBe($existing->id)
        // The register is left as it stood; the form keeps what was typed on it.
        ->and($existing->refresh()->name)->toBe('Mamta Ben')
        ->and($form->customer_name)->toBe('MAMTA B GOHEL');
});

it('re-links the form when a mistyped number is corrected', function () {
    $form = repairForm();
    $wrong = Customer::rememberByPhone('8291711357', 'MAMTA BEN GOHEL');
    $form->forceFill(['customer_id' => $wrong->id])->save();

    $this->actingAs($this->admin)->put(route('repair-forms.update', $form), [
        'form_date' => $form->form_date->toDateString(),
        'delivery_date' => $form->delivery_date->toDateString(),
        'customer_name' => 'MAMTA BEN GOHEL',
        'contact_no' => '9998887777',
        'sales_person_ids' => [$this->person->id],
        'lines' => [['id' => $form->lines->first()->id, 'description' => 'A', 'net_weight' => '1']],
    ])->assertRedirect();

    expect(Customer::count())->toBe(2)
        ->and($form->fresh()->customer_id)->toBe(Customer::findByPhone('9998887777')->id);
});

it('deleting a customer leaves the form and its printed details intact', function () {
    $form = repairForm();
    Customer::rememberByPhone($form->contact_no, $form->customer_name);
    $form->forceFill(['customer_id' => Customer::findByPhone($form->contact_no)->id])->save();

    Customer::findByPhone($form->contact_no)->delete();

    $form = $form->fresh();

    expect($form->customer_name)->toBe('MAMTA BEN GOHEL')
        ->and($form->contact_no)->toBe('8291711357');
});

// --- ready ------------------------------------------------------------------------

it('turns ready only once every piece is back in stock', function () {
    $form = repairForm(['A', 'B']);
    [$first, $second] = $form->lines->all();

    expect($form->isReady())->toBeFalse();

    bookRepairItem($this, $first)->assertRedirect();
    expect($form->fresh()->isReady())->toBeFalse()
        ->and($form->fresh()->readyLineCount())->toBe(1);

    bookRepairItem($this, $second, ['name' => 'Second piece'])->assertRedirect();
    expect($form->fresh()->isReady())->toBeTrue()
        ->and($form->fresh()->statusLabel())->toBe('Ready');
});

it('counts and filters ready against pending in the listing', function () {
    $pending = repairForm(['A', 'B']);
    $ready = repairForm(['C']);
    bookRepairItem($this, $ready->lines->first())->assertRedirect();

    $columns = ['ref', 'customer', 'contact', 'remarks'];

    $all = $this->actingAs($this->admin)->getJson(route('repair-forms.index', dtParams($columns)));
    $all->assertOk();

    expect($all->json('recordsTotal'))->toBe(2)
        ->and($all->json('data.0'))->toHaveKeys(['select', 'ref', 'customer', 'progress', 'action']);

    $rows = collect($all->json('data'))->keyBy('ref');

    expect($rows->keys()->contains(fn ($k) => str_contains($k, (string) $ready->ref_no)))->toBeTrue();

    $onlyReady = $this->actingAs($this->admin)
        ->getJson(route('repair-forms.index', dtParams($columns) + ['status' => 'ready']));
    $onlyPending = $this->actingAs($this->admin)
        ->getJson(route('repair-forms.index', dtParams($columns) + ['status' => 'pending']));

    expect($onlyReady->json('recordsTotal'))->toBe(1)
        ->and($onlyReady->json('data.0.progress'))->toBe('1 / 1')
        ->and($onlyPending->json('recordsTotal'))->toBe(1)
        ->and($onlyPending->json('data.0.progress'))->toBe('0 / 2')
        ->and($onlyPending->json('data.0.DT_RowClass'))->toBe('row-pending')
        ->and($onlyReady->json('data.0.DT_RowClass'))->toBe('row-ready')
        ->and($pending->fresh()->isReady())->toBeFalse();
});

// --- booking a repaired piece back in ---------------------------------------------

it('books a repaired piece into stock under the reserved repair group', function () {
    $form = repairForm();
    $line = $form->lines->first();

    $this->actingAs($this->admin)->get(route('repair-items.create'))->assertOk()->assertSee('RG 205');

    bookRepairItem($this, $line, ['gross_weight' => '12.360', 'net_weight' => '12.100'])->assertRedirect();

    $item = Item::firstOrFail();

    expect($item->code)->toBe('REPAIR0001')
        ->and($item->item_group_id)->toBe(ItemGroup::system(ItemGroup::SYSTEM_REPAIR)->id)
        ->and($item->repair_form_line_id)->toBe($line->id)
        // The net that was typed is expressed as what the piece lost, and the
        // calculator lands back on exactly that figure.
        ->and((float) $item->other_deduction)->toBe(0.26)
        ->and((float) $item->net_weight)->toBe(12.1);
});

it('will not let two pieces claim the same line', function () {
    $form = repairForm();
    $line = $form->lines->first();

    bookRepairItem($this, $line)->assertRedirect();
    bookRepairItem($this, $line)->assertSessionHasErrors('repair_form_line_id');

    expect(Item::count())->toBe(1);
});

it('rejects a net weight above the gross', function () {
    $form = repairForm();

    bookRepairItem($this, $form->lines->first(), ['gross_weight' => '5', 'net_weight' => '6'])
        ->assertSessionHasErrors('net_weight');

    expect(Item::count())->toBe(0);
});

it('offers only forms that still have a piece out', function () {
    $form = repairForm();

    $this->actingAs($this->admin)->get(route('repair-items.create'))->assertOk()->assertSee('RG 205');

    bookRepairItem($this, $form->lines->first())->assertRedirect();

    $this->actingAs($this->admin)->get(route('repair-items.create'))
        ->assertOk()
        ->assertSee('Nothing is out for repair')
        // The order-number select is gone entirely, not merely missing that option.
        ->assertDontSee('id="repair_form"', false);
});

it('leaves the ordinary item screen alone', function () {
    $group = ItemGroup::where('prefix', 'RNG')->firstOrFail();

    $this->actingAs($this->admin)->post(route('items.store'), [
        'item_group_id' => $group->id,
        'metal_type_id' => $this->gold->id,
        'purity_id' => $this->purity->id,
        'name' => 'Plain ring',
        'gross_weight' => '5.500',
        'other_deduction' => 0,
        'is_active' => '1',
    ])->assertRedirect(route('items.index'));

    $item = Item::firstOrFail();

    expect($item->code)->toBe('RNG0001')
        ->and($item->repair_form_line_id)->toBeNull();
});

// --- printing ---------------------------------------------------------------------

it('prints the form and the sticker, one and several', function () {
    AppSetting::current()->update([
        'firm_phone' => '9712406367',
        'firm_office_phone' => '+91-7874655115',
        'firm_website' => 'http://example.com',
        'repair_terms' => "WE USE ONLY 916 GOLD FOR REPAIRING\nPOLISH LOSS OF WEIGHT ARE TO BE BORNE BY CUSTOMERS",
    ]);

    $one = repairForm(['A']);
    $one->salesPersons()->create(['sales_person_id' => $this->person->id, 'name' => $this->person->name]);
    $two = repairForm(['B', 'C']);

    foreach ([route('repair-forms.print'), route('repair-forms.stickers')] as $url) {
        foreach ([[$one->id], [$one->id, $two->id]] as $ids) {
            $response = $this->actingAs($this->admin)->post($url, ['ids' => $ids]);

            $response->assertOk()->assertHeader('content-type', 'application/pdf');
            expect($response->getContent())->toStartWith('%PDF-');
        }
    }
});

it('prints the photo when the form has one, and prints fine when it does not', function () {
    Storage::fake('public');

    $form = repairForm(['A']);

    expect($form->photoDataUri())->toBeNull();

    // Printing must not fall over on a form with no picture.
    $this->actingAs($this->admin)->post(route('repair-forms.print'), ['ids' => [$form->id]])->assertOk();

    app(ItemPhotoStore::class)->put($form, UploadedFile::fake()->image('bangle.jpg', 40, 40));

    $form = $form->fresh();

    expect($form->hasPhoto())->toBeTrue()
        ->and($form->photoDataUri())->toStartWith('data:image/');

    $response = $this->actingAs($this->admin)->post(route('repair-forms.print'), ['ids' => [$form->id]]);

    $response->assertOk();
    expect($response->getContent())->toStartWith('%PDF-');
});

it('prints without the photo when its file has gone missing', function () {
    Storage::fake('public');

    $form = repairForm(['A']);
    $form->forceFill(['photo_path' => 'repair-forms/gone.jpg', 'photo_disk' => 'public'])->save();

    expect($form->hasPhoto())->toBeTrue()
        ->and($form->photoDataUri())->toBeNull();

    $this->actingAs($this->admin)->post(route('repair-forms.print'), ['ids' => [$form->id]])->assertOk();
});

// --- permissions --------------------------------------------------------------------

it('lets a sales user take repairs in and print but not edit or delete', function () {
    $form = repairForm();

    $this->actingAs($this->sales)->get(route('repair-forms.index'))->assertOk();
    $this->actingAs($this->sales)->get(route('repair-forms.create'))->assertOk();
    $this->actingAs($this->sales)->post(route('repair-forms.print'), ['ids' => [$form->id]])->assertOk();
    $this->actingAs($this->sales)->post(route('repair-forms.stickers'), ['ids' => [$form->id]])->assertOk();

    $this->actingAs($this->sales)->get(route('repair-forms.edit', $form))->assertForbidden();
    $this->actingAs($this->sales)->delete(route('repair-forms.destroy', $form))->assertForbidden();
    $this->actingAs($this->sales)->get(route('repair-items.create'))->assertForbidden();
});

it('hides the module from a user with no permissions', function () {
    $none = User::factory()->create();

    $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->assertSee(route('repair-forms.index'));
    $this->actingAs($none)->get(route('dashboard'))->assertOk()->assertDontSee(route('repair-forms.index'));
    $this->actingAs($none)->get(route('repair-forms.index'))->assertForbidden();
});
