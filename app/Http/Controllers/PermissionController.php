<?php

namespace App\Http\Controllers;

use App\Http\Requests\PermissionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Yajra\DataTables\Facades\DataTables;

class PermissionController extends Controller implements HasMiddleware
{
    /**
     * Deleting any of these would lock every admin out of the RBAC screens.
     */
    private const CORE_MODULES = ['user', 'role', 'permission'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:permission.view', only: ['index']),
            new Middleware('permission:permission.create', only: ['create', 'store']),
            new Middleware('permission:permission.edit', only: ['edit', 'update']),
            new Middleware('permission:permission.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->data($request);
        }

        return view('permissions.index', [
            'modules' => Permission::query()
                ->pluck('name')
                ->map(fn (string $name) => Str::before($name, '.'))
                ->unique()
                ->sort()
                ->values(),
        ]);
    }

    /**
     * Server-side DataTables payload for the permission listing.
     *
     * @throws \Exception
     */
    private function data(Request $request): JsonResponse
    {
        // select() must come before withCount(), otherwise it discards the count subquery.
        $query = Permission::query()
            ->select('permissions.*')
            ->withCount('roles')
            ->when($request->filled('module'), fn ($q) => $q->where('name', 'like', $request->string('module').'.%'));

        return DataTables::eloquent($query)
            ->editColumn('name', fn (Permission $permission) => '<code>'.e($permission->name).'</code>')
            ->addColumn('module', fn (Permission $permission) => '<span class="text-capitalize">'.e(Str::before($permission->name, '.')).'</span>')
            ->addColumn('action', fn (Permission $permission) => view('permissions.partials.actions', [
                'permission' => $permission,
                'isCore' => $this->isCore($permission),
            ])->render())
            ->orderColumn('module', 'name $1')
            ->rawColumns(['name', 'module', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        return view('permissions.create', ['permission' => new Permission]);
    }

    public function store(PermissionRequest $request): RedirectResponse
    {
        $permission = Permission::create([
            'name' => $request->validated('name'),
            'guard_name' => 'web',
        ]);

        $this->flushPermissionCache();

        return redirect()->route('permissions.index')
            ->with('success', "Permission \"{$permission->name}\" has been created.");
    }

    public function edit(Permission $permission): View
    {
        abort_if($this->isCore($permission), 403, 'Core RBAC permissions cannot be renamed.');

        return view('permissions.edit', compact('permission'));
    }

    public function update(PermissionRequest $request, Permission $permission): RedirectResponse
    {
        abort_if($this->isCore($permission), 403, 'Core RBAC permissions cannot be renamed.');

        $permission->update(['name' => $request->validated('name')]);

        $this->flushPermissionCache();

        return redirect()->route('permissions.index')
            ->with('success', "Permission \"{$permission->name}\" has been updated.");
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        if ($this->isCore($permission)) {
            return back()->with('error', 'Core RBAC permissions cannot be deleted.');
        }

        $name = $permission->name;
        $permission->delete();

        $this->flushPermissionCache();

        return redirect()->route('permissions.index')
            ->with('success', "Permission \"{$name}\" has been deleted and detached from all roles.");
    }

    private function isCore(Permission $permission): bool
    {
        return in_array(Str::before($permission->name, '.'), self::CORE_MODULES, true);
    }

    private function flushPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
