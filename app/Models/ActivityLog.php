<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * One recorded activity.
 *
 * Extends the package's model so `config('activitylog.activity_model')` can point
 * here; everything below is for the screen. Nothing writes through this class — rows
 * are inserted by ActivityRecorder with the query builder, because an Eloquent create
 * here would fire the very listeners that produced it and log the log.
 */
class ActivityLog extends Activity
{
    /** The log names, and how each is labelled and coloured on the listing. */
    public const LOGS = [
        'data' => ['Data', 'bg-primary'],
        'auth' => ['Sign in', 'bg-warning'],
        'print' => ['Print', 'bg-info'],
        'page' => ['Page', 'bg-secondary'],
    ];

    public function scopeOfLog(Builder $query, ?string $log): void
    {
        $query->when($log, fn (Builder $q) => $q->where('log_name', $log));
    }

    /**
     * Rows inside a date range, either end optional.
     */
    public function scopeBetweenDates(Builder $query, ?string $from, ?string $to): void
    {
        $query
            ->when($from, fn (Builder $q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('created_at', '<=', $to));
    }

    /**
     * The field-level diff, as [field => ['old' => …, 'new' => …]].
     *
     * Always an array, so the detail view never has to guard against a null column or
     * a row written before this shape settled.
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function changes(): array
    {
        return $this->jsonColumn($this->attribute_changes);
    }

    /**
     * Extra context — the IP, the printed view, the attempted username.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->jsonColumn($this->properties);
    }

    /**
     * Read one of the json columns as a plain array.
     *
     * The parent casts both to `collection`, so that is the shape they normally
     * arrive in — but a row read straight off the query builder hands back a string,
     * and an empty column hands back null. All three have to give an array, because
     * the views loop over the result without guarding.
     *
     * @return array<string, mixed>
     */
    private function jsonColumn(mixed $raw): array
    {
        if ($raw instanceof Collection) {
            return $raw->toArray();
        }

        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        return is_array($raw) ? $raw : [];
    }

    /**
     * What the row was about, in words.
     *
     * The subject columns carry no foreign key, so a row outlives the record it
     * describes — which is the point of a log. When the subject is gone the class
     * name and id are all there is, and that is what gets shown rather than a blank
     * cell or an error.
     */
    public function subjectLabel(): string
    {
        if (! $this->subject_type) {
            return '—';
        }

        $name = class_basename($this->subject_type);

        // Loading the subject would be a query per row on the listing, so this only
        // reads what is already on the log row itself.
        return $this->subject_id ? $name.' #'.$this->subject_id : $name;
    }

    /**
     * [label, css class] for the type badge, for a log name we may no longer serve.
     *
     * @return array{0: string, 1: string}
     */
    public function logBadge(): array
    {
        return self::LOGS[$this->log_name] ?? [ucfirst((string) $this->log_name), 'bg-dark'];
    }
}
