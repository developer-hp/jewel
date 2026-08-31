<?php

namespace App\Console\Commands;

use App\Services\ActivityRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Move buffered activity rows out of Redis and into MySQL.
 *
 * Scheduled every four hours, and called with a small cap by the activity screen so
 * what is on it is what happened rather than what happened four hours ago.
 */
class FlushActivityLog extends Command
{
    protected $signature = 'activity:flush
        {--chunk= : Rows per batch (default from config)}
        {--max-chunks= : Stop after this many batches}';

    protected $description = 'Move buffered activity rows from Redis into the activity_log table';

    public function handle(ActivityRecorder $recorder): int
    {
        $key = config('activity-log.buffer_key', 'activity:buffer');
        $chunk = (int) ($this->option('chunk') ?: config('activity-log.flush_chunk', 1000));
        $maxChunks = $this->option('max-chunks') !== null ? (int) $this->option('max-chunks') : null;

        $moved = 0;
        $batches = 0;

        while ($maxChunks === null || $batches < $maxChunks) {
            try {
                $payloads = Redis::lrange($key, 0, $chunk - 1);
            } catch (Throwable $e) {
                // Nothing to flush is the normal case when Redis is not configured;
                // it is not an error and must not fail a scheduled run.
                $this->components->warn('Redis unavailable: '.$e->getMessage());

                return self::SUCCESS;
            }

            if ($payloads === []) {
                break;
            }

            $rows = [];

            foreach ($payloads as $payload) {
                $row = json_decode($payload, true);

                // A payload that will not decode is dropped rather than allowed to
                // wedge the batch for ever — but it is said out loud.
                if (is_array($row)) {
                    $rows[] = $row;
                } else {
                    $this->components->warn('Skipped an unreadable buffered row.');
                }
            }

            // Insert first, trim second. A crash between the two re-processes the
            // batch on the next run, which is at-least-once by design: a duplicate
            // row in an audit log is harmless where a lost one is not.
            $recorder->insert($rows);
            Redis::ltrim($key, count($payloads), -1);

            $moved += count($rows);
            $batches++;
        }

        if ($moved > 0) {
            $this->components->info("Moved {$moved} activity row(s) into the log.");
        }

        return self::SUCCESS;
    }
}
