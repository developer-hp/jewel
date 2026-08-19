<?php

use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\LabelSetting;
use App\Models\Purity;
use App\Models\StoneMaster;
use App\Models\User;
use App\Services\ItemCalculator;
use App\Services\ItemLabelBuilder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->gold22 = Purity::whereRelation('metalType', 'code', 'GOLD')->where('name', '22K')->firstOrFail();
    $this->builder = new ItemLabelBuilder;
});

/**
 * @param  array<int, array<string, mixed>>  $stones
 */
function makeLabelItem(array $attributes = [], array $stones = []): Item
{
    $calc = new ItemCalculator;
    $gold22 = test()->gold22;

    return DB::transaction(function () use ($attributes, $stones, $calc, $gold22) {
        $group = ItemGroup::where('prefix', 'NCK')->firstOrFail();

        $item = new Item(array_merge([
            'item_group_id' => $group->id,
            'metal_type_id' => $gold22->metal_type_id,
            'purity_id' => $gold22->id,
            'name' => 'Label Test',
            'gross_weight' => 114.180,
            'other_deduction' => 0,
        ], $attributes));

        $item->code = $group->nextItemCode();
        $item->net_weight = $item->gross_weight;
        $item->save();

        if ($stones !== []) {
            $masters = StoneMaster::whereIn('id', array_column($stones, 'stone_master_id'))->get()->keyBy('id');
            $item->itemStones()->createMany($calc->buildStoneRows($stones, $masters));
        }

        $calc->recalculate($item);

        return $item->fresh();
    });
}

/** Highest /Count in the PDF's page tree. */
function pdfPageCount(string $pdf): int
{
    preg_match_all('#/Count\s+(\d+)#', $pdf, $matches);

    return $matches[1] ? max(array_map('intval', $matches[1])) : 0;
}

it('streams the tag as a pdf sized to the configured stock', function () {
    $item = makeLabelItem();

    $response = $this->actingAs($this->admin)->get(route('items.label', $item));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');

    $pdf = $response->getContent();

    // 110 x 18 mm in points.
    expect($pdf)->toStartWith('%PDF')
        ->and($pdf)->toContain('MediaBox [0.000 0.000 311.810 51.020]')
        ->and(pdfPageCount($pdf))->toBe(1);
});

it('follows the tag size from settings', function () {
    LabelSetting::current()->update(['tag_width_mm' => 80, 'tag_height_mm' => 25]);

    $pdf = $this->actingAs($this->admin)
        ->get(route('items.label', makeLabelItem()))
        ->getContent();

    // 80 mm = 226.77 pt, 25 mm = 70.87 pt
    expect($pdf)->toContain('MediaBox [0.000 0.000 226.770 70.870]');
});

it('builds the rows the sample tag calls for', function () {
    $ruby = StoneMaster::where('name', 'Ruby')->firstOrFail();
    $diamond = StoneMaster::where('name', 'Round Brilliant SI')->firstOrFail();

    $item = makeLabelItem(
        ['extra_charge_1' => 1200, 'extra_charge_1_label' => 'Polish'],
        [
            ['stone_master_id' => $ruby->id, 'pieces' => 147, 'weight_carat' => 11.5, 'deduct_from_gross' => true],
            ['stone_master_id' => $diamond->id, 'pieces' => 12, 'weight_carat' => 4.4, 'deduct_from_gross' => true],
        ]
    );

    $rows = collect($this->builder->build($item)['rows'])->pluck('value', 'label');

    expect($rows['GW'])->toBe('114.180')
        ->and($rows['NW'])->toBe('111.000')
        ->and($rows['PUR'])->toBe('22K')
        ->and($rows['ST'])->toBe('11.500 ct')
        ->and($rows['DI'])->toBe('4.400 ct')
        // 11.5 ct x 1200 + 4.4 ct x 32000
        ->and($rows['STAMT'])->toBe('154,600')
        ->and($rows['Polish'])->toBe('1,200');
});

it('collapses rows that have no value', function () {
    // Plain gold band: no stones, no extra charges.
    $rows = collect($this->builder->build(makeLabelItem(['gross_weight' => 5.25]))['rows'])->pluck('label');

    expect($rows->all())->toBe(['GW', 'NW', 'PUR'])
        ->and($rows)->not->toContain('ST')
        ->and($rows)->not->toContain('STAMT');
});

it('shows a piece-counted stone bucket as pieces, not carat', function () {
    $pearl = StoneMaster::where('name', 'Pearl')->firstOrFail(); // piece-rated

    $item = makeLabelItem([], [
        ['stone_master_id' => $pearl->id, 'pieces' => 24, 'weight_carat' => 0, 'deduct_from_gross' => false],
    ]);

    $rows = collect($this->builder->build($item)['rows'])->pluck('value', 'label');

    expect($rows['ST'])->toBe('24 pc');
});

it('falls back to E1 and E2 when a charge has no caption', function () {
    $item = makeLabelItem(['extra_charge_1' => 500, 'extra_charge_2' => 250]);

    $rows = collect($this->builder->build($item)['rows'])->pluck('value', 'label');

    expect($rows['E1'])->toBe('500')
        ->and($rows['E2'])->toBe('250');
});

it('omits an extra charge left at zero', function () {
    $item = makeLabelItem(['extra_charge_1' => 500, 'extra_charge_2' => 0]);

    expect($item->extraChargeLines())->toHaveCount(1)
        ->and($item->extraChargeLines()[0]['label'])->toBe('E1');
});

it('honours the visible-field switches', function () {
    LabelSetting::current()->update([
        'show_purity' => false,
        'show_gross' => false,
        'show_extra_charges' => false,
    ]);

    $item = makeLabelItem(['extra_charge_1' => 1200, 'extra_charge_1_label' => 'Polish']);

    $labels = collect($this->builder->build($item)['rows'])->pluck('label');

    expect($labels)->not->toContain('PUR')
        ->and($labels)->not->toContain('GW')
        ->and($labels)->not->toContain('Polish')
        ->and($labels)->toContain('NW');
});

it('omits the qr until it is switched on', function () {
    $item = makeLabelItem();

    expect($this->builder->build($item)['qr'])->toBeNull();

    LabelSetting::current()->update(['qr_enabled' => true]);

    expect($this->builder->build($item->fresh())['qr'])->toStartWith('data:image/png;base64,');
});

it('encodes the item code or the item url as configured', function () {
    $item = makeLabelItem();
    $settings = LabelSetting::current();

    $settings->update(['qr_enabled' => true, 'qr_content' => 'item_code']);
    $byCode = $this->builder->build($item)['qr'];

    $settings->fresh()->update(['qr_content' => 'item_url']);
    $byUrl = $this->builder->build($item)['qr'];

    // Different payloads must produce different images.
    expect($byCode)->not->toBe($byUrl);
});

it('keeps the tag on one page with the qr enabled', function () {
    LabelSetting::current()->update(['qr_enabled' => true, 'qr_size_mm' => 14, 'show_shop_name' => true, 'shop_name' => 'Shree Jewellers']);

    $ruby = StoneMaster::where('name', 'Ruby')->firstOrFail();
    $item = makeLabelItem(
        ['extra_charge_1' => 1200, 'extra_charge_1_label' => 'Polish', 'extra_charge_2' => 300, 'extra_charge_2_label' => 'Cert'],
        [['stone_master_id' => $ruby->id, 'pieces' => 10, 'weight_carat' => 5, 'deduct_from_gross' => true]]
    );

    $pdf = $this->actingAs($this->admin)->get(route('items.label', $item))->getContent();

    expect(pdfPageCount($pdf))->toBe(1);
});

it('clamps a qr that would not fit the tag', function () {
    $settings = LabelSetting::current();
    // 18 mm tall less 2 mm margins each side leaves 14 mm.
    $settings->update(['qr_enabled' => true, 'qr_size_mm' => 99]);

    expect($settings->fresh()->maxQrSizeMm())->toBe(14.0)
        ->and($settings->fresh()->effectiveQrSizeMm())->toBe(14.0);

    $pdf = $this->actingAs($this->admin)->get(route('items.label', makeLabelItem()))->getContent();

    expect(pdfPageCount($pdf))->toBe(1);
});

it('does not embed a font, keeping the tag small', function () {
    $pdf = $this->actingAs($this->admin)->get(route('items.label', makeLabelItem()))->getContent();

    // Helvetica is a PDF core font; embedding DejaVu would push this past 800 KB.
    expect(strlen($pdf))->toBeLessThan(50_000)
        ->and($pdf)->toContain('/Helvetica');
});

it('lets a sales user print but not a user without the permission', function () {
    $sales = User::factory()->create();
    $sales->assignRole('Sales');

    $item = makeLabelItem();

    $this->actingAs($sales)->get(route('items.label', $item))->assertOk();

    $nobody = User::factory()->create();
    $this->actingAs($nobody)->get(route('items.label', $item))->assertForbidden();
});
