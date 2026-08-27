<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's saved note-count for the cash calculator.
 *
 * `user_id` is deliberately NOT fillable: it is set from the session, never from a
 * request, or one clerk could overwrite another's count by posting an id.
 *
 * Nothing derived is stored. The counts go in; every total is worked out by
 * totals() from them, so a stored figure can never disagree with the notes it came
 * from.
 */
#[Fillable(['counts'])]
class CashCalculator extends Model
{
    /**
     * The notes counted, largest first.
     *
     * The screenshot this was built from stops at 10; coins are not worth a row on a
     * jeweller's till. One edit here changes every row on the modal and the totals
     * with it.
     */
    public const DENOMINATIONS = [500, 200, 100, 50, 20, 10];

    /**
     * The two tallies counted side by side. key => label.
     */
    public const COLUMNS = [
        'counter' => 'Counter',
        'safe' => 'Safe',
    ];

    protected function casts(): array
    {
        return ['counts' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The saved counts, filled out to every column and denomination.
     *
     * The stored payload is whatever was last saved; a denomination added to the
     * constant above must still render, so the shape is rebuilt here rather than
     * trusted.
     *
     * @return array<string, array<int, int>>
     */
    public function grid(): array
    {
        return static::normalise($this->counts ?? []);
    }

    /**
     * Coerce any payload into the exact shape the modal expects.
     *
     * Anything unrecognised is dropped rather than kept: a denomination that is no
     * longer offered must not go on contributing to a total nobody can see.
     *
     * @param  array<mixed>  $raw
     * @return array<string, array<int, int>>
     */
    public static function normalise(array $raw): array
    {
        $grid = [];

        foreach (array_keys(self::COLUMNS) as $column) {
            foreach (self::DENOMINATIONS as $note) {
                $value = $raw[$column][$note] ?? $raw[$column][(string) $note] ?? 0;

                // Negative notes are not a thing; a blank box is zero, not null.
                $grid[$column][$note] = max(0, (int) $value);
            }
        }

        return $grid;
    }

    /**
     * What the counted notes come to: each column, and the two together.
     *
     * @param  array<string, array<int, int>>  $grid
     * @return array{columns: array<string, float>, total: float}
     */
    public static function totals(array $grid): array
    {
        $columns = [];

        foreach ($grid as $column => $notes) {
            $columns[$column] = (float) collect($notes)
                ->reduce(fn (float $sum, int $count, int $note) => $sum + ($note * $count), 0.0);
        }

        return [
            'columns' => $columns,
            'total' => array_sum($columns),
        ];
    }
}
