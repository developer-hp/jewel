<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemStone;
use App\Models\LabelSetting;
use App\Models\StoneMaster;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Collection;

/**
 * Turns an item plus a label template into the flat array the tag Blade renders.
 *
 * Every formatting decision lives here — number precision, which rows are dropped,
 * caption fallbacks — so the Blades stay pure layout.
 *
 * Three layouts share the identity, QR and formatting work and differ only in the
 * row structure they return. If a fourth arrives, that is the signal to split the
 * layout methods into App\Services\Labels\* behind a common interface; at three the
 * indirection would cost more than it saves.
 */
class ItemLabelBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Item $item, ?LabelSetting $settings = null): array
    {
        $settings ??= LabelSetting::forItem($item);

        // stoneMaster is the important one: both detail layouts print a code or a
        // shape per row, and without it a fifteen-stone piece fires fifteen extra
        // queries per tag. summariseStones() filters the loaded collection in
        // memory, so stones/diamonds must NOT be added here — they are separate
        // filtered relations and would double the query count for nothing.
        $item->loadMissing(['purity', 'metalType', 'makingCharge', 'itemStones.stoneMaster']);

        return array_merge([
            'settings' => $settings,
            'layout' => $settings->layout,
            'view' => 'items.labels._'.str_replace('_', '-', $settings->layout),
            'code' => $item->code,
            'name' => $settings->show_item_name ? trim((string) $item->name) : null,
            'netWeight' => $this->weight($item->net_weight),
            'grossWeight' => $this->weight($item->gross_weight),
            'purity' => $settings->show_purity ? $item->purity?->name : null,
            'making' => $settings->show_making_charge ? $this->makingLabel($item) : null,
            'shopName' => $settings->show_shop_name ? trim((string) $settings->shop_name) : null,
            'qr' => $settings->qr_enabled ? $this->qrDataUri($item, $settings) : null,
            'qrSizeMm' => $settings->effectiveQrSizeMm(),
        ], match ($settings->layout) {
            LabelSetting::LAYOUT_STONE_DETAIL => $this->stoneDetail($item, $settings),
            LabelSetting::LAYOUT_DIAMOND_DETAIL => $this->diamondDetail($item, $settings),
            default => ['rows' => $this->rows($item, $settings)],
        });
    }

    // --- standard -------------------------------------------------------------

    /**
     * The caption/value pairs to print, in order, already filtered by the visibility
     * flags and by whether the value is worth showing at all.
     *
     * @return array<int, array{label: string, value: string}>
     */
    private function rows(Item $item, LabelSetting $settings): array
    {
        $rows = [];

        if ($settings->show_gross) {
            $rows[] = ['label' => 'GW', 'value' => $this->weight($item->gross_weight)];
        }

        if ($settings->show_net) {
            $rows[] = ['label' => 'NW', 'value' => $this->weight($item->net_weight)];
        }

        if ($settings->show_purity && $item->purity) {
            $rows[] = ['label' => 'PUR', 'value' => $item->purity->name];
        }

        // Omitted when blank, like every other optional row.
        if ($settings->show_huid && filled($item->huid)) {
            $rows[] = ['label' => 'HUID', 'value' => $item->huid];
        }

        if ($settings->show_stone) {
            $rows = array_merge($rows, $this->stoneRows('ST', $item->stoneSummary()));
        }

        if ($settings->show_diamond) {
            $rows = array_merge($rows, $this->stoneRows('DI', $item->diamondSummary()));
        }

        // One combined total for every stone and diamond charge.
        if (($settings->show_stone || $settings->show_diamond) && $item->totalStoneCharge() > 0) {
            $rows[] = ['label' => 'STAMT', 'value' => $this->amount($item->totalStoneCharge())];
        }

        if ($settings->show_extra_charges) {
            foreach ($item->extraChargeLines() as $line) {
                $rows[] = ['label' => $line['label'], 'value' => $this->amount($line['amount'])];
            }
        }

        return $rows;
    }

    /**
     * A stone bucket prints in whichever unit it actually has — carat when the rows
     * were weighed, piece count when they were counted. Empty buckets print nothing.
     *
     * @param  array{pieces: int, carat: float, grams: float, amount: float}  $summary
     * @return array<int, array{label: string, value: string}>
     */
    private function stoneRows(string $label, array $summary): array
    {
        if ($summary['carat'] > 0) {
            return [['label' => $label, 'value' => $this->weight($summary['carat']).' ct']];
        }

        if ($summary['pieces'] > 0) {
            return [['label' => $label, 'value' => (string) $summary['pieces'].' pc']];
        }

        return [];
    }

    // --- stone detail ---------------------------------------------------------

    /**
     * A row per stone, with the extra charges beneath and OC totalling the lot.
     *
     * @return array<string, mixed>
     */
    private function stoneDetail(Item $item, LabelSetting $settings): array
    {
        $rows = $this->visibleStones($item, $settings)
            ->map(fn (ItemStone $stone) => [
                'code' => $this->stoneCode($stone),
                'weight' => $this->stoneWeight($stone),
                'pieces' => $stone->pieces > 0 ? (string) $stone->pieces : '',
                'rate' => $settings->show_stone_rate ? $this->amount((float) $stone->rate) : '',
                'amount' => $this->amount((float) $stone->amount),
            ])
            ->values();

        $chargeRows = $settings->show_extra_charges
            ? collect($item->extraChargeLines())
                ->map(fn (array $line) => [
                    'label' => $line['label'],
                    'amount' => $this->amount($line['amount']),
                ])->all()
            : [];

        // Every charge on the piece, which is what the printed column has to sum to.
        $oc = $item->totalStoneCharge() + $item->extraChargeTotal();

        return [
            'stoneRows' => $this->capRows($rows, (int) $settings->max_stone_rows),
            'chargeRows' => $chargeRows,
            'ocAmount' => $settings->show_oc && $oc > 0 ? $this->amount($oc) : null,
        ];
    }

    /**
     * Rows past the tag's capacity collapse into a single OTH line carrying their
     * combined amount, so the printed column still reconciles to OC rather than
     * quietly losing money or running onto a second page.
     *
     * @param  Collection<int, array<string, string>>  $rows
     * @return array<int, array<string, string>>
     */
    private function capRows(Collection $rows, int $max): array
    {
        if ($rows->count() <= $max) {
            return $rows->all();
        }

        // One line of the cap is given up to the OTH row itself.
        $kept = $rows->take(max(1, $max - 1));
        $dropped = $rows->skip($kept->count());

        $total = $dropped->sum(fn (array $row) => (float) str_replace(',', '', $row['amount']));

        return $kept->push([
            'code' => 'OTH',
            'weight' => '',
            'pieces' => (string) $dropped->count().' items',
            'rate' => '',
            'amount' => $this->amount($total),
        ])->all();
    }

    // --- diamond detail -------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function diamondDetail(Item $item, LabelSetting $settings): array
    {
        $diamonds = $settings->show_diamond
            ? $item->itemStones->filter(fn (ItemStone $s) => $s->kind === StoneMaster::KIND_DIAMOND)->values()
            : collect();

        // The sieve list and the detail groups come off the same collection, so the
        // two halves of the tag can never fall out of step.
        return [
            'sieves' => $diamonds
                ->map(fn (ItemStone $s) => $this->trimNumber($s->weight_carat).'-'.$s->pieces)
                ->all(),
            'diamondRows' => $diamonds
                ->map(fn (ItemStone $s) => [
                    'dw' => $this->weight($s->weight_carat),
                    'dr' => $settings->show_stone_rate ? $this->amount((float) $s->rate) : '',
                    'ds' => mb_strtoupper((string) $s->stoneMaster?->shape),
                ])
                ->all(),
        ];
    }

    // --- shared ---------------------------------------------------------------

    /**
     * The stone rows this template prints, in the order they were entered.
     *
     * @return Collection<int, ItemStone>
     */
    private function visibleStones(Item $item, LabelSetting $settings): Collection
    {
        return $item->itemStones->filter(function (ItemStone $stone) use ($settings) {
            return $stone->kind === StoneMaster::KIND_DIAMOND
                ? $settings->show_diamond
                : $settings->show_stone;
        })->values();
    }

    private function stoneCode(ItemStone $stone): string
    {
        $master = $stone->stoneMaster;

        // The code is what fits a tag; the name is the fallback when a master was
        // created without one.
        return (string) (filled($master?->code) ? $master->code : mb_substr((string) $master?->name, 0, 6));
    }

    /**
     * Carat where the row was weighed, grams where it was not, blank where it was
     * only counted.
     */
    private function stoneWeight(ItemStone $stone): string
    {
        if ((float) $stone->weight_carat > 0) {
            return $this->weight($stone->weight_carat);
        }

        return (float) $stone->weight_grams > 0 ? $this->weight($stone->weight_grams) : '';
    }

    /**
     * What prints under LB. The code is what the counter reads; the rate stands in
     * when a making charge was created without one.
     */
    private function makingLabel(Item $item): ?string
    {
        $charge = $item->makingCharge;

        if (! $charge) {
            return null;
        }

        return filled($charge->code) ? $charge->code : $this->trimNumber($charge->rate);
    }

    private function weight(float|string|null $value): string
    {
        return number_format((float) $value, 3, '.', '');
    }

    /**
     * dompdf's bundled DejaVu Sans predates the rupee glyph, so amounts print bare.
     */
    private function amount(float $value): string
    {
        return number_format($value, 0, '.', ',');
    }

    /**
     * 0.300 reads as 0.3, 350.0000 as 350 — a sieve size or a rate on a tag is not
     * an accounting figure.
     */
    private function trimNumber(float|string|null $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.') ?: '0';
    }

    private function qrDataUri(Item $item, LabelSetting $settings): string
    {
        $data = $settings->qr_content === 'item_url'
            ? route('items.show', $item)
            : $item->code;

        // Rendered well above the printed size so it stays crisp on a thermal printer.
        $pixels = max(60, (int) round($settings->effectiveQrSizeMm() * 12));

        $qrCode = new QrCode(
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $pixels,
            margin: 0,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );

        return (new PngWriter)->write($qrCode)->getDataUri();
    }
}
