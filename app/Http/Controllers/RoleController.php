<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Yajra\DataTables\Facades\DataTables;

class RoleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:role.view', only: ['index', 'show']),
            new Middleware('permission:role.create', only: ['create', 'store']),
            new Middleware('permission:role.edit', only: ['edit', 'update']),
            new Middleware('permission:role.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->data();
        }

        return view('roles.index');
    }

    /**
     * Server-side DataTables payload for the role listing.
     *
     * @throws \Exception
     */
    private function data(): JsonResponse
    {
        // select() must come before withCount(), otherwise it discards the count subqueries.
        $query = Role::query()->select('roles.*')->withCount(['permissions', 'users']);

        return DataTables::eloquent($query)
            ->editColumn('name', fn (Role $role) => view('roles.partials.name-cell', compact('role'))->render())
            ->addColumn('permissions', fn (Role $role) => view('roles.partials.permissions-cell', compact('role'))->render())
            ->addColumn('action', fn (Role $role) => view('roles.partials.actions', compact('role'))->render())
            ->orderColumn('permissions', 'permissions_count $1')
            ->rawColumns(['name', 'permissions', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        return view('roles.create', [
            'role' => new Role(),
            'groupedPermissions' => $this->groupedPermissions(),
            'selectedPermissions' => [],
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $role = Role::create(['name' => $request->validated('name'), 'guard_name' => 'web']);
        $role->syncPermissions($request->validated('permissions') ?? []);

        $this->flushPermissionCache();

        return redirect()->route('roles.index')
            ->with('success', "Role \"{$role->name}\" has been created.");
    }

    public function show(Role $role): View
    {
        return view('roles.show', [
            'role' => $role->load('permissions', 'users'),
        ]);
    }

    public function edit(Role $role): View
    {
        abort_if($this->isLocked($role), 403, 'The Super Admin role cannot be modified.');

        return view('roles.edit', [
            'role' => $role,
            'groupedPermissions' => $this->groupedPermissions(),
            'selectedPermissions' => $role->permissions->pluck('name')->all(),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        abort_if($this->isLocked($role), 403, 'The Super Admin role cannot be modified.');

        $role->update(['name' => $request->validated('name')]);
        $role->syncPermissions($request->validated('permissions') ?? []);

        $this->flushPermissionCache();

        return redirect()->route('roles.index')
            ->with('success', "Role \"{$role->name}\" has been updated.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($this->isLocked($role)) {
            return back()->with('error', 'The Super Admin role cannot be deleted.');
        }

        if ($role->users()->count() > 0) {
            return back()->with('error', "Role \"{$role->name}\" is still assigned to users. Reassign them first.");
        }

        $name = $role->name;
        $role->delete();

        $this->flushPermissionCache();

        return redirect()->route('roles.index')
            ->with('success', "Role \"{$name}\" has been deleted.");
    }

    /**
     * Permissions keyed by module prefix, so the form can render one card per module.
     *
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, Permission>>
     */
    private function groupedPermissions()
    {
        return Permission::orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission) => Str::before($permission->name, '.'));
    }

    private function isLocked(Role $role): bool
    {
        return $role->name === 'Super Admin';
    }

    /**
     * Spatie caches permissions for 24h — without this a change appears to do nothing.
     */
    private function flushPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
