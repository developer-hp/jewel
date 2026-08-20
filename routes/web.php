<?php

use App\Http\Controllers\AngadiyaController;
use App\Http\Controllers\AngadiyaPrintController;
use App\Http\Controllers\AppSettingController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HallmarkController;
use App\Http\Controllers\HallmarkPrintController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemGroupController;
use App\Http\Controllers\ItemLabelController;
use App\Http\Controllers\ItemLotController;
use App\Http\Controllers\ItemPhotoController;
use App\Http\Controllers\LabelSettingController;
use App\Http\Controllers\LotItemEntryController;
use App\Http\Controllers\MakingChargeController;
use App\Http\Controllers\MetalRateController;
use App\Http\Controllers\MetalTypeController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurityController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SecuritySettingController;
use App\Http\Controllers\SessionHeartbeatController;
use App\Http\Controllers\StockGroupController;
use App\Http\Controllers\StoneMasterController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierHisabController;
use App\Http\Controllers\SupplierHisabPaymentController;
use App\Http\Controllers\SupplierHisabPrintController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login']);

    // Shown when the password checked out but the account is signed in elsewhere.
    Route::get('login/conflict', [LoginController::class, 'showConflict'])->name('login.conflict');
    Route::post('login/conflict', [LoginController::class, 'resolveConflict'])->name('login.conflict.resolve');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');
    Route::post('session/heartbeat', SessionHeartbeatController::class)->name('session.heartbeat');

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

    Route::resource('stock-groups', StockGroupController::class)->except('show')
        ->parameters(['stock-groups' => 'stock_group']);

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

    Route::resource('suppliers', SupplierController::class)->except('show');

    Route::get('security-settings', [SecuritySettingController::class, 'edit'])->name('security-settings.edit');
    Route::put('security-settings', [SecuritySettingController::class, 'update'])->name('security-settings.update');

    Route::get('app-settings', [AppSettingController::class, 'edit'])->name('app-settings.edit');
    Route::put('app-settings', [AppSettingController::class, 'update'])->name('app-settings.update');

    Route::get('label-settings', [LabelSettingController::class, 'edit'])->name('label-settings.edit');
    Route::put('label-settings', [LabelSettingController::class, 'update'])->name('label-settings.update');

    Route::get('hallmarks/{hallmark}/print', HallmarkPrintController::class)->name('hallmarks.print');
    Route::resource('hallmarks', HallmarkController::class)->except('show');

    // Rate, print and summary sit above the resource so `supplier-hisabs/print` is
    // not read as an entry id.
    Route::post('supplier-hisabs/rate', [SupplierHisabController::class, 'storeRate'])->name('supplier-hisabs.rate');
    Route::post('supplier-hisabs/print', [SupplierHisabPrintController::class, 'slips'])->name('supplier-hisabs.print');
    Route::get('supplier-hisabs/summary', [SupplierHisabPrintController::class, 'summary'])->name('supplier-hisabs.summary');
    Route::get('supplier-hisabs/{hisab}/settle', [SupplierHisabPaymentController::class, 'edit'])->name('supplier-hisabs.settle');
    Route::put('supplier-hisabs/{hisab}/settle', [SupplierHisabPaymentController::class, 'update'])->name('supplier-hisabs.settle.update');
    Route::resource('supplier-hisabs', SupplierHisabController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['supplier-hisabs' => 'hisab']);

    // Print sits above the resource so `angadiyas/print` is not read as a slip id.
    Route::post('angadiyas/print', AngadiyaPrintController::class)->name('angadiyas.print');
    Route::resource('angadiyas', AngadiyaController::class)->except('show');

    // Entry screen sits above the resource so `lots/{lot}/items` is not a lot route.
    Route::get('lots/{lot}/items/create', [LotItemEntryController::class, 'create'])->name('lots.items.create');
    Route::post('lots/{lot}/items', [LotItemEntryController::class, 'store'])->name('lots.items.store');
    Route::resource('lots', ItemLotController::class)->parameters(['lots' => 'lot']);

    // All above the resource so these are not swallowed by `items/{item}`.
    Route::get('items/{item}/label', ItemLabelController::class)->name('items.label');
    Route::get('items-photos/bulk', [ItemPhotoController::class, 'bulk'])->name('items.photos.bulk');
    Route::post('items-photos/bulk', [ItemPhotoController::class, 'bulkStore'])->name('items.photos.bulk.store');
    Route::post('items/{item}/photo', [ItemPhotoController::class, 'store'])->name('items.photo.store');
    Route::delete('items/{item}/photo', [ItemPhotoController::class, 'destroy'])->name('items.photo.destroy');
    Route::resource('items', ItemController::class);
});
