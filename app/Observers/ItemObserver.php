<?php

namespace App\Observers;

use App\Models\Item;
use App\Models\ItemLot;

/**
 * Keeps a lot's status in step with the items created against it.
 *
 * An observer rather than inline calls because items are created from several
 * places — the single item form, the lot entry screen, and anything added later —
 * and deleting an item has to roll a finished lot back to in progress.
 */
class ItemObserver
{
    public function created(Item $item): void
    {
        $item->itemLot?->refreshStatus();
    }

    public function deleted(Item $item): void
    {
        $item->itemLot?->refreshStatus();
    }

    public function restored(Item $item): void
    {
        $item->itemLot?->refreshStatus();
    }

    /**
     * Moving an item between lots has to settle both of them.
     */
    public function updated(Item $item): void
    {
        if ($item->wasChanged('item_lot_id')) {
            $item->itemLot?->refreshStatus();

            $previousId = $item->getOriginal('item_lot_id');

            if ($previousId) {
                ItemLot::find($previousId)?->refreshStatus();
            }
        }
    }
}
