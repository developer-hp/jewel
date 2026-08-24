<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * What an estimate line is worth.
 *
 * Deliberately its own service rather than methods on the model: the screen computes
 * these in JavaScript as the clerk types, the PDF computes them again at print, and
 * the tests check them a third time. One statement of the rule means those three
 * cannot drift apart.
 *
 * The same shape serves the item-based estimate to come — only the way a line is
 * named differs, so this and the row partial are shared from the start.
 */
class EstimateLineMath
{
    /**
     * Pure gold in a line: the net weight at its purity.
     */
    public function fineWeight(float $netWeight, float $touchPercent): float
    {
        return round($netWeight * $touchPercent / 100, 3);
    }

    /**
     * What the line is worth.
     *
     * The rate is quoted per ten grams, the way the trade quotes it, which is what
     * makes 10 g at 91.6% and 150000 come out at exactly 137400.
     */
    public function lineValue(float $netWeight, float $touchPercent, float $ratePerTenGrams): float
    {
        return round($this->fineWeight($netWeight, $touchPercent) * $ratePerTenGrams / 10, 2);
    }

    /**
     * The footer under the grid.
     *
     * Note the `fine` figure sits under the **%** column on the printed form — it is
     * the total pure gold, not an average percentage, which no one would want.
     *
     * @param  Collection<int, object>  $lines  anything with net_weight, touch_percent, rate, gross_weight
     * @return object{gross: float, net: float, fine: float, value: float}
     */
    public function totals(Collection $lines): object
    {
        $fine = 0.0;
        $value = 0.0;

        foreach ($lines as $line) {
            $net = (float) $line->net_weight;
            $touch = (float) $line->touch_percent;

            $fine += $this->fineWeight($net, $touch);
            $value += $this->lineValue($net, $touch, (float) $line->rate);
        }

        return (object) [
            'gross' => round((float) $lines->sum('gross_weight'), 3),
            'net' => round((float) $lines->sum('net_weight'), 3),
            'fine' => round($fine, 3),
            'value' => round($value, 2),
        ];
    }
}
