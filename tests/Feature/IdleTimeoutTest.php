<?php

use App\Http\Middleware\EnforceIdleTimeout;
use App\Models\AppSetting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('Sales');
});

it('does nothing when the timeout is off', function () {
    AppSetting::current()->update(['idle_timeout_minutes' => 0]);

    $this->actingAs($this->user)
        ->withSession([EnforceIdleTimeout::LAST_ACTIVITY_KEY => now()->subDay()->getTimestamp()])
        ->get(route('dashboard'))
        ->assertOk();

    $this->assertAuthenticatedAs($this->user);
});

it('signs the user out once the idle limit is passed', function () {
    AppSetting::current()->update(['idle_timeout_minutes' => 15]);

    $this->actingAs($this->user)
        ->withSession([EnforceIdleTimeout::LAST_ACTIVITY_KEY => now()->subMinutes(16)->getTimestamp()])
        ->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'You were signed out after a period of inactivity.');

    $this->assertGuest();
});

it('lets a request through just inside the limit and refreshes the clock', function () {
    AppSetting::current()->update(['idle_timeout_minutes' => 15]);

    $this->actingAs($this->user)
        ->withSession([EnforceIdleTimeout::LAST_ACTIVITY_KEY => now()->subMinutes(14)->getTimestamp()])
        ->get(route('dashboard'))
        ->assertOk();

    $this->assertAuthenticatedAs($this->user);

    // The clock was pushed forward, so the next request is not near the limit.
    expect(session(EnforceIdleTimeout::LAST_ACTIVITY_KEY))
        ->toBeGreaterThanOrEqual(now()->subSeconds(5)->getTimestamp());
});

it('keeps a busy session alive across successive requests', function () {
    AppSetting::current()->update(['idle_timeout_minutes' => 1]);

    $this->actingAs($this->user);

    foreach (range(1, 3) as $ignored) {
        $this->travel(50)->seconds();
        $this->get(route('dashboard'))->assertOk();
    }

    // Three minutes of wall clock, never idle for a full minute.
    $this->assertAuthenticatedAs($this->user);
});

it('answers a timed-out ajax request with 401 and a redirect target', function () {
    AppSetting::current()->update(['idle_timeout_minutes' => 5]);

    $this->actingAs($this->user)
        ->withSession([EnforceIdleTimeout::LAST_ACTIVITY_KEY => now()->subMinutes(6)->getTimestamp()])
        ->getJson(route('items.index'))
        ->assertStatus(401)
        ->assertJson(['redirect' => route('login')]);
});

it('leaves guests alone', function () {
    AppSetting::current()->update(['idle_timeout_minutes' => 1]);

    $this->withSession([EnforceIdleTimeout::LAST_ACTIVITY_KEY => now()->subHour()->getTimestamp()])
        ->get(route('login'))
        ->assertOk();
});

it('starts the idle clock at login', function () {
    AppSetting::current()->update(['idle_timeout_minutes' => 15]);

    $user = User::factory()->create(['username' => 'clerk', 'password' => 'Secret@123']);
    $user->assignRole('Sales');

    $this->post(route('login'), ['username' => 'clerk', 'password' => 'Secret@123'])
        ->assertRedirect(route('dashboard'));

    expect(session(EnforceIdleTimeout::LAST_ACTIVITY_KEY))->not->toBeNull();
});

// --- heartbeat ---------------------------------------------------------------

it('refreshes the clock on a heartbeat', function () {
    AppSetting::current()->update(['idle_timeout_minutes' => 15]);

    $this->actingAs($this->user)
        ->withSession([EnforceIdleTimeout::LAST_ACTIVITY_KEY => now()->subMinutes(14)->getTimestamp()])
        ->postJson(route('session.heartbeat'))
        ->assertOk()
        ->assertJson(['ok' => true, 'timeout_seconds' => 900]);

    expect(session(EnforceIdleTimeout::LAST_ACTIVITY_KEY))
        ->toBeGreaterThanOrEqual(now()->subSeconds(5)->getTimestamp());
});

it('cannot revive an already expired session with a heartbeat', function () {
    AppSetting::current()->update(['idle_timeout_minutes' => 15]);

    $this->actingAs($this->user)
        ->withSession([EnforceIdleTimeout::LAST_ACTIVITY_KEY => now()->subMinutes(20)->getTimestamp()])
        ->postJson(route('session.heartbeat'))
        ->assertStatus(401);

    $this->assertGuest();
});

it('requires authentication for the heartbeat', function () {
    $this->postJson(route('session.heartbeat'))->assertStatus(401);
});

// --- the browser-side watcher ------------------------------------------------

it('injects the countdown only when a timeout is configured', function () {
    AppSetting::current()->update(['idle_timeout_minutes' => 0]);

    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('idle-warning-modal');

    AppSetting::current()->update(['idle_timeout_minutes' => 10, 'idle_warning_seconds' => 45]);

    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('idle-warning-modal')
        ->assertSee('TIMEOUT_MS = 600 * 1000', false)
        ->assertSee('WARNING_MS = 45 * 1000', false)
        // Mouse and keyboard both count as activity.
        ->assertSee("'mousemove', 'mousedown', 'keydown'", false);
});

it('never lets the warning outlast the timeout', function () {
    // 1 minute timeout with a 90 second warning would show it immediately.
    AppSetting::current()->forceFill([
        'idle_timeout_minutes' => 1,
        'idle_warning_seconds' => 90,
    ])->save();

    expect(AppSetting::current()->idleWarningSeconds())->toBe(59);
});
