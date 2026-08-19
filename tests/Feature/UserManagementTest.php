<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

it('blocks a user without user.view from the user list', function () {
    $sales = User::factory()->create();
    $sales->assignRole('Sales');

    $this->actingAs($sales)->get(route('users.index'))->assertForbidden();
});

it('allows a user with user.view to see the list', function () {
    $this->actingAs($this->admin)->get(route('users.index'))->assertOk();
});

it('creates a user with roles attached', function () {
    $this->actingAs($this->admin)->post(route('users.store'), [
        'name' => 'Ravi B',
        'username' => 'ravi',
        'email' => 'ravi@example.com',
        'password' => 'Secret@12345',
        'password_confirmation' => 'Secret@12345',
        'is_active' => '1',
        'roles' => ['Sales'],
    ])->assertRedirect(route('users.index'));

    $user = User::where('username', 'ravi')->firstOrFail();

    expect($user->hasRole('Sales'))->toBeTrue()
        ->and($user->is_active)->toBeTrue();
});

it('keeps the current password when the field is left blank on update', function () {
    $user = User::factory()->create(['username' => 'ravi']);
    $original = $user->password;

    $this->actingAs($this->admin)->put(route('users.update', $user), [
        'name' => 'Ravi Updated',
        'username' => 'ravi',
        'password' => '',
        'password_confirmation' => '',
        'is_active' => '1',
        'roles' => ['Sales'],
    ])->assertRedirect(route('users.index'));

    expect($user->fresh()->password)->toBe($original)
        ->and($user->fresh()->name)->toBe('Ravi Updated');
});

it('rejects a duplicate username', function () {
    User::factory()->create(['username' => 'ravi']);

    $this->actingAs($this->admin)->post(route('users.store'), [
        'name' => 'Someone Else',
        'username' => 'ravi',
        'password' => 'Secret@12345',
        'password_confirmation' => 'Secret@12345',
    ])->assertSessionHasErrors('username');
});

it('stops a user from deleting their own account', function () {
    $this->actingAs($this->admin)->delete(route('users.destroy', $this->admin))
        ->assertSessionHas('error');

    expect(User::find($this->admin->id))->not->toBeNull();
});

it('stops a non super admin from editing a super admin', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('Super Admin');

    $this->actingAs($this->admin)->get(route('users.edit', $superAdmin))->assertForbidden();
});

it('does not let a non super admin grant the super admin role', function () {
    $this->actingAs($this->admin)->post(route('users.store'), [
        'name' => 'Sneaky',
        'username' => 'sneaky',
        'password' => 'Secret@12345',
        'password_confirmation' => 'Secret@12345',
        'roles' => ['Super Admin'],
    ])->assertRedirect(route('users.index'));

    expect(User::where('username', 'sneaky')->firstOrFail()->hasRole('Super Admin'))->toBeFalse();
});

it('toggles a user status', function () {
    $user = User::factory()->create(['is_active' => true]);

    $this->actingAs($this->admin)->post(route('users.toggle-status', $user));

    expect($user->fresh()->is_active)->toBeFalse();
});
