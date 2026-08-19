<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard.index', [
            'userCount' => User::count(),
            'activeUserCount' => User::where('is_active', true)->count(),
            'roleCount' => Role::count(),
            'permissionCount' => Permission::count(),
        ]);
    }
}
