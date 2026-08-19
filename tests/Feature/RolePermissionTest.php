<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

it('seeds the expected roles and permissions', function () {
    expect(Role::pluck('name')->all())
        ->toContain('Super Admin', 'Admin', 'Manager', 'Sales')
        ->and(Permission::count())->toBeGreaterThan(30);
});

it('gives a super admin every ability without explicit permissions', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('Super Admin');

    expect($superAdmin->can('quotation.approve'))->toBeTrue()
        ->and($superAdmin->can('some.permission.that.does.not.exist'))->toBeTrue();
});

it('creates a role with the selected permissions', function () {
    $this->actingAs($this->admin)->post(route('roles.store'), [
        'name' => 'Counter Staff',
        'permissions' => ['quotation.view', 'quotation.create'],
    ])->assertRedirect(route('roles.index'));

    $role = Role::where('name', 'Counter Staff')->firstOrFail();

    expect($role->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(['quotation.view', 'quotation.create']);
});

it('applies a role permission change immediately', function () {
    $role = Role::create(['name' => 'Counter Staff', 'guard_name' => 'web']);
    $staff = User::factory()->create();
    $staff->assignRole($role);

    expect($staff->can('user.view'))->toBeFalse();

    $this->actingAs($this->admin)->put(route('roles.update', $role), [
        'name' => 'Counter Staff',
        'permissions' => ['user.view'],
    ]);

    expect($staff->fresh()->can('user.view'))->toBeTrue();
});

it('refuses to edit or delete the super admin role', function () {
    $role = Role::where('name', 'Super Admin')->firstOrFail();

    $this->actingAs($this->admin)->get(route('roles.edit', $role))->assertForbidden();
    $this->actingAs($this->admin)->delete(route('roles.destroy', $role))->assertSessionHas('error');

    expect(Role::where('name', 'Super Admin')->exists())->toBeTrue();
});

it('refuses to delete a role that still has users', function () {
    $role = Role::where('name', 'Sales')->firstOrFail();
    User::factory()->create()->assignRole($role);

    $this->actingAs($this->admin)->delete(route('roles.destroy', $role))->assertSessionHas('error');

    expect(Role::where('name', 'Sales')->exists())->toBeTrue();
});

it('creates a permission and rejects a badly named one', function () {
    $this->actingAs($this->admin)->post(route('permissions.store'), ['name' => 'repair.create'])
        ->assertRedirect(route('permissions.index'));

    expect(Permission::where('name', 'repair.create')->exists())->toBeTrue();

    $this->actingAs($this->admin)->post(route('permissions.store'), ['name' => 'Not Valid'])
        ->assertSessionHasErrors('name');
});

it('protects core rbac permissions from deletion', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('Super Admin');

    $permission = Permission::where('name', 'user.view')->firstOrFail();

    $this->actingAs($superAdmin)->delete(route('permissions.destroy', $permission))
        ->assertSessionHas('error');

    expect(Permission::where('name', 'user.view')->exists())->toBeTrue();
});

it('hides admin menu items from a user without the permissions', function () {
    $sales = User::factory()->create();
    $sales->assignRole('Sales');

    $this->actingAs($sales)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(route('users.index'))
        ->assertDontSee(route('roles.index'));
});
