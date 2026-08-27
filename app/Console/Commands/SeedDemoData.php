<?php

namespace App\Console\Commands;

use App\Models\Angadiya;
use App\Models\CashDrawer;
use App\Models\CashEntry;
use App\Models\Customer;
use App\Models\Hallmark;
use App\Models\Item;
use App\Models\ItemEstimate;
use App\Models\ItemEstimateLine;
use App\Models\ItemGroup;
use App\Models\MetalRate;
use App\Models\MetalType;
use App\Models\OgEstimate;
use App\Models\OrderForm;
use App\Models\Purity;
use App\Models\RepairForm;
use App\Models\SalesPerson;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CashMath;
use App\Services\ItemCalculator;
use App\Services\ItemPhotoStore;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fills an empty install with a plausible day's trading, so the screens and the
 * PDFs can be looked at without typing thirty forms in by hand.
 *
 * Everything goes through the models' own creation path — nextItemCode(),
 * nextRefNo(), ItemCalculator, ItemPhotoStore — rather than raw inserts, so the
 * demo data exercises the same counters and derived figures the app does. That is
 * the point of a command rather than a SQL dump: if a rule changes, this breaks
 * loudly instead of quietly seeding data the app could never have produced.
 *
 * Photos are drawn on the fly with GD. No network, no binary blobs in the repo.
 */
class SeedDemoData extends Command
{
    protected $signature = 'demo:seed
        {--items=24 : How many stock items to create}
        {--fresh : Wipe the trading data first, so a reseed does not pile up on the last one}
        {--force : Required to run outside local/testing}';

    protected $description = 'Seed demo trading data — items with photos, orders, repairs, estimates, cash and angadiya slips';

    public function handle(ItemCalculator $calculator, ItemPhotoStore $photos): int
    {
        if (! app()->environment(['local', 'testing']) && ! $this->option('force')) {
            $this->error('Refusing to seed demo data in '.app()->environment().'. Pass --force if you mean it.');

            return self::FAILURE;
        }

        if (! extension_loaded('gd')) {
            $this->warn('The gd extension is not loaded — items will be seeded without photos.');
        }

        if ($this->option('fresh') && ! $this->wipe()) {
            return self::FAILURE;
        }

        $this->components->info('Seeding demo data…');

        // Masters first: everything below picks purities and groups out of them.
        $this->callSilent('db:seed', ['--class' => RolePermissionSeeder::class]);
        $this->callSilent('db:seed', ['--class' => MasterDataSeeder::class]);

        $user = User::query()->first();
        $people = $this->salesPeople();
        $suppliers = $this->suppliers();
        $customers = $this->customers();

        $this->rates();
        $items = $this->items($calculator, $photos, $suppliers);
        $this->orderForms($items, $people);
        $this->repairForms();
        $og = $this->ogEstimates($customers, $people);
        $estimates = $this->itemEstimates($items, $customers, $people);
        $this->angadiyas($suppliers, $user);
        $this->cash($estimates, $og, $user);
        $this->hallmark($photos, $suppliers);

        $this->newLine();
        $this->components->info('Done. Sign in and have a look.');

        $this->table(['What', 'Rows'], [
            ['Items', Item::count()],
            ['Customers', Customer::count()],
            ['Order forms', OrderForm::count()],
            ['Repair forms', RepairForm::count()],
            ['OG estimates', OgEstimate::count()],
            ['Item estimates', ItemEstimate::count()],
            ['Angadiya slips', Angadiya::count()],
            ['Cash entries', CashEntry::count()],
            ['Hallmark lots', Hallmark::count()],
        ]);

        return self::SUCCESS;
    }

    /**
     * Clear the trading data so a reseed replaces it rather than stacking on it.
     *
     * Masters, users and settings are left alone — this wipes what a day of trading
     * produced, not the shop's configuration. Children go before parents: MySQL will
     * not let a row go while a foreign key still points at it, and items are last
     * because the estimate and order lines reference them.
     */
    private function wipe(): bool
    {
        if (! $this->option('force') && ! $this->confirm('Delete ALL items, orders, repairs, estimates, cash entries, angadiya slips and hallmark lots?')) {
            $this->components->warn('Left the existing data alone.');

            return false;
        }

        DB::transaction(function () {
            CashEntry::withTrashed()->forceDelete();
            CashDrawer::withTrashed()->forceDelete();

            ItemEstimate::withTrashed()->get()->each->forceDelete();
            OgEstimate::withTrashed()->get()->each->forceDelete();
            OrderForm::withTrashed()->get()->each->forceDelete();
            RepairForm::withTrashed()->get()->each->forceDelete();
            Hallmark::withTrashed()->get()->each->forceDelete();

            Angadiya::withTrashed()->forceDelete();
            Item::withTrashed()->get()->each->forceDelete();
            MetalRate::query()->delete();
        });

        $this->components->info('Cleared the previous trading data.');

        return true;
    }

    /** @return Collection<int, SalesPerson> */
    private function salesPeople()
    {
        return collect([
            ['name' => 'Rameshbhai', 'city' => 'Ahmedabad'],
            ['name' => 'Shilpa Soni', 'city' => 'Surat'],
            ['name' => 'Zubin', 'city' => 'Rajkot'],
        ])->map(fn (array $row) => SalesPerson::firstOrCreate(['name' => $row['name']], $row));
    }

    /** @return Collection<int, Supplier> */
    private function suppliers()
    {
        return collect([
            ['name' => 'Yamuna Gold', 'city' => 'Rajkot', 'phone' => '9558776785'],
            ['name' => 'S. Pal Jewellers', 'city' => 'Surat', 'phone' => '7567280980'],
            ['name' => 'Alap Exports', 'city' => 'Palanpur', 'phone' => '9604816192'],
        ])->map(fn (array $row) => Supplier::firstOrCreate(['name' => $row['name']], $row));
    }

    /** @return Collection<int, Customer> */
    private function customers()
    {
        return collect([
            ['name' => 'Ravibhai Bhalodiya', 'phone' => '9601263350', 'address' => 'Ahmedabad'],
            ['name' => 'Nilesh Shah', 'phone' => '9824237636', 'address' => 'Bhavnagar'],
            ['name' => 'Priya Mehta', 'phone' => '9825011223', 'address' => 'Vadodara'],
            ['name' => 'Kiran Patel', 'phone' => '9909887766', 'address' => 'Surat'],
        ])->map(fn (array $row) => Customer::firstOrCreate(['phone' => $row['phone']], $row));
    }

    /**
     * Today's rates, so the estimate screens and the rates popup have something to
     * show. Only the purities actually used below are priced.
     */
    private function rates(): void
    {
        $prices = ['24K' => 152000, '22K' => 150000, '18K' => 118000, '14K' => 94000, '999' => 92000, '925' => 86000];

        foreach (Purity::with('metalType')->get() as $purity) {
            $rate = $prices[$purity->name] ?? 100000;

            MetalRate::updateOrCreate(
                ['purity_id' => $purity->id, 'effective_date' => today()->toDateString()],
                ['rate' => $rate, 'per_grams' => $purity->default_per_grams ?: 10],
            );
        }
    }

    /** @return Collection<int, Item> */
    private function items(ItemCalculator $calculator, ItemPhotoStore $photos, $suppliers)
    {
        $count = max(1, (int) $this->option('items'));

        $names = [
            'Ring' => ['Solitaire Ring', 'Band Ring', 'Cocktail Ring'],
            'Necklace' => ['Temple Necklace', 'Choker Set', 'Rani Haar'],
            'Bangle' => ['Kada Bangle', 'Filigree Bangle'],
            'Earring' => ['Jhumka', 'Stud Earring', 'Chandbali'],
            'Chain' => ['Rope Chain', 'Box Chain'],
            'Pendant' => ['Locket Pendant', 'Om Pendant'],
        ];

        // Only the groups a shop actually stocks; the reserved Repair/Order groups
        // are filled by their own modules, not by hand.
        $groups = ItemGroup::whereNull('system_key')->whereIn('name', array_keys($names))->get();
        $gold = MetalType::where('code', 'GOLD')->firstOrFail();
        $purities = Purity::where('metal_type_id', $gold->id)->get()->keyBy('name');

        $created = collect();
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        for ($i = 0; $i < $count; $i++) {
            $group = $groups[$i % $groups->count()];
            $purity = $purities[['22K', '18K', '22K', '14K'][$i % 4]] ?? $purities->first();
            $pool = $names[$group->name];

            $item = DB::transaction(function () use ($group, $gold, $purity, $pool, $i, $suppliers, $calculator) {
                $item = new Item([
                    'item_group_id' => $group->id,
                    'metal_type_id' => $gold->id,
                    'purity_id' => $purity->id,
                    'supplier_id' => $suppliers->random()->id,
                    'name' => $pool[$i % count($pool)],
                    'gross_weight' => round(4 + ($i % 17) + ($i % 7) / 10, 3),
                    'other_deduction' => 0,
                    'is_active' => true,
                ]);

                $item->code = $group->nextItemCode();
                $item->net_weight = $item->gross_weight;
                $item->save();

                $calculator->recalculate($item);

                return $item;
            });

            $this->attachPhoto($photos, $item, $item->code, $item->name);

            $created->push($item->fresh());
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        return $created;
    }

    private function orderForms($items, $people): void
    {
        // The demo set is a whole picture, not an increment: if these already
        // exist, leave them be. Use --fresh to start over.
        if (OrderForm::exists()) {
            return;
        }

        $orders = [
            ['Nilesh Shah', '9824237636', ['Gold bangle pair, 22K', 'Matching earrings']],
            ['Priya Mehta', '9825011223', ['Diamond pendant, 18K']],
            ['Kiran Patel', '9909887766', ['Chain 22K, 20 inch', 'Locket', 'Ring size 14']],
        ];

        foreach ($orders as $index => [$name, $phone, $lines]) {
            DB::transaction(function () use ($name, $phone, $lines, $index, $people) {
                $form = new OrderForm([
                    'form_date' => today()->subDays($index + 1),
                    'delivery_date' => today()->addDays(10 - $index),
                    'customer_name' => $name,
                    'contact_no' => $phone,
                    'sales_person_id' => $people->random()->id,
                    'remarks' => 'Demo order',
                ]);

                $form->ref_no = OrderForm::nextRefNo();
                $form->save();

                foreach ($lines as $sort => $description) {
                    $form->lines()->create([
                        'description' => $description,
                        'made_to_order' => true,
                        'net_weight' => round(6 + $sort * 2.35, 3),
                        'sort_order' => $sort,
                    ]);
                }
            });
        }
    }

    private function repairForms(): void
    {
        if (RepairForm::exists()) {
            return;
        }

        $repairs = [
            ['Ravibhai Bhalodiya', '9601263350', 'Polish and rhodium', [['Earring', 2.000]]],
            ['Priya Mehta', '9825011223', 'Clasp broken', [['Chain', 7.020], ['Pendant', 3.480]]],
            ['Nilesh Shah', '9824237636', 'Resize to 16', [['Ring', 4.150]]],
        ];

        foreach ($repairs as $index => [$name, $phone, $remark, $lines]) {
            DB::transaction(function () use ($name, $phone, $remark, $lines, $index) {
                $form = new RepairForm([
                    'form_date' => today()->subDays($index),
                    'delivery_date' => today()->addDays(7 + $index),
                    'customer_name' => $name,
                    'contact_no' => $phone,
                    'approx_extra_charge' => $index === 1 ? 1500 : null,
                    'remarks' => $remark,
                ]);

                $form->ref_no = RepairForm::nextRefNo();
                $form->save();

                foreach ($lines as $sort => [$description, $weight]) {
                    $form->lines()->create([
                        'description' => $description,
                        'net_weight' => $weight,
                        'sort_order' => $sort,
                    ]);
                }
            });
        }
    }

    /** @return Collection<int, OgEstimate> */
    private function ogEstimates($customers, $people)
    {
        if (OgEstimate::exists()) {
            return OgEstimate::with('lines')->get();
        }

        $sets = [
            [['Old ring', 10.000, 10.000, 91.6, 150000], ['Broken chain', 12.000, 11.400, 85.0, 150000]],
            [['Bangle pair', 24.500, 23.900, 91.6, 150000]],
        ];

        return collect($sets)->map(function (array $lines, int $index) use ($customers, $people) {
            return DB::transaction(function () use ($lines, $index, $customers, $people) {
                $customer = $customers[$index % $customers->count()];
                $person = $people->random();

                $estimate = new OgEstimate([
                    'estimate_date' => today()->subDays($index),
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'contact_no' => $customer->phone,
                    'address' => $customer->address,
                    'sales_person_id' => $person->id,
                    'sales_person_name' => $person->name,
                ]);

                $estimate->ref_no = OgEstimate::nextRefNo();
                $estimate->save();

                foreach ($lines as $sort => [$description, $gross, $net, $touch, $rate]) {
                    $estimate->lines()->create([
                        'description' => $description,
                        'gross_weight' => $gross,
                        'net_weight' => $net,
                        'touch_percent' => $touch,
                        'rate' => $rate,
                        'sort_order' => $sort,
                    ]);
                }

                return $estimate->fresh('lines');
            });
        });
    }

    /** @return Collection<int, ItemEstimate> */
    private function itemEstimates($items, $customers, $people)
    {
        if (ItemEstimate::exists()) {
            return ItemEstimate::with('lines')->get();
        }

        return collect(range(0, 2))->map(function (int $index) use ($items, $customers, $people) {
            return DB::transaction(function () use ($index, $items, $customers, $people) {
                $customer = $customers[$index % $customers->count()];
                $person = $people->random();

                $estimate = new ItemEstimate([
                    'estimate_date' => today()->subDays($index),
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'contact_no' => $customer->phone,
                    'address' => $customer->address,
                    'sales_person_id' => $person->id,
                    'sales_person_name' => $person->name,
                    'gst_enabled' => $index === 0,
                    'gst_percent' => 3,
                    'show_photo' => true,
                ]);

                $estimate->ref_no = ItemEstimate::nextRefNo();
                $estimate->save();

                // Distinct items per estimate, so the sold-items screen has a real
                // one-item-one-document picture to work from.
                foreach ($items->slice($index * 2, 2) as $sort => $item) {
                    $estimate->lines()->create([
                        'item_id' => $item->id,
                        'description' => $item->name,
                        'gross_weight' => $item->gross_weight,
                        'rate' => 150000,
                        'labour_amount' => 12,
                        'labour_type' => ItemEstimateLine::LABOUR_PERCENTAGE,
                        'oc_amount' => 250,
                        'sort_order' => $sort,
                    ]);
                }

                return $estimate->fresh('lines');
            });
        });
    }

    private function angadiyas($suppliers, ?User $user): void
    {
        if (Angadiya::exists()) {
            return;
        }

        $slips = [
            ['Surat', 'S.PAL', '7567280980', 120000, 'chain 18 k 6.280 / chain 18 k 4.480'],
            ['Palanpur', 'Alap Exports', '9604816192', 420000, 'set 22k 31.580'],
            ['Rajkot', 'Yamuna Gold', '9558776785', 105000, 'chain 22k 7.020'],
            ['Ahmedabad', 'BHV', '9824237636', 25000, 'ring 22k 4.150'],
        ];

        foreach ($slips as $index => [$city, $name, $mobile, $insurance, $remark]) {
            Angadiya::create([
                'supplier_id' => $suppliers[$index % $suppliers->count()]->id,
                'name' => $name,
                'city' => $city,
                'mobile' => $mobile,
                'insurance_amount' => $insurance,
                'remark' => $remark,
            ]);
        }
    }

    /**
     * A drawer with an opening float and one settled estimate, so the cash listing
     * and the drawer balance both show something.
     */
    private function cash($estimates, $og, ?User $user): void
    {
        $drawer = CashDrawer::firstOrCreate(
            ['name' => 'Counter 1'],
            ['opening_balance' => 25000, 'sort_order' => 1, 'is_active' => true],
        );

        CashDrawer::firstOrCreate(
            ['name' => 'Counter 2'],
            ['opening_balance' => 10000, 'sort_order' => 2, 'is_active' => true],
        );

        $estimate = $estimates->first();

        if (! $estimate || CashEntry::where('item_estimate_id', $estimate->id)->exists()) {
            return;
        }

        $math = app(CashMath::class);
        $goldSource = $og->first();
        $gold = $math->goldFigures($goldSource);
        $final = $math->finalAmount($estimate);

        DB::transaction(function () use ($drawer, $estimate, $goldSource, $gold, $final, $user) {
            $entry = new CashEntry([
                'entry_date' => today(),
                'cash_drawer_id' => $drawer->id,
                'cash_event' => CashEntry::EVENT_IN,
                'cash_amount' => round(max(0, $final - $gold['amount']) * 0.6, 2),
                'cheque_amount' => 0,
            ]);

            // Every one of these is deliberately not fillable — the controller sets
            // them from a server-side reload, and so does this.
            $entry->forceFill([
                'ref_no' => CashEntry::nextRefNo(),
                'item_estimate_id' => $estimate->id,
                'og_estimate_id' => $goldSource?->id,
                'final_amount' => $final,
                'document_reference' => $estimate->reference(),
                'og_reference' => $goldSource?->reference(),
                'party_name' => $estimate->customer_name,
                'gold_weight' => $gold['weight'],
                'gold_amount' => $gold['amount'],
                'created_by' => $user?->id,
            ])->save();
        });
    }

    private function hallmark(ItemPhotoStore $photos, $suppliers): void
    {
        if (Hallmark::exists()) {
            return;
        }

        $lot = DB::transaction(function () use ($suppliers) {
            $lot = new Hallmark([
                'hallmark_date' => today(),
                'cost_per_piece' => 30,
                'gross_weight' => 920.070,
            ]);

            $lot->lot_no = Hallmark::nextLotNo();
            $lot->save();

            $gold = MetalType::where('code', 'GOLD')->firstOrFail();
            $purities = Purity::where('metal_type_id', $gold->id)->get()->keyBy('name');

            $lines = [
                ['Chain', '22K', 4, 1],
                ['Set', '22K', 1, 3],
                ['Ring', '22K', 12, 1],
                ['Chain', '18K', 1, 1],
            ];

            foreach ($lines as $sort => [$groupName, $purityName, $quantity, $per]) {
                $group = ItemGroup::where('name', $groupName)->first() ?? ItemGroup::whereNull('system_key')->first();

                $lot->lines()->create([
                    'item_group_id' => $group->id,
                    'purity_id' => $purities[$purityName]->id,
                    'supplier_id' => $suppliers->random()->id,
                    'description' => strtoupper($groupName).($purityName === '18K' ? '18' : ''),
                    'quantity' => $quantity,
                    'pcs_per_quantity' => $per,
                    'sort_order' => $sort,
                ]);
            }

            return $lot;
        });

        $this->attachPhoto($photos, $lot, 'LOT '.$lot->lot_no, 'Hallmark');
    }

    /**
     * Draw a placeholder photo and store it through the real photo store, so the
     * disk setting and the naming convention are honoured.
     */
    private function attachPhoto(ItemPhotoStore $photos, $model, string $caption, string $subtitle): void
    {
        if (! extension_loaded('gd')) {
            return;
        }

        $file = $this->drawCard($caption, $subtitle);

        if (! $file) {
            return;
        }

        $photos->put($model, new UploadedFile(
            $file,
            basename($file),
            'image/png',
            null,
            true, // Test mode: the file was not uploaded, it was written here.
        ));

        @unlink($file);
    }

    /**
     * A 400x400 tinted card with the code on it. Recognisable at a glance on a
     * listing and in a PDF, which is all a demo photo has to be.
     */
    private function drawCard(string $caption, string $subtitle): ?string
    {
        $size = 400;
        $image = imagecreatetruecolor($size, $size);

        // Hue keyed off the caption, so each item gets its own colour and the same
        // item gets the same one on a reseed.
        $seed = crc32($caption);
        $background = imagecolorallocate(
            $image,
            140 + ($seed % 80),
            110 + (($seed >> 8) % 90),
            160 + (($seed >> 16) % 70),
        );
        $ink = imagecolorallocate($image, 255, 255, 255);
        $shade = imagecolorallocate($image, 0, 0, 0);

        imagefilledrectangle($image, 0, 0, $size, $size, $background);
        imagefilledrectangle($image, 0, $size - 90, $size, $size, $shade);
        imagerectangle($image, 6, 6, $size - 7, $size - 7, $ink);

        // Built-in bitmap fonts: no TTF on the box is a safe assumption, and this
        // never has to look good, only be legible.
        $this->centreText($image, 5, $caption, $size, 170, $ink);
        $this->centreText($image, 3, $subtitle, $size, $size - 55, $ink);

        $path = tempnam(sys_get_temp_dir(), 'demo').'.png';
        imagepng($image, $path);
        imagedestroy($image);

        return is_file($path) ? $path : null;
    }

    private function centreText($image, int $font, string $text, int $width, int $y, int $colour): void
    {
        $text = substr($text, 0, 24);
        $x = (int) max(4, ($width - imagefontwidth($font) * strlen($text)) / 2);

        imagestring($image, $font, $x, $y, $text, $colour);
    }
}
