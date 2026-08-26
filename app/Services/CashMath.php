<?php

namespace App\Services;

use App\Models\CashDrawer;
use App\Models\CashEntry;
use App\Models\ItemEstimate;
use App\Models\OgEstimate;
use App\Models\Voucher;

/**
 * Every figure a cash entry derives, in one place.
 *
 * The screen recomputes the discount in JavaScript as the clerk types and the tests
 * check it again here — one statement of the rule keeps the two from drifting, the
 * same bargain ItemEstimateMath makes.
 *
 * Nothing derived is stored. What *is* stored on the entry is what the documents said
 * at the moment the money changed hands, because both sources recompute themselves
 * from live lines.
 */
class CashMath
{
    /**
     * The settled figure, as SQL. Paired with settled() below so the drawer listing's
     * aggregate and the model accessor cannot disagree.
     */
    public const SETTLED_SQL = '(cash_amount + cheque_amount + gold_amount)';

    /** The same, signed by the event — what a drawer's balance sums. */
    public const SIGNED_SQL = "(CASE WHEN cash_event = 'in' THEN 1 ELSE -1 END) * ".self::SETTLED_SQL;

    /**
     * What the document says is payable.
     *
     * An item estimate's is its summary total — amount plus GST, rounded to the
     * nearest ten. A voucher simply has an amount.
     */
    public function finalAmount(ItemEstimate|Voucher $document): float
    {
        return $document instanceof ItemEstimate
            ? (float) $document->summary()->total
            : round((float) $document->amount, 2);
    }

    /**
     * The old gold an OG estimate is worth: the weight physically taken in, and what
     * it was valued at.
     *
     * @return array{weight: float, amount: float}
     */
    public function goldFigures(?OgEstimate $estimate): array
    {
        if (! $estimate) {
            return ['weight' => 0.0, 'amount' => 0.0];
        }

        $totals = $estimate->totals();

        return ['weight' => (float) $totals->net, 'amount' => (float) $totals->value];
    }

    /**
     * What actually changed hands. The discount is not part of it — a discount was
     * never money — which is why the drawer moves by this and not by the final
     * amount.
     */
    public function settled(CashEntry $entry): float
    {
        return round(
            (float) $entry->cash_amount + (float) $entry->cheque_amount + (float) $entry->gold_amount,
            2
        );
    }

    /**
     * What the document asked for, less what was handed over.
     *
     * Never negative on a saved entry: CashEntryRequest refuses an over-payment,
     * because more money than the document is worth means a typo or the wrong
     * document.
     */
    public function discount(CashEntry $entry): float
    {
        return round((float) $entry->final_amount - $this->settled($entry), 2);
    }

    /** Signed by the event: money in adds to the till, money out takes from it. */
    public function signed(CashEntry $entry): float
    {
        return $entry->cash_event === CashEntry::EVENT_OUT
            ? -$this->settled($entry)
            : $this->settled($entry);
    }

    /**
     * The shop's position: what should be in the tills, and how much old gold has
     * come across the counter.
     *
     * Cash is every drawer's opening figure plus the signed movement, so it is the
     * same number the drawer listing adds up. Gold is signed too — weight paid back
     * out counts against weight taken in.
     *
     * @return object{cash: float, gold: float}
     */
    public function position(): object
    {
        $opening = (float) CashDrawer::query()->sum('opening_balance');

        $movement = CashEntry::query()
            ->selectRaw('COALESCE(SUM('.self::SIGNED_SQL.'), 0) as cash')
            ->selectRaw("COALESCE(SUM((CASE WHEN cash_event = 'in' THEN 1 ELSE -1 END) * gold_weight), 0) as gold")
            ->first();

        return (object) [
            'cash' => round($opening + (float) $movement->cash, 2),
            'gold' => round((float) $movement->gold, 3),
        ];
    }

    /**
     * A single drawer's balance.
     *
     * One query per drawer, so this is for a form header or a detail page. The
     * listing computes every balance at once — see CashDrawerController::data().
     */
    public function balance(CashDrawer $drawer): float
    {
        $movement = (float) $drawer->entries()
            ->selectRaw('COALESCE(SUM('.self::SIGNED_SQL.'), 0) as movement')
            ->value('movement');

        return round((float) $drawer->opening_balance + $movement, 2);
    }
}
