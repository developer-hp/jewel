<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('redirects guests to the login page', function () {
    $this->get('/')->assertRedirect(route('login'));
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('logs a user in with a valid username and password', function () {
    $user = User::factory()->create([
        'username' => 'ravi',
        'password' => Hash::make('Secret@123'),
    ]);

    $this->post(route('login'), ['username' => 'ravi', 'password' => 'Secret@123'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('rejects a wrong password', function () {
    User::factory()->create(['username' => 'ravi', 'password' => Hash::make('Secret@123')]);

    $this->post(route('login'), ['username' => 'ravi', 'password' => 'wrong'])
        ->assertSessionHasErrors('username');

    $this->assertGuest();
});

it('refuses to log in a deactivated user and says so', function () {
    User::factory()->create([
        'username' => 'ravi',
        'password' => Hash::make('Secret@123'),
        'is_active' => false,
    ]);

    $this->post(route('login'), ['username' => 'ravi', 'password' => 'Secret@123'])
        ->assertSessionHasErrors(['username' => 'Your account has been deactivated. Please contact an administrator.']);

    $this->assertGuest();
});

it('does not reveal that a deactivated account exists when the password is wrong', function () {
    User::factory()->create([
        'username' => 'ravi',
        'password' => Hash::make('Secret@123'),
        'is_active' => false,
    ]);

    $this->post(route('login'), ['username' => 'ravi', 'password' => 'wrong'])
        ->assertSessionHasErrors(['username' => 'These credentials do not match our records.']);
});

it('throttles after five failed attempts', function () {
    User::factory()->create(['username' => 'ravi', 'password' => Hash::make('Secret@123')]);

    foreach (range(1, 5) as $ignored) {
        $this->post(route('login'), ['username' => 'ravi', 'password' => 'wrong']);
    }

    // The 6th attempt is locked out even though the password is now correct.
    $this->post(route('login'), ['username' => 'ravi', 'password' => 'Secret@123'])
        ->assertInvalid(['username' => 'Too many login attempts']);

    $this->assertGuest();
});

it('logs the user out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));

    $this->assertGuest();
});

it('shows the dashboard to an authenticated user', function () {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee($user->name);
});
