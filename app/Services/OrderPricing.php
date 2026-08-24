<?php

namespace App\Services;

use App\Models\OrderForm;
use App\Models\OrderFormLine;

/**
 * What an order form is worth.
 *
 * This is the first place the shop's pricing rule is written down, and it exists now
 * because an advance has to sit against something. **Quotations must reuse it** rather
 * than restate the formula — two copies of a pricing rule is two answers.
 *
 * A line the shop cannot price yet — no purity, or no rate pinned and none entered for
 * that purity today — contributes nothing and is counted as unpriced. A partly-rated
 * order then shows an honest subtotal and says what is missing, instead of a confident
 * wrong total.
 */
class OrderPricing
{
    /**
     * @return object{value: float, priced: int, unpriced: int}
     */
    public function value(OrderForm $form): object
    {
        $value = 0.0;
        $priced = 0;
        $unpriced = 0;

        foreach ($form->lines as $line) {
            $lineValue = $this->lineValue($line);

            if ($lineValue === null) {
                $unpriced++;

                continue;
            }

            $value += $lineValue;
            $priced++;
        }

        return (object) [
            'value' => round($value, 2),
            'priced' => $priced,
            'unpriced' => $unpriced,
        ];
    }

    /**
     * Metal plus labour plus other charges, or null when the line cannot be priced.
     */
    public function lineValue(OrderFormLine $line): ?float
    {
        $rate = $this->ratePerGram($line);

        if ($rate === null) {
            return null;
        }

        $metal = (float) $line->net_weight * $rate;

        return round($metal + $this->labour($line, $metal) + (float) $line->oc_amount, 2);
    }

    /**
     * The pinned rate if the line has one, otherwise the rate of the day for its
     * purity. Null when neither exists — which is what makes a line unpriced.
     */
    public function ratePerGram(OrderFormLine $line): ?float
    {
        if ($line->isRateFixed()) {
            return (float) $line->fixed_rate_per_gram;
        }

        $today = $line->purity?->ratePerGramOn();

        return $today === null ? null : (float) $today;
    }

    private function labour(OrderFormLine $line, float $metalValue): float
    {
        $amount = (float) $line->lc_amount;

        return match ($line->lc_type) {
            OrderFormLine::LC_PERCENTAGE => $metalValue * $amount / 100,
            OrderFormLine::LC_FIXED => $amount,
            default => (float) $line->net_weight * $amount,
        };
    }
}
