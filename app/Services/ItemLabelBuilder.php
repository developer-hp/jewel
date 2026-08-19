<?php

namespace App\Services;

use App\Models\Item;
use App\Models\LabelSetting;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Turns an item plus the label settings into the flat array the tag Blade renders.
 *
 * Every formatting decision lives here — number precision, which rows are dropped,
 * caption fallbacks — so the Blade stays pure layout.
 */
class ItemLabelBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Item $item, ?LabelSetting $settings = null): array
    {
        $settings ??= LabelSetting::current();

        $item->loadMissing(['purity', 'metalType', 'itemStones']);

        return [
            'settings' => $settings,
            'code' => $item->code,
            'name' => $item->name,
            'netWeight' => $this->weight($item->net_weight),
            'shopName' => $settings->show_shop_name ? trim((string) $settings->shop_name) : null,
            'rows' => $this->rows($item, $settings),
            'qr' => $settings->qr_enabled ? $this->qrDataUri($item, $settings) : null,
            'qrSizeMm' => $settings->effectiveQrSizeMm(),
        ];
    }

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
