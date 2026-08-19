<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\Facades\DataTables;

class UserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:user.view', only: ['index', 'show']),
            new Middleware('permission:user.create', only: ['create', 'store']),
            new Middleware('permission:user.edit', only: ['edit', 'update', 'toggleStatus']),
            new Middleware('permission:user.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->data($request);
        }

        return view('users.index', [
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    /**
     * Server-side DataTables payload for the user listing.
     *
     * @throws \Exception
     */
    private function data(Request $request): JsonResponse
    {
        $query = User::query()
            ->with('roles')
            ->select('users.*')
            ->when($request->filled('role'), fn ($q) => $q->role($request->string('role')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->string('status')->toString() === 'active'));

        return DataTables::eloquent($query)
            ->addColumn('user', fn (User $user) => view('users.partials.user-cell', compact('user'))->render())
            ->addColumn('contact', fn (User $user) => view('users.partials.contact-cell', compact('user'))->render())
            ->addColumn('roles', fn (User $user) => view('users.partials.roles-cell', compact('user'))->render())
            ->addColumn('status', fn (User $user) => view('users.partials.status-cell', compact('user'))->render())
            ->addColumn('action', fn (User $user) => view('users.partials.actions', compact('user'))->render())
            // The global search box spans the searchable text columns.
            ->filterColumn('user', fn ($q, $keyword) => $q->where('name', 'like', "%{$keyword}%"))
            ->filterColumn('contact', function ($q, $keyword) {
                $q->where(fn ($sub) => $sub->where('email', 'like', "%{$keyword}%")
                    ->orWhere('phone', 'like', "%{$keyword}%"));
            })
            ->orderColumn('user', 'name $1')
            ->orderColumn('contact', 'email $1')
            ->orderColumn('status', 'is_active $1')
            ->rawColumns(['user', 'contact', 'roles', 'status', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        return view('users.create', [
            'user' => new User(['is_active' => true]),
            'roles' => $this->assignableRoles(),
            'selectedRoles' => [],
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create($data);
        $user->syncRoles($this->filterRoles($data['roles'] ?? []));

        return redirect()->route('users.index')
            ->with('success', "User \"{$user->username}\" has been created.");
    }

    public function edit(User $user): View
    {
        abort_if($this->isProtectedFromCurrentUser($user), 403, 'Only a Super Admin can manage a Super Admin account.');

        return view('users.edit', [
            'user' => $user,
            'roles' => $this->assignableRoles(),
            'selectedRoles' => $user->roles->pluck('name')->all(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        abort_if($this->isProtectedFromCurrentUser($user), 403, 'Only a Super Admin can manage a Super Admin account.');

        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        // Nobody may lock themselves out of their own account.
        if ($user->is($request->user())) {
            $data['is_active'] = true;
        }

        $user->update($data);

        if (! $user->is($request->user())) {
            $user->syncRoles($this->filterRoles($data['roles'] ?? []));
        }

        return redirect()->route('users.index')
            ->with('success', "User \"{$user->username}\" has been updated.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        abort_if($this->isProtectedFromCurrentUser($user), 403, 'Only a Super Admin can manage a Super Admin account.');

        if ($user->hasRole('Super Admin') && $this->superAdminCount() <= 1) {
            return back()->with('error', 'The last Super Admin cannot be deleted.');
        }

        $username = $user->username;
        $user->delete();

        return redirect()->route('users.index')
            ->with('success', "User \"{$username}\" has been deleted.");
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        abort_if($this->isProtectedFromCurrentUser($user), 403, 'Only a Super Admin can manage a Super Admin account.');

        if ($user->is_active && $user->hasRole('Super Admin') && $this->superAdminCount() <= 1) {
            return back()->with('error', 'The last Super Admin cannot be deactivated.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', sprintf(
            'User "%s" has been %s.',
            $user->username,
            $user->is_active ? 'activated' : 'deactivated',
        ));
    }

    /**
     * A Super Admin account may only be touched by another Super Admin — otherwise
     * an Admin with `user.edit` could demote or lock out the owner.
     */
    private function isProtectedFromCurrentUser(User $user): bool
    {
        return $user->hasRole('Super Admin') && ! request()->user()->hasRole('Super Admin');
    }

    /**
     * Only a Super Admin may hand out the Super Admin role.
     */
    private function assignableRoles(): \Illuminate\Support\Collection
    {
        return Role::orderBy('name')
            ->when(! request()->user()->hasRole('Super Admin'), fn ($q) => $q->where('name', '!=', 'Super Admin'))
            ->pluck('name');
    }

    /**
     * @param  array<int, string>  $roles
     * @return array<int, string>
     */
    private function filterRoles(array $roles): array
    {
        $assignable = $this->assignableRoles()->all();

        return array_values(array_intersect($roles, $assignable));
    }

    private function superAdminCount(): int
    {
        return User::role('Super Admin')->where('is_active', true)->count();
    }
}
