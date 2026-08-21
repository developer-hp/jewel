<?php

namespace Database\Seeders;

use App\Models\ItemGroup;
use App\Models\MakingCharge;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\StoneMaster;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    /**
     * Metal types and the purities each one owns.
     * [name, code, [ [purity name, touch, per-grams basis], ... ]]
     */
    private const METALS = [
        ['Gold', 'GOLD', [
            ['24K', 99.900, 10],
            ['22K', 91.600, 10],
            ['18K', 75.000, 10],
            ['14K', 58.500, 10],
        ]],
        ['Silver', 'SILV', [
            ['999', 99.900, 1000],
            ['925', 92.500, 1000],
        ]],
        ['Antique (Jadtar)', 'ANTQ', [
            ['22K', 91.600, 10],
            ['20K', 83.300, 10],
        ]],
        ['Diamond', 'DIAM', [
            ['18K', 75.000, 10],
            ['14K', 58.500, 10],
        ]],
    ];

    /** [name, prefix, padding] */
    private const ITEM_GROUPS = [
        ['Ring', 'RNG', 4],
        ['Necklace', 'NCK', 4],
        ['Bangle', 'BNG', 4],
        ['Earring', 'ERG', 4],
        ['Chain', 'CHN', 4],
        ['Pendant', 'PND', 4],
    ];

    /**
     * Groups the app itself owns. Repair pieces coming back into stock are coded
     * REPAIR0001; the Order module will use ORDER0001 when it is built. Marked with
     * a system key so they cannot be deleted.
     *
     * [name, prefix, padding, system key]
     */
    private const RESERVED_ITEM_GROUPS = [
        ['Repair', 'REPAIR', 4, ItemGroup::SYSTEM_REPAIR],
        ['Order', 'ORDER', 4, ItemGroup::SYSTEM_ORDER],
    ];

    /** [kind, name, code, rate unit, default rate, shape, quality, colour, size] */
    private const STONES = [
        ['stone', 'Ruby', 'ST-RUBY', 'carat', 1200.00, null, null, 'Red', null],
        ['stone', 'Emerald', 'ST-EMRL', 'carat', 1500.00, null, null, 'Green', null],
        ['stone', 'Pearl', 'ST-PERL', 'piece', 250.00, 'Round', null, 'White', null],
        ['stone', 'Kundan', 'ST-KUND', 'gram', 800.00, null, null, null, null],
        ['stone', 'Cubic Zirconia', 'ST-CZ', 'piece', 15.00, 'Round', 'AAA', 'White', '2mm'],
        ['stone', 'Meena Work', 'ST-MEEN', 'fixed', 500.00, null, null, null, null],
        ['diamond', 'Round Brilliant VVS', 'DI-RBVVS', 'carat', 65000.00, 'Round', 'VVS', 'EF', '0.10-0.15'],
        ['diamond', 'Round Brilliant VS', 'DI-RBVS', 'carat', 48000.00, 'Round', 'VS', 'FG', '0.10-0.15'],
        ['diamond', 'Round Brilliant SI', 'DI-RBSI', 'carat', 32000.00, 'Round', 'SI', 'GH', '0.05-0.10'],
        ['diamond', 'Princess Cut VS', 'DI-PRVS', 'carat', 52000.00, 'Princess', 'VS', 'FG', '0.15-0.20'],
        ['diamond', 'Solitaire (certified)', 'DI-SOLI', 'fixed', 185000.00, 'Round', 'VVS1', 'D', '1.00'],
    ];

    /** [code, name, charge type, rate, weight basis] */
    private const MAKING_CHARGES = [
        ['MC-FIX500', 'Fixed — Small Item', 'fixed', 500.0000, null],
        ['MC-FIX1500', 'Fixed — Large Item', 'fixed', 1500.0000, null],
        ['MC-PG350', 'Per Gram — Standard', 'per_gram', 350.0000, 'net'],
        ['MC-PG600', 'Per Gram — Antique', 'per_gram', 600.0000, 'gross'],
        ['MC-PCT08', '8% of Metal Value', 'percentage', 8.0000, null],
        ['MC-PCT12', '12% of Metal Value', 'percentage', 12.0000, null],
    ];

    public function run(): void
    {
        foreach (self::METALS as $index => [$name, $code, $purities]) {
            $metalType = MetalType::firstOrCreate(
                ['name' => $name],
                ['code' => $code, 'sort_order' => $index + 1],
            );

            foreach ($purities as $order => [$purityName, $touch, $perGrams]) {
                Purity::firstOrCreate(
                    ['metal_type_id' => $metalType->id, 'name' => $purityName],
                    ['touch' => $touch, 'default_per_grams' => $perGrams, 'sort_order' => $order + 1],
                );
            }
        }

        foreach (self::ITEM_GROUPS as $index => [$name, $prefix, $padding]) {
            ItemGroup::firstOrCreate(
                ['name' => $name],
                ['prefix' => $prefix, 'code_padding' => $padding, 'sort_order' => $index + 1],
            );
        }

        foreach (self::RESERVED_ITEM_GROUPS as $index => [$name, $prefix, $padding, $key]) {
            // Matched on the system key, not the name, so renaming the group in the
            // UI does not make the seeder create a second one.
            ItemGroup::firstOrCreate(
                ['system_key' => $key],
                [
                    'name' => $name,
                    'prefix' => $prefix,
                    'code_padding' => $padding,
                    'sort_order' => 100 + $index,
                ],
            );
        }

        foreach (self::STONES as [$kind, $name, $code, $unit, $rate, $shape, $quality, $colour, $size]) {
            StoneMaster::firstOrCreate(
                ['kind' => $kind, 'name' => $name],
                [
                    'code' => $code,
                    'rate_unit' => $unit,
                    'default_rate' => $rate,
                    'shape' => $shape,
                    'quality' => $quality,
                    'colour' => $colour,
                    'size' => $size,
                ],
            );
        }

        foreach (self::MAKING_CHARGES as [$code, $name, $type, $rate, $basis]) {
            MakingCharge::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'charge_type' => $type, 'rate' => $rate, 'weight_basis' => $basis],
            );
        }
    }
}
