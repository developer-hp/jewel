<?php

use App\Models\ActivityLog;
use App\Models\CashCalculator;
use Illuminate\Notifications\DatabaseNotification;
use Spatie\Activitylog\Models\Activity;

/*
|--------------------------------------------------------------------------
| Activity Log
|--------------------------------------------------------------------------
|
| Our own settings, alongside the package's config/activitylog.php. Everything
| about what gets recorded and where it goes first lives here, so turning the log
| down is one file rather than six edits across the codebase.
|
| Plain arrays only, no closures, so `php artisan config:cache` still works.
|
*/

return [

    /*
    | Master switch. Off, nothing anywhere records anything.
    */
    'enabled' => env('ACTIVITY_LOG_ENABLED', true),

    /*
    | Page views are one row on nearly every request and by far the biggest part of
    | the table. Their own switch, so the noisiest half can go without losing the
    | audit trail.
    */
    'log_page_views' => env('ACTIVITY_LOG_PAGE_VIEWS', true),

    /*
    |----------------------------------------------------------------------
    | The Redis buffer
    |----------------------------------------------------------------------
    |
    | 'redis' pushes the high-volume rows onto a list and lets `activity:flush`
    | move them into MySQL on a schedule; 'off' writes straight through.
    |
    | Either way a Redis failure falls back to a direct insert in the same call —
    | a log that drops rows because Redis hiccuped is worse than a slow one.
    */
    'buffer' => env('ACTIVITY_LOG_BUFFER', 'redis'),

    'buffer_key' => 'activity:buffer',

    /*
    | Which log names are worth buffering.
    |
    | `auth` and `print` are deliberately absent. They are the two rows you least
    | want to lose and the two there are fewest of — a handful a day against
    | thousands of page views — so they are written straight to MySQL, where a
    | Redis restart cannot take them.
    */
    'buffered_logs' => ['data', 'page'],

    /*
    | Past this many rows the buffer stops accepting and records go direct. A list
    | nobody is draining should not be allowed to eat the whole Redis instance.
    */
    'buffer_max' => 50000,

    /*
    | How many rows one flush batch carries. One bulk insert per batch.
    */
    'flush_chunk' => 1000,

    /*
    |----------------------------------------------------------------------
    | What not to record
    |----------------------------------------------------------------------
    |
    | Models whose writes are never interesting. The package's own Activity is
    | here for the obvious reason; CashCalculator is a per-user scratchpad that
    | autosaves as the notes are typed.
    */
    'ignore' => [
        Activity::class,
        ActivityLog::class,
        CashCalculator::class,
        DatabaseNotification::class,
    ],

    /*
    | Attributes dropped from the recorded properties entirely — not masked,
    | dropped. A password hash must never be written down, and "we starred it out"
    | is not the same promise as "it was never stored".
    */
    'redact' => [
        'password',
        'password_confirmation',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'api_token',
    ],

    /*
    | Attributes stripped from the diff as noise rather than as secrets. The
    | timestamps move on every single update and the log row carries its own
    | created_at, so recording them again says nothing.
    */
    'ignore_attributes' => [
        'created_at',
        'updated_at',
    ],

    /*
    | Request paths that never count as a page view. The heartbeat fires on a timer
    | and would out-number every real page on the site.
    */
    'skip_paths' => [
        'session/heartbeat',
        'up',
    ],

];
