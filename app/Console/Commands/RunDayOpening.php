<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Services\DayOpening;
use Illuminate\Console\Command;

/**
 * Close the books and start the next day.
 *
 * Destructive and not reversible — see App\Services\DayOpening. Run on a schedule
 * only when the shop has switched it on, and from the command line only when someone
 * means it, which is what --force is for.
 */
class RunDayOpening extends Command
{
    protected $signature = 'opening:run {--force : Run even when auto opening is switched off}';

    protected $description = "Close the day: report it, carry the balances forward, and clear the day's documents";

    public function handle(DayOpening $opening): int
    {
        if (! $this->option('force') && ! AppSetting::current()->auto_opening_enabled) {
            $this->components->warn('Auto opening is switched off. Use --force to run it anyway.');

            return self::SUCCESS;
        }

        $summary = $opening->run();

        $this->components->info("Day opening complete for {$summary['since']} → {$summary['until']}.");

        $this->components->twoColumnDetail('Items marked sold', (string) $summary['marked_sold']);
        $this->components->twoColumnDetail('Drawers carried forward', (string) $summary['drawers']);
        $this->components->twoColumnDetail('Internal stocks reset', (string) $summary['internal_stocks']);
        $this->components->twoColumnDetail('WhatsApp messages queued', (string) $summary['sent_to']);

        foreach ($summary['reports'] as $report) {
            $this->components->twoColumnDetail($report['title'], $report['count'].' pcs');
        }

        foreach ($summary['deleted'] as $table => $count) {
            $this->components->twoColumnDetail("Deleted {$table}", (string) $count);
        }

        return self::SUCCESS;
    }
}
