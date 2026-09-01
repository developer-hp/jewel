<?php

use App\Models\ActivityLog;
use App\Models\CashCalculator;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\SalesPerson;
use App\Models\User;
use App\Services\ActivityRecorder;
use App\Services\DayOpening;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * The activity log: what it records, what it refuses to record, and how buffered rows
 * reach the table.
 *
 * The suite runs with ACTIVITY_LOG_ENABLED=false (see phpunit.xml) so 780 other tests
 * do not each write a pile of rows. This file turns it on for itself, and asserts
 * against MySQL with the buffer off unless a test says otherwise.
 */
beforeEach(function () {
    config([
        'activity-log.enabled' => true,
        'activity-log.buffer' => 'off',
    ]);

    $this->seed(RolePermissionSeeder::class);
    $this->seed(MasterDataSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    // Seeding writes hundreds of rows of its own; the tests care about what happens
    // after it.
    DB::table('activity_log')->truncate();
});

/**
 * Rows of one kind, newest first.
 *
 * Named distinctly: Pest's global helpers share one namespace across the whole suite,
 * and a redeclaration is a fatal, not a failure.
 */
function activityRows(?string $log = null)
{
    return ActivityLog::query()
        ->when($log, fn ($q) => $q->where('log_name', $log))
        ->orderByDesc('id')
        ->get();
}

/**
 * Is Redis actually reachable here?
 *
 * The buffer tests skip rather than fail when it is not: a developer without Redis
 * running should still get a green suite, and the fallback path has its own test that
 * always runs.
 */
function activityRedisUp(): bool
{
    try {
        Redis::connection()->ping();

        return true;
    } catch (Throwable) {
        return false;
    }
}

/** A piece in stock, so there is something to change. */
function activitySubject(): Item
{
    $group = ItemGroup::where('prefix', 'RNG')->firstOrFail();
    $gold = MetalType::where('code', 'GOLD')->firstOrFail();

    $item = new Item([
        'item_group_id' => $group->id,
        'metal_type_id' => $gold->id,
        'purity_id' => Purity::where('metal_type_id', $gold->id)->firstOrFail()->id,
        'name' => 'Solitaire Ring',
        'gross_weight' => 9,
        'other_deduction' => 0,
    ]);

    $item->code = $group->nextItemCode();
    $item->net_weight = 9;
    $item->save();

    return $item;
}

// --- data changes ------------------------------------------------------------------

it('records a create, an update and a delete', function () {
    $person = SalesPerson::create(['name' => 'Counter']);
    $person->update(['city' => 'Ahmedabad']);
    $person->delete();

    $events = activityRows('data')->pluck('event')->all();

    expect($events)->toContain('created')->toContain('updated')->toContain('deleted');
});

it('records only the fields that moved, with both sides', function () {
    $person = SalesPerson::create(['name' => 'Counter', 'city' => 'Surat']);
    DB::table('activity_log')->truncate();

    $person->update(['city' => 'Rajkot']);

    $changes = activityRows('data')->first()->changes();

    expect($changes)->toHaveKey('city')
        ->and($changes['city']['old'])->toBe('Surat')
        ->and($changes['city']['new'])->toBe('Rajkot')
        // The name did not move, and the timestamps are stripped as noise.
        ->and($changes)->not->toHaveKey('name')
        ->and($changes)->not->toHaveKey('updated_at');
});

/**
 * The one that matters most. A hash must never be written down anywhere — "we starred
 * it out" is a weaker promise than "it was never stored".
 */
it('never writes a password or its hash into the log', function () {
    $this->admin->update(['password' => bcrypt('a-very-secret-value')]);

    $rows = activityRows('data');
    $blob = $rows->toJson();

    expect($blob)->not->toContain('password')
        ->not->toContain('$2y$')
        ->not->toContain('a-very-secret-value')
        ->and($rows->first()->changes())->not->toHaveKey('password');
});

it('records nothing for a model on the ignore list', function () {
    $calculator = new CashCalculator(['counts' => ['counter' => [500 => 1]]]);
    $calculator->forceFill(['user_id' => $this->admin->id])->save();

    expect(activityRows())->toBeEmpty();
});

/**
 * Pins the named-event listeners against anybody "simplifying" them to `eloquent.*`,
 * which would also catch `retrieved` and log one row per hydrated record.
 */
it('does not log reading', function () {
    activitySubject();
    DB::table('activity_log')->truncate();

    Item::query()->get();
    Purity::query()->get();
    SalesPerson::query()->get();

    expect(activityRows())->toBeEmpty();
});

it('logs a change made with nobody signed in as having no causer', function () {
    auth()->logout();

    SalesPerson::create(['name' => 'From a command']);

    expect(activityRows('data')->first()->causer_id)->toBeNull();
});

// --- auth ---------------------------------------------------------------------------

it('records signing in and out', function () {
    $user = User::factory()->create(['username' => 'counter', 'password' => bcrypt('password')]);
    $user->assignRole('Sales');

    $this->post(route('login'), ['username' => 'counter', 'password' => 'password']);
    $this->actingAs($user)->post(route('logout'));

    $events = activityRows('auth')->pluck('event')->all();

    expect($events)->toContain('login')->toContain('logout');
});

it('records a failed attempt with the username tried and no causer', function () {
    $this->post(route('login'), ['username' => 'chancer', 'password' => 'wrong']);

    $row = activityRows('auth')->firstWhere('event', 'failed');

    expect($row)->not->toBeNull()
        ->and($row->causer_id)->toBeNull()
        ->and($row->context()['username'])->toBe('chancer');
});

// --- prints ---------------------------------------------------------------------------

it('records every pdf, because PdfDocument is the only way one is made', function () {
    $this->actingAs($this->admin)->get(route('stock.print'))->assertOk();

    $row = activityRows('print')->first();

    expect($row)->not->toBeNull()
        ->and($row->event)->toBe('print')
        ->and($row->causer_id)->toBe($this->admin->id)
        ->and($row->context()['bytes'])->toBeGreaterThan(0);
});

// --- page views -------------------------------------------------------------------

it('records a page a signed-in user looked at', function () {
    $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();

    $row = activityRows('page')->first();

    expect($row)->not->toBeNull()
        ->and($row->context()['route'])->toBe('dashboard');
});

it('leaves the noise out of page views', function (string $case) {
    match ($case) {
        // A guest has no identity worth recording, and the login screen would
        // otherwise be the most-viewed page in the log.
        'guest' => $this->get(route('login'))->assertOk(),
        // Every DataTables redraw is its own GET.
        'ajax' => $this->actingAs($this->admin)
            ->getJson(route('customers.index', dtParams(['name'])))->assertOk(),
        // The heartbeat fires on a timer.
        'heartbeat' => $this->actingAs($this->admin)->post(route('session.heartbeat')),
    };

    expect(activityRows('page'))->toBeEmpty();
})->with(['guest', 'ajax', 'heartbeat']);

// --- the Redis buffer -----------------------------------------------------------------

it('buffers a data row instead of inserting it, then flushes it across', function () {
    config(['activity-log.buffer' => 'redis', 'activity-log.buffer_key' => 'activity:test']);
    Redis::del('activity:test');

    $person = SalesPerson::create(['name' => 'Buffered']);

    // In Redis, not in MySQL.
    expect(activityRows())->toBeEmpty()
        ->and((int) Redis::llen('activity:test'))->toBe(1);

    $this->artisan('activity:flush')->assertSuccessful();

    $row = activityRows('data')->first();

    expect($row)->not->toBeNull()
        ->and($row->subject_id)->toBe($person->id)
        ->and((int) Redis::llen('activity:test'))->toBe(0);
})->skip(fn () => ! activityRedisUp(), 'Redis is not reachable.');

/**
 * The reason the causer is resolved at write time. A flush runs in a console process
 * with no session, so a row that waited for one would be recorded as nobody.
 */
it('keeps the causer across a flush', function () {
    config(['activity-log.buffer' => 'redis', 'activity-log.buffer_key' => 'activity:test']);
    Redis::del('activity:test');

    $this->actingAs($this->admin);
    SalesPerson::create(['name' => 'Buffered']);

    // Sign out entirely, exactly as the console has nobody signed in.
    auth()->logout();
    $this->artisan('activity:flush')->assertSuccessful();

    expect(activityRows('data')->first()->causer_id)->toBe($this->admin->id);
})->skip(fn () => ! activityRedisUp(), 'Redis is not reachable.');

it('never buffers an auth row', function () {
    config(['activity-log.buffer' => 'redis', 'activity-log.buffer_key' => 'activity:test']);
    Redis::del('activity:test');

    app(ActivityRecorder::class)->record(log: 'auth', description: 'Signed in', event: 'login');

    // Straight to MySQL: it is the row you least want a Redis restart to take.
    expect(activityRows('auth'))->toHaveCount(1)
        ->and((int) Redis::llen('activity:test'))->toBe(0);
})->skip(fn () => ! activityRedisUp(), 'Redis is not reachable.');

/**
 * With REDIS_CLIENT naming a driver whose extension is missing, every Redis call
 * throws — and this is the path the whole app runs on. It has to be the one that is
 * right by default.
 */
it('falls back to a direct insert when redis is unreachable', function () {
    config([
        'activity-log.buffer' => 'redis',
        'database.redis.client' => 'phpredis-does-not-exist',
    ]);

    app()->forgetInstance(ActivityRecorder::class);
    app(ActivityRecorder::class)->record(log: 'data', description: 'Fell back', event: 'created');

    expect(activityRows('data'))->toHaveCount(1);
});

// --- the screen -----------------------------------------------------------------------

it('lists the log', function () {
    activitySubject();

    $this->actingAs($this->admin)->get(route('activity-log.index'))
        ->assertOk()
        ->assertSee('Activity Log');

    $rows = $this->actingAs($this->admin)
        ->getJson(route('activity-log.index', dtParams(['created_at'])))
        ->assertOk()
        ->json('data');

    expect($rows)->not->toBeEmpty()
        ->and($rows[0])->toHaveKeys(['created_at', 'user', 'type', 'subject', 'summary', 'action']);
});

// A log row outlives the record it describes — that is the point of one.
it('still renders a row whose subject has been deleted', function () {
    $person = SalesPerson::create(['name' => 'Gone']);
    $person->forceDelete();

    $rows = $this->actingAs($this->admin)
        ->getJson(route('activity-log.index', dtParams(['created_at'])))
        ->assertOk()
        ->json('data');

    expect(collect($rows)->pluck('subject'))->toContain('SalesPerson #'.$person->id);
});

it('shows one row in full', function () {
    $person = SalesPerson::create(['name' => 'Counter', 'city' => 'Surat']);
    DB::table('activity_log')->truncate();
    $person->update(['city' => 'Rajkot']);

    $this->actingAs($this->admin)
        ->get(route('activity-log.show', activityRows('data')->first()))
        ->assertOk()
        ->assertSee('What changed')
        ->assertSee('Surat')
        ->assertSee('Rajkot');
});

it('reads the listing without a query per row', function () {
    foreach (range(1, 25) as $i) {
        SalesPerson::create(['name' => 'Person '.$i]);
    }

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $this->actingAs($this->admin)
        ->getJson(route('activity-log.index', dtParams(['created_at'])))
        ->assertOk();

    // Count, filtered count, the page, and the eager-loaded causers — not 25 lookups.
    expect($queries)->toBeLessThan(12);
});

// --- pruning ---------------------------------------------------------------------------

it('prunes only what is older than the date given', function () {
    $cutOff = now()->subMonth();

    SalesPerson::create(['name' => 'Old']);
    ActivityLog::query()->update(['created_at' => now()->subMonths(6)]);

    SalesPerson::create(['name' => 'New']);

    // Counted by age rather than in total: the request itself writes rows — the
    // global listener catches AppSetting::current() creating the settings row on a
    // fresh database, which is the listener doing exactly its job.
    $olderThan = fn () => ActivityLog::where('created_at', '<', $cutOff)->count();
    $newerThan = fn () => ActivityLog::where('created_at', '>=', $cutOff)->count();

    expect($olderThan())->toBe(1)
        ->and($newerThan())->toBeGreaterThan(0);

    $before = $newerThan();

    $this->actingAs($this->admin)
        ->delete(route('activity-log.prune'), ['before' => $cutOff->toDateString()])
        ->assertRedirect();

    expect($olderThan())->toBe(0)
        ->and($newerThan())->toBeGreaterThanOrEqual($before);
});

it('refuses a prune date in the future', function () {
    $this->actingAs($this->admin)
        ->delete(route('activity-log.prune'), ['before' => now()->addWeek()->toDateString()])
        ->assertSessionHasErrors('before');
});

// --- the log survives the day opening ---------------------------------------------------

/**
 * The day opening deletes estimates, angadiya slips and hisab. It must never take the
 * audit trail with them — this is the test that stops a future truncate from doing so.
 */
it('survives the day opening', function () {
    SalesPerson::create(['name' => 'Before the opening']);

    $before = activityRows()->count();

    expect($before)->toBeGreaterThan(0);

    app(DayOpening::class)->run();

    expect(activityRows()->count())->toBeGreaterThanOrEqual($before);
});

// --- permissions --------------------------------------------------------------------

it('lets a manager read the log but never prune it', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $this->actingAs($manager)->get(route('activity-log.index'))->assertOk();
    $this->actingAs($manager)
        ->delete(route('activity-log.prune'), ['before' => now()->subMonth()->toDateString()])
        ->assertForbidden();
});

it('keeps the log away from anyone without the permission', function () {
    $sales = User::factory()->create();
    $sales->assignRole('Sales');

    $this->actingAs($sales)->get(route('activity-log.index'))->assertForbidden();
    $this->actingAs($sales)
        ->delete(route('activity-log.prune'), ['before' => now()->subMonth()->toDateString()])
        ->assertForbidden();
});
