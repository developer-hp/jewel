<?php

use App\Models\AppSetting;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\Purity;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('s3');

    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->gold22 = Purity::whereRelation('metalType', 'code', 'GOLD')->where('name', '22K')->firstOrFail();
    $this->ring = ItemGroup::where('prefix', 'RNG')->firstOrFail();
});

function makePhotoItem(?string $prefix = 'RNG'): Item
{
    $group = ItemGroup::where('prefix', $prefix)->firstOrFail();

    test()->actingAs(test()->admin)->post(route('items.store'), [
        'item_group_id' => $group->id,
        'metal_type_id' => test()->gold22->metal_type_id,
        'purity_id' => test()->gold22->id,
        'name' => 'Photo Test',
        'gross_weight' => 10,
        'other_deduction' => 0,
        'is_active' => '1',
    ]);

    return Item::latest('id')->firstOrFail();
}

it('attaches a photo to an item', function () {
    $item = makePhotoItem();

    $this->actingAs($this->admin)
        ->post(route('items.photo.store', $item), ['photo' => UploadedFile::fake()->image('shot.jpg')])
        ->assertRedirect();

    $item->refresh();

    expect($item->hasPhoto())->toBeTrue()
        ->and($item->photo_disk)->toBe('public')
        // Named after the code so a file pulled off the disk is identifiable.
        ->and($item->photo_path)->toStartWith('items/'.$item->code.'-')
        ->and($item->photoUrl())->not->toBeNull();

    Storage::disk('public')->assertExists($item->photo_path);
});

it('replaces the previous photo and deletes the old file', function () {
    $item = makePhotoItem();

    $this->actingAs($this->admin)->post(route('items.photo.store', $item), ['photo' => UploadedFile::fake()->image('one.jpg')]);
    $first = $item->fresh()->photo_path;

    $this->actingAs($this->admin)->post(route('items.photo.store', $item), ['photo' => UploadedFile::fake()->image('two.jpg')]);
    $second = $item->fresh()->photo_path;

    expect($second)->not->toBe($first);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);
});

it('removes a photo', function () {
    $item = makePhotoItem();
    $this->actingAs($this->admin)->post(route('items.photo.store', $item), ['photo' => UploadedFile::fake()->image('shot.jpg')]);
    $path = $item->fresh()->photo_path;

    $this->actingAs($this->admin)->delete(route('items.photo.destroy', $item))->assertRedirect();

    expect($item->fresh()->hasPhoto())->toBeFalse()
        ->and($item->fresh()->photoUrl())->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('rejects a non-image and an oversized photo', function () {
    $item = makePhotoItem();

    $this->actingAs($this->admin)
        ->post(route('items.photo.store', $item), ['photo' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('photo');

    $this->actingAs($this->admin)
        ->post(route('items.photo.store', $item), ['photo' => UploadedFile::fake()->image('big.jpg')->size(5000)])
        ->assertSessionHasErrors('photo');
});

it('writes to the disk chosen in settings', function () {
    AppSetting::current()->update(['media_disk' => 's3']);

    $item = makePhotoItem();

    $this->actingAs($this->admin)->post(route('items.photo.store', $item), ['photo' => UploadedFile::fake()->image('shot.jpg')]);

    expect($item->fresh()->photo_disk)->toBe('s3');
    Storage::disk('s3')->assertExists($item->fresh()->photo_path);
    Storage::disk('public')->assertMissing($item->fresh()->photo_path);
});

it('keeps serving photos from the disk they were written to after a switch', function () {
    $item = makePhotoItem();
    $this->actingAs($this->admin)->post(route('items.photo.store', $item), ['photo' => UploadedFile::fake()->image('shot.jpg')]);

    $path = $item->fresh()->photo_path;

    // Switching the setting must not orphan photos already on the old disk.
    AppSetting::current()->update(['media_disk' => 's3']);

    expect($item->fresh()->photo_disk)->toBe('public')
        ->and($item->fresh()->photoUrl())->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

it('falls back to local when the configured disk is unknown', function () {
    AppSetting::current()->forceFill(['media_disk' => 'dropbox'])->save();

    expect(AppSetting::current()->mediaDisk())->toBe('public');
});

// --- bulk upload -------------------------------------------------------------

it('attaches bulk photos by matching the filename to the item code', function () {
    $ring = makePhotoItem('RNG');
    $necklace = makePhotoItem('NCK');

    $this->actingAs($this->admin)->post(route('items.photos.bulk.store'), [
        'photos' => [
            UploadedFile::fake()->image("{$ring->code}.jpg"),
            UploadedFile::fake()->image("{$necklace->code}.png"),
        ],
    ])->assertRedirect();

    expect($ring->fresh()->hasPhoto())->toBeTrue()
        ->and($necklace->fresh()->hasPhoto())->toBeTrue();
});

it('matches the code regardless of case', function () {
    $item = makePhotoItem();

    $this->actingAs($this->admin)->post(route('items.photos.bulk.store'), [
        'photos' => [UploadedFile::fake()->image(strtolower($item->code).'.jpg')],
    ]);

    expect($item->fresh()->hasPhoto())->toBeTrue();
});

it('reports files that match no item and writes nothing for them', function () {
    $item = makePhotoItem();

    $response = $this->actingAs($this->admin)->post(route('items.photos.bulk.store'), [
        'photos' => [
            UploadedFile::fake()->image("{$item->code}.jpg"),
            UploadedFile::fake()->image('NOSUCH9999.jpg'),
        ],
    ]);

    $result = $response->getSession()->get('bulkPhotoResult');

    expect($result['attached'])->toBe([$item->code])
        ->and($result['skipped'])->toHaveCount(1)
        ->and($result['skipped'][0]['file'])->toBe('NOSUCH9999.jpg')
        ->and($result['skipped'][0]['reason'])->toContain('No item with code');
});

it('separates replacements from first-time attachments', function () {
    $item = makePhotoItem();
    $this->actingAs($this->admin)->post(route('items.photo.store', $item), ['photo' => UploadedFile::fake()->image('first.jpg')]);

    $response = $this->actingAs($this->admin)->post(route('items.photos.bulk.store'), [
        'photos' => [UploadedFile::fake()->image("{$item->code}.jpg")],
        'overwrite_existing' => '1',
    ]);

    $result = $response->getSession()->get('bulkPhotoResult');

    expect($result['replaced'])->toBe([$item->code])
        ->and($result['attached'])->toBe([]);
});

it('leaves existing photos alone when overwrite is off', function () {
    $item = makePhotoItem();
    $this->actingAs($this->admin)->post(route('items.photo.store', $item), ['photo' => UploadedFile::fake()->image('first.jpg')]);
    $original = $item->fresh()->photo_path;

    $response = $this->actingAs($this->admin)->post(route('items.photos.bulk.store'), [
        'photos' => [UploadedFile::fake()->image("{$item->code}.jpg")],
        'overwrite_existing' => '0',
    ]);

    $result = $response->getSession()->get('bulkPhotoResult');

    expect($item->fresh()->photo_path)->toBe($original)
        ->and($result['skipped'][0]['reason'])->toContain('already has a photo');
    Storage::disk('public')->assertExists($original);
});

it('renders the bulk screen with coverage figures', function () {
    makePhotoItem();
    makePhotoItem();

    $this->actingAs($this->admin)->get(route('items.photos.bulk'))
        ->assertOk()
        ->assertSee('Bulk Photo Upload');
});

it('gates photo actions behind item.edit', function () {
    $item = makePhotoItem();

    $sales = User::factory()->create();
    $sales->assignRole('Sales'); // has item.view and item.print, not item.edit

    $this->actingAs($sales)->get(route('items.photos.bulk'))->assertForbidden();
    $this->actingAs($sales)
        ->post(route('items.photo.store', $item), ['photo' => UploadedFile::fake()->image('x.jpg')])
        ->assertForbidden();
    $this->actingAs($sales)->delete(route('items.photo.destroy', $item))->assertForbidden();
});
