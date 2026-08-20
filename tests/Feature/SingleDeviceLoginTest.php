<?php

use App\Models\AppSetting;
use App\Models\User;
use App\Services\DeviceSessionRegistry;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // The suite runs on the array driver by default, which cannot attribute a
    // session to a user; single-device sign-in is a database-session feature.
    config(['session.driver' => 'database']);
    $this->app->forgetInstance('session');
    $this->app->forgetInstance('session.store');

    $this->seed(RolePermissionSeeder::class);

    $this->user = User::factory()->create(['username' => 'clerk', 'password' => 'Secret@123']);
    $this->user->assignRole('Sales');

    AppSetting::current()->update(['single_device_login' => true]);
});

/** Insert a session row as though the user were signed in elsewhere. */
function otherDeviceSession(User $user, ?int $lastActivity = null, string $id = 'other-device-session'): string
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user->id,
        'ip_address' => '203.0.113.9',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0 Safari/537.36',
        'payload' => base64_encode(serialize([])),
        'last_activity' => $lastActivity ?? now()->getTimestamp(),
    ]);

    return $id;
}

it('signs in normally when no other device is active', function () {
    $this->post(route('login'), ['username' => 'clerk', 'password' => 'Secret@123'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($this->user);
});

it('asks what to do when the account is already signed in elsewhere', function () {
    otherDeviceSession($this->user);

    $this->post(route('login'), ['username' => 'clerk', 'password' => 'Secret@123'])
        ->assertRedirect(route('login.conflict'));

    // Credentials were correct, but nothing is signed in yet.
    $this->assertGuest();
});

it('shows the other device on the conflict screen', function () {
    otherDeviceSession($this->user);

    $this->post(route('login'), ['username' => 'clerk', 'password' => 'Secret@123']);

    $this->get(route('login.conflict'))
        ->assertOk()
        ->assertSee('Already signed in')
        ->assertSee('clerk')
        ->assertSee('Chrome on Windows')
        ->assertSee('203.0.113.9');
});

it('signs the other device out and continues', function () {
    $otherId = otherDeviceSession($this->user);

    $this->post(route('login'), ['username' => 'clerk', 'password' => 'Secret@123']);
    $this->post(route('login.conflict.resolve'), ['action' => 'continue'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($this->user);
    expect(DB::table('sessions')->where('id', $otherId)->exists())->toBeFalse();
});

it('leaves the other device alone when cancelled', function () {
    $otherId = otherDeviceSession($this->user);

    $this->post(route('login'), ['username' => 'clerk', 'password' => 'Secret@123']);
    $this->post(route('login.conflict.resolve'), ['action' => 'cancel'])
        ->assertRedirect(route('login'));

    $this->assertGuest();
    expect(DB::table('sessions')->where('id', $otherId)->exists())->toBeTrue();
});

it('cycles the remember token so remember-me cookies elsewhere stop working', function () {
    otherDeviceSession($this->user);
    $this->user->forceFill(['remember_token' => 'old-token-value'])->save();

    $this->post(route('login'), ['username' => 'clerk', 'password' => 'Secret@123']);
    $this->post(route('login.conflict.resolve'), ['action' => 'continue']);

    expect($this->user->fresh()->remember_token)->not->toBe('old-token-value');
});

it('ignores sessions that have already expired', function () {
    // Older than session.lifetime, so it is a dead row awaiting garbage collection.
    otherDeviceSession($this->user, now()->subMinutes(config('session.lifetime') + 10)->getTimestamp());

    $this->post(route('login'), ['username' => 'clerk', 'password' => 'Secret@123'])
        ->assertRedirect(route('dashboard'));
});

it('ignores another user\'s session', function () {
    $other = User::factory()->create();
    otherDeviceSession($other);

    $this->post(route('login'), ['username' => 'clerk', 'password' => 'Secret@123'])
        ->assertRedirect(route('dashboard'));
});

it('does not prompt when the feature is switched off', function () {
    AppSetting::current()->update(['single_device_login' => false]);
    otherDeviceSession($this->user);

    $this->post(route('login'), ['username' => 'clerk', 'password' => 'Secret@123'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($this->user);
});

it('still rejects a wrong password before any conflict is considered', function () {
    otherDeviceSession($this->user);

    $this->post(route('login'), ['username' => 'clerk', 'password' => 'wrong'])
        ->assertSessionHasErrors('username');

    $this->assertGuest();
});

it('sends someone with no pending sign-in back to the login form', function () {
    $this->get(route('login.conflict'))->assertRedirect(route('login'));

    $this->post(route('login.conflict.resolve'), ['action' => 'continue'])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('username');
});

it('expires a pending decision that is left too long', function () {
    otherDeviceSession($this->user);

    $this->post(route('login'), ['username' => 'clerk', 'password' => 'Secret@123']);

    $this->travel(6)->minutes();

    $this->post(route('login.conflict.resolve'), ['action' => 'continue'])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('username');

    $this->assertGuest();
});

it('reports the registry as unsupported on a non-database driver', function () {
    config(['session.driver' => 'file']);

    $registry = new DeviceSessionRegistry;

    expect($registry->isSupported())->toBeFalse()
        ->and($registry->hasOtherSessions($this->user))->toBeFalse()
        ->and($registry->logoutOthers($this->user))->toBe(0);
});
