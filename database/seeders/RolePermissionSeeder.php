<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Permissions grouped by module. The `module.action` naming is what the
     * role edit screen groups on, so keep the prefix meaningful.
     */
    public const MODULES = [
        'user' => ['view', 'create', 'edit', 'delete'],
        'role' => ['view', 'create', 'edit', 'delete'],
        'permission' => ['view', 'create', 'edit', 'delete'],
        // Masters. `stone` covers both the Stones and Diamonds screens — same table,
        // same controller.
        'metal_type' => ['view', 'create', 'edit', 'delete'],
        'purity' => ['view', 'create', 'edit', 'delete'],
        'metal_rate' => ['view', 'create', 'edit', 'delete'],
        'item_group' => ['view', 'create', 'edit', 'delete'],
        'stone' => ['view', 'create', 'edit', 'delete'],
        'making_charge' => ['view', 'create', 'edit', 'delete'],
        'item' => ['view', 'create', 'edit', 'delete'],
        'stock' => ['view', 'adjust', 'report'],
        'customer' => ['view', 'create', 'edit', 'delete'],
        'quotation' => ['view', 'create', 'edit', 'delete', 'approve', 'print'],
    ];

    /**
     * Modules seeded by an earlier version that nothing references any more.
     */
    private const RETIRED_MODULES = ['category'];

    /**
     * Master modules a Manager runs day to day and Sales only reads.
     */
    private const MASTER_MODULES = [
        'metal_type', 'purity', 'metal_rate', 'item_group', 'stone', 'making_charge',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::MODULES as $module => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "{$module}.{$action}",
                    'guard_name' => 'web',
                ]);
            }
        }

        $this->pruneRetiredPermissions();

        // Super Admin holds no explicit permissions — Gate::before grants everything.
        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

        $this->syncRole('Admin', Permission::query()
            ->whereNotIn('name', ['permission.delete'])
            ->pluck('name')
            ->all());

        $this->syncRole('Manager', array_merge(
            ['user.view'],
            $this->modulePermissions(...self::MASTER_MODULES),
            $this->modulePermissions('item', 'stock', 'customer'),
            ['quotation.view', 'quotation.create', 'quotation.edit', 'quotation.approve', 'quotation.print'],
        ));

        $this->syncRole('Sales', array_merge(
            ['item.view', 'stock.view'],
            // Sales reads the masters so quotation screens can resolve rates and names.
            array_map(fn (string $module) => "{$module}.view", self::MASTER_MODULES),
            $this->modulePermissions('customer'),
            ['quotation.view', 'quotation.create', 'quotation.edit', 'quotation.print'],
        ));

        $this->createSuperAdmin();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Remove permissions from modules that no longer exist, detaching them from
     * every role on the way out.
     */
    private function pruneRetiredPermissions(): void
    {
        foreach (self::RETIRED_MODULES as $module) {
            Permission::where('name', 'like', "{$module}.%")->get()->each->delete();
        }
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function syncRole(string $name, array $permissions): void
    {
        Role::firstOrCreate(['name' => $name, 'guard_name' => 'web'])
            ->syncPermissions($permissions);
    }

    /**
     * Every permission belonging to the given modules.
     *
     * @return array<int, string>
     */
    private function modulePermissions(string ...$modules): array
    {
        $names = [];

        foreach ($modules as $module) {
            foreach (self::MODULES[$module] as $action) {
                $names[] = "{$module}.{$action}";
            }
        }

        return $names;
    }

    private function createSuperAdmin(): void
    {
        $username = env('SUPER_ADMIN_USERNAME', 'superadmin');
        $password = env('SUPER_ADMIN_PASSWORD', 'Admin@123');

        $user = User::withTrashed()->firstOrNew(['username' => $username]);

        if (! $user->exists) {
            $user->fill([
                'name' => 'Super Admin',
                'email' => env('SUPER_ADMIN_EMAIL', 'superadmin@example.com'),
                'password' => Hash::make($password),
                'is_active' => true,
            ]);
            $user->email_verified_at = now();
            $user->save();

            $this->command?->warn("Super Admin created — username: {$username} / password: {$password}");
            $this->command?->warn('Change this password immediately after the first login.');
        }

        $user->syncRoles(['Super Admin']);
    }
}
