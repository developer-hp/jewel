<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemGroupController;
use App\Http\Controllers\MakingChargeController;
use App\Http\Controllers\MetalRateController;
use App\Http\Controllers\MetalTypeController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurityController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\StoneMasterController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::post('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
    Route::resource('users', UserController::class)->except('show');
    Route::resource('roles', RoleController::class);
    Route::resource('permissions', PermissionController::class)->except('show');

    // --- Masters ------------------------------------------------------------
    Route::resource('metal-types', MetalTypeController::class)->except('show')
        ->parameters(['metal-types' => 'metal_type']);
    Route::resource('purities', PurityController::class)->except('show');

    // The morning rate-entry screen must sit above the resource so `rates/today`
    // is not swallowed by `rates/{rate}`.
    Route::get('rates/today', [MetalRateController::class, 'today'])->name('rates.today');
    Route::post('rates/today', [MetalRateController::class, 'storeToday'])->name('rates.today.store');
    Route::resource('rates', MetalRateController::class)->except('show')
        ->parameters(['rates' => 'rate']);

    Route::resource('item-groups', ItemGroupController::class)->except('show')
        ->parameters(['item-groups' => 'item_group']);

    // Stones and diamonds are one table and one controller, split into two screens.
    // The controller reads the kind off the route name (`stones.*` / `diamonds.*`).
    Route::resource('stones', StoneMasterController::class)->except('show')
        ->parameters(['stones' => 'stone']);
    Route::resource('diamonds', StoneMasterController::class)->except('show')
        ->parameters(['diamonds' => 'stone']);

    Route::resource('making-charges', MakingChargeController::class)->except('show')
        ->parameters(['making-charges' => 'making_charge']);

    Route::resource('items', ItemController::class);
});
