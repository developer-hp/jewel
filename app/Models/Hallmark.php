<?php

namespace App\Models;

use App\Models\Concerns\HasPhoto;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * A lot of goods sent for BIS hallmarking.
 *
 * Every total on the docket is derived from the lines; nothing summed is stored, so
 * a line edit can never leave a stale figure behind.
 */
#[Fillable(['hallmark_date', 'cost_per_piece', 'gross_weight'])]
class Hallmark extends Model
{
    use HasPhoto, SoftDeletes;

    protected function casts(): array
    {
        return [
            'hallmark_date' => 'date',
            'lot_no' => 'integer',
            'cost_per_piece' => 'decimal:2',
            'gross_weight' => 'decimal:3',
        ];
    }

    public function photoDirectory(): string
    {
        return 'hallmarks';
    }

    public function lines(): HasMany
    {
        return $this->hasMany(HallmarkLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Reserve the next lot number.
     *
     * Takes the settings row under a lock, so two clerks saving at the same moment
     * cannot be handed the same number. Must run inside a transaction for the lock
     * to hold; the unique index on lot_no is the backstop.
     */
    public static function nextLotNo(): int
    {
        // Make sure the singleton exists before locking it — lockForUpdate on a
        // missing row locks nothing.
        AppSetting::current();

        $settings = AppSetting::query()->lockForUpdate()->firstOrFail();

        $lotNo = max(1, (int) $settings->hallmark_next_lot_no);

        $settings->forceFill(['hallmark_next_lot_no' => $lotNo + 1])->save();

        return $lotNo;
    }

    // --- derived totals ----------------------------------------------------

    /**
     * Sum of the QUANTITY column — the left-hand total on the docket.
     */
    public function totalQuantity(): int
    {
        return (int) $this->lines->sum('quantity');
    }

    /**
     * Sum of TOTAL PCS, which is what the cost is charged on.
     */
    public function totalPieces(): int
    {
        return (int) $this->lines->sum(fn (HallmarkLine $line) => $line->totalPcs());
    }

    public function totalCost(): float
    {
        return round($this->totalPieces() * (float) $this->cost_per_piece, 2);
    }

    /**
     * The docket photo as a data URI, so dompdf renders it without reaching for
     * the filesystem or the network.
     */
    public function photoDataUri(): ?string
    {
        if (! $this->hasPhoto()) {
            return null;
        }

        $disk = $this->photo_disk ?: 'public';

        if (! config("filesystems.disks.{$disk}")) {
            return null;
        }

        $storage = Storage::disk($disk);

        if (! $storage->exists($this->photo_path)) {
            return null;
        }

        return 'data:'.$storage->mimeType($this->photo_path).';base64,'
            .base64_encode($storage->get($this->photo_path));
    }
}
