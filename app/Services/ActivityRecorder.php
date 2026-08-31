<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * The one place an activity row is written.
 *
 * Every capture point — the Eloquent listeners, the auth listeners, PdfDocument and
 * the page-view middleware — comes through here, so the buffering decision, the
 * redaction and the fallback are each stated once instead of four times.
 *
 * High-volume rows go onto a Redis list and are moved into MySQL by `activity:flush`.
 * Everything else, and everything at all when Redis is unavailable, is inserted
 * straight away.
 */
class ActivityRecorder
{
    public const TABLE = 'activity_log';

    /**
     * Whether Redis answered this request. Null until asked.
     *
     * Memoised because a dead Redis would otherwise cost a failed connection attempt
     * on every single page view — which is exactly the case the buffer exists to make
     * cheap.
     */
    private ?bool $redisUp = null;

    /**
     * Record one activity.
     *
     * @param  string  $log  'data' | 'auth' | 'print' | 'page'
     * @param  array<string, mixed>  $properties
     * @param  array<string, mixed>  $changes  [field => ['old' => …, 'new' => …]]
     */
    public function record(
        string $log,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        array $changes = [],
        ?string $event = null,
    ): void {
        if (! config('activity-log.enabled', true)) {
            return;
        }

        $row = $this->row($log, $description, $subject, $properties, $changes, $event);

        if ($this->buffers($log) && $this->push($row)) {
            return;
        }

        $this->insert([$row]);
    }

    /**
     * Build the complete row, ready to insert now or in four hours' time.
     *
     * The causer is resolved HERE, at write time, and never at flush time. A flush
     * runs in a console process with no session behind it, so a row that waited for
     * one would be recorded as nobody. This is the most important line in the class.
     *
     * @param  array<string, mixed>  $properties
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function row(
        string $log,
        string $description,
        ?Model $subject,
        array $properties,
        array $changes,
        ?string $event,
    ): array {
        $causer = auth()->user();
        $now = now()->toDateTimeString();

        return [
            'log_name' => $log,
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'event' => $event,
            // Null is correct and means the scheduler or an artisan command did it.
            'causer_type' => $causer?->getMorphClass(),
            'causer_id' => $causer?->getKey(),
            'attribute_changes' => $changes === [] ? null : json_encode($changes),
            'properties' => $properties === [] ? null : json_encode($properties),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Is this log name worth putting through Redis?
     */
    private function buffers(string $log): bool
    {
        if (config('activity-log.buffer') !== 'redis') {
            return false;
        }

        if (! in_array($log, (array) config('activity-log.buffered_logs', []), true)) {
            return false;
        }

        return $this->redisAvailable();
    }

    private function redisAvailable(): bool
    {
        if ($this->redisUp !== null) {
            return $this->redisUp;
        }

        try {
            Redis::connection()->ping();

            return $this->redisUp = true;
        } catch (Throwable) {
            // Includes "Class \"Redis\" not found" when REDIS_CLIENT names a driver
            // whose extension is not installed — a misconfiguration must degrade to
            // direct writes, not to lost rows.
            return $this->redisUp = false;
        }
    }

    /**
     * Push onto the buffer. False means the caller should insert directly instead.
     *
     * @param  array<string, mixed>  $row
     */
    private function push(array $row): bool
    {
        $key = config('activity-log.buffer_key', 'activity:buffer');

        try {
            // A list nobody is draining should not be allowed to eat the instance.
            if (Redis::llen($key) >= (int) config('activity-log.buffer_max', 50000)) {
                return false;
            }

            Redis::rpush($key, json_encode($row));

            return true;
        } catch (Throwable) {
            // Fall back within the same call, not on a later retry. A log that drops
            // rows because Redis hiccuped is worse than one that is slow.
            $this->redisUp = false;

            return false;
        }
    }

    /**
     * How many rows are waiting to be flushed.
     */
    public function pending(): int
    {
        if (! $this->redisAvailable()) {
            return 0;
        }

        try {
            return (int) Redis::llen(config('activity-log.buffer_key', 'activity:buffer'));
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Write rows to the table.
     *
     * A query-builder insert, not the model: a thousand rows is one statement, and no
     * Eloquent event fires — creating an ActivityLog through Eloquent would re-enter
     * the listeners and log the log.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function insert(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        DB::table(self::TABLE)->insert($rows);
    }
}
