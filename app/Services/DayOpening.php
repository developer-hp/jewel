<?php

namespace App\Services;

use App\Models\Angadiya;
use App\Models\AppSetting;
use App\Models\CashDrawer;
use App\Models\CashEntry;
use App\Models\InternalStock;
use App\Models\InternalStockEntry;
use App\Models\Item;
use App\Models\SupplierHisab;
use App\Models\WhatsAppReceiver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Closing the day's books and starting the next.
 *
 * This is the most destructive thing in the app. It reports the day, carries the
 * balances forward, and then **permanently deletes** the day's estimates, vouchers,
 * angadiya slips, supplier hisab and cash entries. There is no archive and no undo —
 * that was a deliberate choice, so the order of operations below matters:
 *
 *   1. settle the item state, while the evidence still exists
 *   2. render and send the reports, while the rows still exist
 *   3. carry the balances forward
 *   4. delete
 *
 * Steps 1 to 3 happen before anything is removed, so a failure leaves the day intact
 * rather than half-closed.
 */
class DayOpening
{
    public function __construct(
        private readonly OpeningReports $reports,
        private readonly WhatsAppNotifier $whatsapp,
    ) {}

    /**
     * Run the whole thing, and report what it did.
     *
     * @return array<string, mixed>
     */
    public function run(): array
    {
        $since = $this->since();
        $until = now();

        $summary = [
            'since' => $since->toDateTimeString(),
            'until' => $until->toDateTimeString(),
            'marked_sold' => $this->markSettledItemsSold(),
        ];

        // Rendered and sent before anything is deleted: the reports describe rows
        // that are about to stop existing.
        $summary['reports'] = $this->reports->build($since, $until);
        $summary['sent_to'] = $this->send($summary['reports'], $until);

        $summary += DB::transaction(fn () => [
            'drawers' => $this->rollDrawers(),
            'internal_stocks' => $this->rollInternalStocks(),
            'deleted' => $this->clearTheDay(),
        ]);

        // Stamped last, and to the moment the window closed rather than to now, so
        // nothing that happened while this was running is skipped by the next one.
        AppSetting::current()->forceFill(['last_opening_at' => $until])->save();

        Log::info('Day opening completed.', $summary);

        return $summary;
    }

    /**
     * Where this opening's window starts: the moment the last one closed.
     *
     * A boundary rather than a date, which is what makes the two ways of running it
     * agree. An opening at 11:30 covers everything since 11:30 yesterday, and one run
     * by hand two days later covers both days — neither has to guess which day it is
     * closing.
     *
     * On the very first opening there is no boundary, so everything counts.
     */
    public function since(): Carbon
    {
        return AppSetting::current()->last_opening_at ?? Carbon::createFromTimestamp(0);
    }

    /**
     * Mark every piece that was paid for but never written out of stock.
     *
     * Deleting the estimates removes the only proof a piece was settled, so anything
     * left unmarked here could never be marked again. Stamped now, which puts it
     * inside this opening's window and therefore onto this opening's report.
     */
    private function markSettledItemsSold(): int
    {
        $items = Item::query()
            ->inStock()
            ->whereHas('estimateLines.itemEstimate.cashEntry')
            ->get();

        foreach ($items as $item) {
            $item->markSold();
        }

        return $items->count();
    }

    /**
     * Each drawer starts tomorrow holding what its notes came to.
     *
     * Cash only — a cheque is not in the till and neither is old gold, whatever the
     * listing's balance column adds up.
     *
     * @return int drawers carried forward
     */
    private function rollDrawers(): int
    {
        $drawers = CashDrawer::query()->get();

        foreach ($drawers as $drawer) {
            $cash = (float) CashEntry::query()
                ->where('cash_drawer_id', $drawer->id)
                // The same expression the balance is read from, so a drawer is never
                // rolled forward by a figure it never showed.
                ->selectRaw('COALESCE(SUM('.CashMath::CASH_SIGNED_SQL.'), 0) as moved')
                ->value('moved');

            $drawer->forceFill([
                'opening_balance' => round((float) $drawer->opening_balance + $cash, 2),
            ])->save();
        }

        return $drawers->count();
    }

    /**
     * Every pot set to reset starts tomorrow with one opening entry for what it
     * holds, and the day's movements go.
     *
     * A pot with reset_on_opening off is left exactly as it is — its ledger is meant
     * to run on across days.
     *
     * @return int pots carried forward
     */
    private function rollInternalStocks(): int
    {
        $stocks = InternalStock::query()->where('reset_on_opening', true)->get();

        foreach ($stocks as $stock) {
            $balance = $stock->balance();

            // Hard delete, so the pot genuinely starts from one line rather than
            // from a ledger that merely looks empty.
            InternalStockEntry::withTrashed()->where('internal_stock_id', $stock->id)->forceDelete();

            if (abs($balance) > 0) {
                $entry = new InternalStockEntry([
                    'internal_stock_id' => $stock->id,
                    'type' => InternalStockEntry::TYPE_OPENING,
                    'weight' => abs($balance),
                    'note' => 'Carried forward by the day opening.',
                ]);

                $entry->save();
            }
        }

        return $stocks->count();
    }

    /**
     * Remove the day.
     *
     * The order is the foreign keys' order, not a preference. MySQL refuses to
     * TRUNCATE a table anything points at, so these are deletes, children first —
     * and cash entries go before the documents they reference, or they would be left
     * behind pointing at nothing.
     *
     * @return array<string, int>
     */
    private function clearTheDay(): array
    {
        $counts = [];

        // Cash first: its foreign keys are nullOnDelete, so leaving it until after
        // the estimates would strand every entry with no document behind it.
        $counts['cash_entries'] = CashEntry::withTrashed()->forceDelete();

        // Children before parents. item_estimate_line_stones and the line tables
        // cascade in the schema, but deleting them explicitly keeps the counts
        // honest and does not depend on the cascade being there.
        $counts['item_estimate_line_stones'] = DB::table('item_estimate_line_stones')->delete();
        $counts['item_estimate_lines'] = DB::table('item_estimate_lines')->delete();
        $counts['item_estimates'] = DB::table('item_estimates')->delete();

        $counts['og_estimate_lines'] = DB::table('og_estimate_lines')->delete();
        $counts['og_estimates'] = DB::table('og_estimates')->delete();

        $counts['vouchers'] = DB::table('vouchers')->delete();

        $counts['angadiyas'] = Angadiya::withTrashed()->forceDelete();

        $counts['supplier_hisab_payments'] = DB::table('supplier_hisab_payments')->delete();
        $counts['supplier_hisabs'] = SupplierHisab::withTrashed()->forceDelete();

        return $counts;
    }

    /**
     * Send the reports to everyone on the list.
     *
     * Nothing here throws: the books are closed either way, and a messaging problem
     * must not leave the opening half-done.
     *
     * @param  array<int, array{title: string, link: string, filename: string}>  $reports
     * @return int messages queued
     */
    private function send(array $reports, Carbon $until): int
    {
        $receivers = WhatsAppReceiver::active()->ordered()->get();
        $sent = 0;

        foreach ($receivers as $receiver) {
            foreach ($reports as $report) {
                $queued = rescue(fn () => $this->whatsapp->documentSent(
                    $receiver->mobile,
                    $receiver->name,
                    $report['title'].' '.$until->format('d-m-Y'),
                    ['link' => $report['link'], 'filename' => $report['filename']],
                ), false, report: true);

                if ($queued) {
                    $sent++;
                }
            }
        }

        return $sent;
    }
}
