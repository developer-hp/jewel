<?php

/*
|--------------------------------------------------------------------------
| Dashboard Sections
|--------------------------------------------------------------------------
|
| The dashboard is built from this list, in this order.
|
| Per entry:
|   key    stable identifier — what gets stored when a section is hidden, so
|          renaming a label is safe but renaming a key is not
|   label  shown on the Appearance tick list
|   hint   one line under that label, explaining what the section holds
|   can    permission required to see it; null means the section decides for
|          itself, by filtering its own contents
|
| A section renders only when all three hold: it is not hidden, the viewer has
| its permission, and it has something to show. Adding one here is the only
| change needed — Appearance, the dashboard and the update rules all read it.
|
| Plain arrays only — no closures — so `php artisan config:cache` still works.
|
*/

return [

    [
        'key' => 'rates',
        'label' => "Today's Rates",
        'hint' => 'The rate per gram for each purity, and a warning for any still unset.',
        'can' => 'metal_rate.view',
    ],

    [
        'key' => 'attention',
        'label' => 'Needs Attention',
        'hint' => 'Repairs and orders overdue or due today, followups, unprinted slips.',
        // Composite: each line is gated on its own module, so it filters itself.
        'can' => null,
    ],

    [
        'key' => 'quick_actions',
        'label' => 'Quick Actions',
        'hint' => 'Buttons for the things started most often.',
        'can' => null,
    ],

    [
        'key' => 'stock',
        'label' => 'Stock at a Glance',
        'hint' => 'Total pieces and weight, broken down by stock group.',
        'can' => 'stock.view',
    ],

    [
        'key' => 'internal_stock',
        'label' => 'Internal Stock',
        'hint' => 'What each internal pot is holding.',
        'can' => 'internal_stock_entry.view',
    ],

    [
        'key' => 'progress',
        'label' => 'Repairs & Orders',
        'hint' => 'How much of each is still outstanding.',
        'can' => null,
    ],

    [
        'key' => 'hisab',
        'label' => "Today's Supplier Hisab",
        'hint' => "The day's fine and cash totals.",
        'can' => 'supplier_hisab.view',
    ],

    [
        'key' => 'recent',
        'label' => 'Recent Activity',
        'hint' => 'The last few things recorded across the shop.',
        'can' => null,
    ],

];
