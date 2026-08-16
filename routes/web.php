<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\MenuItemController;
use App\Http\Controllers\Admin\RestaurantTableController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderHistoryController;
use App\Http\Controllers\Pos\CheckoutController;
use App\Http\Controllers\Pos\CustomerDisplayController;
use App\Http\Controllers\Pos\OrderScreenController;
use App\Http\Controllers\Pos\PosController;
use App\Http\Controllers\ReportController;
use App\Support\Permissions;
use Illuminate\Support\Facades\Route;

/*
 * Access is granted by permission, never by role name, so a restaurant can
 * invent its own roles without a code change.
 */

Route::redirect('/', '/pos');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:10,1');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // ---------------------------------------------------------------- POS ---
    Route::middleware('permission:'.Permissions::VIEW_POS)->prefix('pos')->name('pos.')->group(function () {
        Route::get('/', [PosController::class, 'home'])->name('home');

        // The second screen. Opened once per shift and left running; it is fed
        // by the cashier's window rather than by this route.
        Route::get('display', CustomerDisplayController::class)->name('display');

        Route::post('tables/{table}', [PosController::class, 'selectTable'])->name('tables.select');
        Route::post('takeaway', [PosController::class, 'storeTakeaway'])->name('takeaway.store');
        Route::post('phone-order', [PosController::class, 'storePhoneOrder'])->name('phone.store');

        Route::prefix('orders/{order}')->name('orders.')->group(function () {
            Route::get('/', [OrderScreenController::class, 'show'])->name('show');
            Route::post('hold', [OrderScreenController::class, 'hold'])->name('hold');

            // Touch endpoints, called from the order screen over fetch.
            Route::post('items', [OrderScreenController::class, 'storeItem'])->name('items.store');
            Route::patch('items/{item}', [OrderScreenController::class, 'updateItem'])->name('items.update');
            Route::delete('items/{item}', [OrderScreenController::class, 'destroyItem'])->name('items.destroy');

            Route::post('discount', [OrderScreenController::class, 'applyDiscount'])->name('discount');
            Route::post('move', [OrderScreenController::class, 'moveTable'])->name('move');
            Route::post('cancel', [OrderScreenController::class, 'cancel'])->name('cancel');
            Route::post('customer', [PosController::class, 'updateCustomer'])->name('customer');
            Route::post('fulfillment/{status}', [PosController::class, 'updateFulfillment'])->name('fulfillment');

            Route::get('checkout', [CheckoutController::class, 'show'])->name('checkout');
            Route::post('checkout', [CheckoutController::class, 'store'])->name('checkout.store');
        });
    });

    // ------------------------------------------------------ Order history ---
    Route::middleware('permission:'.Permissions::VIEW_ORDERS)->group(function () {
        Route::get('orders', [OrderHistoryController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderHistoryController::class, 'show'])->name('orders.show');
    });

    Route::get('orders/{order}/receipt', [OrderHistoryController::class, 'receipt'])
        ->middleware('permission:'.Permissions::PRINT_RECEIPTS)
        ->name('orders.receipt');

    // ------------------------------------------------------------- Admin ---
    Route::middleware('permission:'.Permissions::MANAGE_MENU)->group(function () {
        Route::resource('categories', CategoryController::class)->except('show');
        Route::resource('menu-items', MenuItemController::class)->except('show');
    });

    Route::middleware('permission:'.Permissions::MANAGE_TABLES)
        ->resource('tables', RestaurantTableController::class)
        ->except('show')
        ->parameters(['tables' => 'table']);

    Route::middleware('permission:'.Permissions::MANAGE_USERS)
        ->resource('users', UserController::class)
        ->except('show');

    Route::middleware('permission:'.Permissions::MANAGE_SETTINGS)->group(function () {
        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    });

    // The same figures in three shapes: on screen, on paper, in a spreadsheet.
    Route::middleware('permission:'.Permissions::VIEW_REPORTS)->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('print', [ReportController::class, 'print'])->name('print');
        Route::get('download', [ReportController::class, 'download'])->name('download');
    });
});
