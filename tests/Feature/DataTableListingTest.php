<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

/**
 * Minimal DataTables query string. Column metadata must be present or the
 * server-side handler cannot resolve searching and ordering.
 *
 * @param  array<int, string>  $columns
 * @return array<string, mixed>
 */
function dtParams(array $columns, array $overrides = []): array
{
    $params = [
        'draw' => 1,
        'start' => 0,
        'length' => 10,
        'search' => ['value' => '', 'regex' => 'false'],
        'columns' => collect($columns)->map(fn (string $name) => [
            'data' => $name,
            'name' => $name,
            'searchable' => 'true',
            'orderable' => 'true',
            'search' => ['value' => '', 'regex' => 'false'],
        ])->all(),
    ];

    return array_replace_recursive($params, $overrides);
}

it('returns a datatables payload for the user listing', function () {
    User::factory()->count(5)->create();

    $response = $this->actingAs($this->admin)
        ->getJson(route('users.index', dtParams(['user', 'username', 'contact'])));

    // 5 factory users + the acting admin + the seeded superadmin.
    $response->assertOk()
        ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data'])
        ->assertJsonPath('recordsTotal', User::count());

    expect($response->json('data.0'))->toHaveKeys(['user', 'username', 'contact', 'roles', 'status', 'action']);
});

it('searches users across name, username, email and phone', function () {
    User::factory()->create([
        'name' => 'Zephyr Testuser',
        'username' => 'zephyr',
        'email' => 'zephyr@example.com',
        'phone' => '9998887777',
    ]);
    User::factory()->count(3)->create();

    $columns = ['user', 'username', 'contact'];

    foreach (['Zephyr', 'zephyr', 'zephyr@example.com', '9998887777'] as $term) {
        $response = $this->actingAs($this->admin)->getJson(route('users.index', dtParams($columns, [
            'search' => ['value' => $term],
        ])));

        expect($response->json('recordsFiltered'))->toBe(1, "search term: {$term}")
            ->and($response->json('data.0.username'))->toBe('zephyr');
    }
});

it('filters the user listing by role and status', function () {
    User::factory()->create(['is_active' => false])->assignRole('Sales');
    User::factory()->count(2)->create()->each->assignRole('Manager');

    $columns = ['user', 'username', 'contact'];

    expect($this->actingAs($this->admin)
        ->getJson(route('users.index', dtParams($columns) + ['role' => 'Manager']))
        ->json('recordsFiltered'))->toBe(2);

    expect($this->actingAs($this->admin)
        ->getJson(route('users.index', dtParams($columns) + ['status' => 'inactive']))
        ->json('recordsFiltered'))->toBe(1);
});

it('orders the user listing by a computed column', function () {
    User::factory()->create(['name' => 'Aaron First']);
    User::factory()->create(['name' => 'Zoe Last']);

    $params = dtParams(['user', 'username', 'contact'], [
        'order' => [['column' => 0, 'dir' => 'desc']],
    ]);

    $response = $this->actingAs($this->admin)->getJson(route('users.index', $params));

    expect($response->json('data.0.user'))->toContain('Zoe Last');
});

it('returns role counts in the role listing payload', function () {
    $response = $this->actingAs($this->admin)
        ->getJson(route('roles.index', dtParams(['name', 'permissions', 'users_count'])));

    $response->assertOk();

    expect($response->json('data.0'))
        ->toHaveKeys(['name', 'permissions', 'users_count', 'permissions_count', 'action']);

    $rows = collect($response->json('data'));

    // Admin was seeded with every permission except permission.delete.
    $admin = $rows->first(fn ($row) => str_contains($row['name'], '>Admin<'));
    expect($admin)->not->toBeNull()
        ->and($admin['permissions_count'])->toBe(Permission::count() - 1);

    // Super Admin carries no explicit permissions; the cell says so instead of showing 0.
    $superAdmin = $rows->first(fn ($row) => str_contains($row['name'], '>Super Admin<'));
    expect($superAdmin['permissions_count'])->toBe(0)
        ->and($superAdmin['permissions'])->toContain('All permissions')
        ->and($superAdmin['name'])->toContain('locked');
});

it('returns permission counts and filters by module', function () {
    $columns = ['name', 'module', 'roles_count'];

    $all = $this->actingAs($this->admin)->getJson(route('permissions.index', dtParams($columns)));
    $all->assertOk();
    expect($all->json('recordsTotal'))->toBe(Permission::count())
        ->and($all->json('data.0'))->toHaveKeys(['name', 'module', 'roles_count', 'action']);

    $filtered = $this->actingAs($this->admin)
        ->getJson(route('permissions.index', dtParams($columns) + ['module' => 'quotation']));

    expect($filtered->json('recordsFiltered'))
        ->toBe(Permission::where('name', 'like', 'quotation.%')->count());
});

it('still enforces permissions on the datatables endpoints', function () {
    $sales = User::factory()->create();
    $sales->assignRole('Sales');

    $this->actingAs($sales)->getJson(route('users.index', dtParams(['user'])))->assertForbidden();
    $this->actingAs($sales)->getJson(route('roles.index', dtParams(['name'])))->assertForbidden();
    $this->actingAs($sales)->getJson(route('permissions.index', dtParams(['name'])))->assertForbidden();
});

it('escapes user supplied content in rendered cells', function () {
    User::factory()->create(['name' => '<script>alert(1)</script>', 'username' => 'xssuser']);

    $response = $this->actingAs($this->admin)
        ->getJson(route('users.index', dtParams(['user', 'username', 'contact'], [
            'search' => ['value' => 'xssuser'],
        ])));

    expect($response->json('data.0.user'))
        ->toContain('&lt;script&gt;')
        ->not->toContain('<script>alert(1)</script>');
});
