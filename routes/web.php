<?php

use App\Http\Controllers\AngadiyaController;
use App\Http\Controllers\AngadiyaPrintController;
use App\Http\Controllers\AppSettingController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CashDrawerController;
use App\Http\Controllers\CashEntryController;
use App\Http\Controllers\CashLookupController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EstimatePrintController;
use App\Http\Controllers\HallmarkController;
use App\Http\Controllers\HallmarkPrintController;
use App\Http\Controllers\InternalStockController;
use App\Http\Controllers\InternalStockEntryController;
use App\Http\Controllers\InternalStockExportController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemEstimateController;
use App\Http\Controllers\ItemGroupController;
use App\Http\Controllers\ItemLabelController;
use App\Http\Controllers\ItemLotController;
use App\Http\Controllers\ItemPhotoController;
use App\Http\Controllers\LabelSettingController;
use App\Http\Controllers\LotItemEntryController;
use App\Http\Controllers\MakingChargeController;
use App\Http\Controllers\MetalRateController;
use App\Http\Controllers\MetalTypeController;
use App\Http\Controllers\OgEstimateController;
use App\Http\Controllers\OrderFormController;
use App\Http\Controllers\OrderFormPrintController;
use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\OrderTypeController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurityController;
use App\Http\Controllers\RepairFormController;
use App\Http\Controllers\RepairFormPrintController;
use App\Http\Controllers\RepairItemController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalesPersonController;
use App\Http\Controllers\SecuritySettingController;
use App\Http\Controllers\SessionHeartbeatController;
use App\Http\Controllers\SoldItemController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockGroupController;
use App\Http\Controllers\StockReportController;
use App\Http\Controllers\StoneMasterController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierHisabController;
use App\Http\Controllers\SupplierHisabPaymentController;
use App\Http\Controllers\SupplierHisabPrintController;
use App\Http\Controllers\SupplierOrderController;
use App\Http\Controllers\SupplierOrderPrintController;
use App\Http\Controllers\SupplierOrderScanController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\WhatsAppDocumentController;
use App\Http\Controllers\WhatsAppTemplateController;
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
    // The fragment behind the "Today's Rates" button on the estimate forms.
    Route::get('rates/snapshot', [MetalRateController::class, 'snapshot'])->name('rates.snapshot');
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

    // Lookup sits above the resource so `customers/lookup` is not read as an id.
    Route::get('customers/lookup', [CustomerController::class, 'lookup'])->name('customers.lookup');
    Route::get('customers/search', [CustomerController::class, 'search'])->name('customers.search');
    Route::resource('customers', CustomerController::class)->except('show');

    Route::resource('suppliers', SupplierController::class)->except('show');

    Route::get('security-settings', [SecuritySettingController::class, 'edit'])->name('security-settings.edit');
    Route::put('security-settings', [SecuritySettingController::class, 'update'])->name('security-settings.update');

    // {event} is a WhatsAppEvent case, not an id — the enum is what says which
    // templates exist, so an unknown one is a 404 rather than a missing row.
    Route::get('whatsapp-templates', [WhatsAppTemplateController::class, 'index'])->name('whatsapp-templates.index');
    Route::get('whatsapp-templates/{event}', [WhatsAppTemplateController::class, 'edit'])->name('whatsapp-templates.edit');
    Route::put('whatsapp-templates/{event}', [WhatsAppTemplateController::class, 'update'])->name('whatsapp-templates.update');

    Route::get('send-document', [WhatsAppDocumentController::class, 'create'])->name('whatsapp-documents.create');
    Route::post('send-document', [WhatsAppDocumentController::class, 'send'])->name('whatsapp-documents.send');

    // Hyphenated, like items-lookup, so neither can be read as an id.
    Route::get('cash-entries-lookup/documents', [CashLookupController::class, 'documents'])
        ->name('cash-entries.lookup.documents');
    Route::get('cash-entries-lookup/og-estimates', [CashLookupController::class, 'ogEstimates'])
        ->name('cash-entries.lookup.og-estimates');
    // Pieces paid for and on their way out of stock.
    Route::get('sold-items', [SoldItemController::class, 'index'])->name('sold-items.index');
    Route::post('sold-items/{item}/sold', [SoldItemController::class, 'markSold'])->name('sold-items.sold');
    Route::post('sold-items/{item}/available', [SoldItemController::class, 'markAvailable'])->name('sold-items.available');

    Route::get('cash-entries/export', [CashEntryController::class, 'export'])->name('cash-entries.export');
    Route::resource('cash-entries', CashEntryController::class)->except('show')
        ->parameters(['cash-entries' => 'cash_entry']);
    Route::resource('cash-drawers', CashDrawerController::class)->except('show')
        ->parameters(['cash-drawers' => 'cash_drawer']);

    Route::get('app-settings', [AppSettingController::class, 'edit'])->name('app-settings.edit');
    Route::put('app-settings', [AppSettingController::class, 'update'])->name('app-settings.update');

    // Above the resource, so neither path is swallowed by `label-settings/{label_setting}`.
    Route::post('label-settings/{label_setting}/duplicate', [LabelSettingController::class, 'duplicate'])
        ->name('label-settings.duplicate');
    Route::post('label-settings/{label_setting}/default', [LabelSettingController::class, 'setDefault'])
        ->name('label-settings.default');
    Route::resource('label-settings', LabelSettingController::class)->except('show')
        ->parameters(['label-settings' => 'label_setting']);

    Route::get('hallmarks/{hallmark}/print', HallmarkPrintController::class)->name('hallmarks.print');
    Route::resource('hallmarks', HallmarkController::class)->except('show');

    // Making a piece to order. Its own screen, carrying the full item detail.
    Route::get('order-items/create', [OrderItemController::class, 'create'])->name('order-items.create');
    Route::post('order-items', [OrderItemController::class, 'store'])->name('order-items.store');

    // Print, stickers and the by-ref sticker screen sit above the resource so
    // `order-forms/print` is not read as an order id.
    Route::post('order-forms/print', [OrderFormPrintController::class, 'forms'])->name('order-forms.print');
    Route::post('order-forms/stickers', [OrderFormPrintController::class, 'stickers'])->name('order-forms.stickers');
    Route::get('order-forms/sticker-by-ref', [OrderFormPrintController::class, 'stickerByRef'])->name('order-forms.sticker-by-ref');
    Route::post('order-forms/lines/{line}/fix-rate', [OrderFormController::class, 'fixRate'])->name('order-forms.fix-rate');
    Route::resource('order-forms', OrderFormController::class)->except('show')
        ->parameters(['order-forms' => 'order_form']);

    // Booking a repaired piece back into stock. Its own screen, deliberately apart
    // from the general item form.
    Route::get('repair-items/create', [RepairItemController::class, 'create'])->name('repair-items.create');
    Route::post('repair-items', [RepairItemController::class, 'store'])->name('repair-items.store');

    // Print and stickers sit above the resource so `repair-forms/print` is not read
    // as a form id.
    Route::post('repair-forms/print', [RepairFormPrintController::class, 'forms'])->name('repair-forms.print');
    Route::post('repair-forms/stickers', [RepairFormPrintController::class, 'stickers'])->name('repair-forms.stickers');
    Route::resource('repair-forms', RepairFormController::class)->except('show')
        ->parameters(['repair-forms' => 'repair_form']);

    Route::resource('sales-persons', SalesPersonController::class)->except('show')
        ->parameters(['sales-persons' => 'sales_person']);

    // What the shop holds, and how it moved. Both read off items; nothing is stored.
    Route::get('stock', [StockController::class, 'index'])->name('stock.index');
    Route::get('stock/print', [StockController::class, 'print'])->name('stock.print');
    Route::get('stock/daily', [StockReportController::class, 'index'])->name('stock.daily');
    Route::get('stock/daily/export', [StockReportController::class, 'export'])->name('stock.daily.export');
    Route::post('stock/daily/groups', [StockReportController::class, 'updateGroups'])->name('stock.daily.groups');

    // Export sits above the resource so `internal-stock-entries/export` is not read
    // as an entry id.
    Route::get('internal-stock-entries/export', InternalStockExportController::class)
        ->name('internal-stock-entries.export');
    Route::resource('internal-stock-entries', InternalStockEntryController::class)->except('show')
        ->parameters(['internal-stock-entries' => 'entry']);

    Route::post('internal-stocks/{internal_stock}/reset-toggle', [InternalStockController::class, 'toggleReset'])
        ->name('internal-stocks.reset-toggle');
    Route::resource('internal-stocks', InternalStockController::class)->except('show')
        ->parameters(['internal-stocks' => 'internal_stock']);

    // Scan and print sit above the resource, or `supplier-orders/scan` is read as
    // an order id.
    Route::get('supplier-orders/scan', [SupplierOrderScanController::class, 'index'])->name('supplier-orders.scan');
    Route::post('supplier-orders/scan', [SupplierOrderScanController::class, 'destroy'])->name('supplier-orders.scan.destroy');
    Route::post('supplier-orders/scan/{id}/restore', [SupplierOrderScanController::class, 'restore'])->name('supplier-orders.scan.restore');
    Route::post('supplier-orders/print', SupplierOrderPrintController::class)->name('supplier-orders.print');
    Route::post('supplier-orders/{supplier_order}/received', [SupplierOrderController::class, 'markReceived'])
        ->name('supplier-orders.received');
    Route::resource('supplier-orders', SupplierOrderController::class)->except('show')
        ->parameters(['supplier-orders' => 'supplier_order']);

    Route::resource('order-types', OrderTypeController::class)->except('show')
        ->parameters(['order-types' => 'order_type']);

    // Print sits above the resource, or `item-estimates/print` is read as an id.
    Route::post('item-estimates/print', [EstimatePrintController::class, 'itemEstimates'])->name('item-estimates.print');
    Route::get('item-estimates/from-order/{order_form}', [ItemEstimateController::class, 'fromOrder'])
        ->name('item-estimates.from-order');
    Route::resource('item-estimates', ItemEstimateController::class)->except('show')
        ->parameters(['item-estimates' => 'item_estimate']);

    // Print sits above each resource, or `og-estimates/print` is read as an id.
    Route::post('og-estimates/print', [EstimatePrintController::class, 'estimates'])->name('og-estimates.print');
    Route::post('og-estimates/{og_estimate}/copy', [OgEstimateController::class, 'copy'])->name('og-estimates.copy');
    Route::resource('og-estimates', OgEstimateController::class)->except('show')
        ->parameters(['og-estimates' => 'og_estimate']);

    Route::post('vouchers/print', [EstimatePrintController::class, 'vouchers'])->name('vouchers.print');
    Route::post('vouchers/{voucher}/copy', [VoucherController::class, 'copy'])->name('vouchers.copy');
    Route::resource('vouchers', VoucherController::class)->except('show');

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
    Route::get('items-lookup', [ItemController::class, 'lookup'])->name('items.lookup');
    Route::get('items/{item}/label', ItemLabelController::class)->name('items.label');
    // `items-photos/bulk` first, so it is not read as an item id.
    Route::get('items-photos/bulk', [ItemPhotoController::class, 'bulk'])->name('items.photos.bulk');
    Route::post('items-photos/bulk', [ItemPhotoController::class, 'bulkStore'])->name('items.photos.bulk.store');
    Route::get('items-photos', [ItemPhotoController::class, 'index'])->name('items.photos.index');
    Route::get('items/{item}/photo', [ItemPhotoController::class, 'show'])->name('items.photo.show');
    Route::get('items/{item}/photo/raw', [ItemPhotoController::class, 'raw'])->name('items.photo.raw');
    Route::post('items/{item}/photo', [ItemPhotoController::class, 'store'])->name('items.photo.store');
    Route::delete('items/{item}/photo', [ItemPhotoController::class, 'destroy'])->name('items.photo.destroy');
    Route::resource('items', ItemController::class);
});
