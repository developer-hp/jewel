<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * The day opening, at 11:30 shop time.
 *
 * The app runs on Asia/Kolkata, so this needs no timezone of its own — 11:30 here is
 * 11:30 at the counter.
 *
 * The command refuses to do anything unless auto opening is switched on, so this is
 * scheduled unconditionally and the setting decides. That keeps the decision in one
 * place, and means turning it on does not need a deploy.
 *
 * withoutOverlapping: the opening renders three PDFs and queues WhatsApp messages, so
 * a slow run must not have the next one start on top of it and close the day twice.
 */
Schedule::command('opening:run')
    ->dailyAt('11:30')
    ->withoutOverlapping()
    ->onOneServer();

/*
 * Move buffered activity rows out of Redis and into the log table.
 *
 * The activity screen also flushes a couple of batches when it is opened, so this is
 * the bulk carrier rather than the only one — a page nobody visits for a week still
 * has its rows written down.
 *
 * withoutOverlapping: a big backlog takes a while, and two flushes racing on the same
 * list would insert the same batch twice.
 */
Schedule::command('activity:flush')
    ->everyFourHours()
    ->withoutOverlapping()
    ->onOneServer();
