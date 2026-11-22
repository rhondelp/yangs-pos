<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\ActivityLogController;
use App\Http\Controllers\Web\BillController;
use App\Http\Controllers\Web\CategoryController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\FloorController;
use App\Http\Controllers\Web\InventoryItemController;
use App\Http\Controllers\Web\InventoryMovementController;
use App\Http\Controllers\Web\PaymentMethodController;
use App\Http\Controllers\Web\PosController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\ReceiptController;
use App\Http\Controllers\Web\RecipeController;
use App\Http\Controllers\Web\RoleController;
use App\Http\Controllers\Web\SettingsController;
use App\Http\Controllers\Web\StockReceiptController;
use App\Http\Controllers\Web\SupplierController;
use App\Http\Controllers\Web\TableController;
use App\Http\Controllers\Web\TransactionController;
use App\Http\Controllers\Web\UserController;
use Illuminate\Support\Facades\Route;

// Staff app: no public landing page. Guests are sent on to sign in.
Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile/approval-pin', [ProfileController::class, 'updateApprovalPin'])->middleware('can:approvals.grant')->name('profile.approval-pin');

    Route::middleware('can:users.manage')->group(function () {
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
    });

    Route::middleware('can:logs.view')->group(function () {
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::get('/activity-logs/{activityLog}', [ActivityLogController::class, 'show'])->name('activity-logs.show');
    });

    Route::middleware('can:roles.manage')->group(function () {
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::put('/roles', [RoleController::class, 'update'])->name('roles.update');
    });

    Route::middleware('can:settings.manage')->group(function () {
        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::get('/payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');
        Route::post('/payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
        Route::get('/payment-methods/{paymentMethod}/edit', [PaymentMethodController::class, 'edit'])->name('payment-methods.edit');
        Route::put('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update'])->name('payment-methods.update');
    });

    Route::get('/floor', [FloorController::class, 'index'])->middleware('can:tables.view')->name('floor');
    Route::get('/pos/sessions/{session}', [PosController::class, 'show'])->middleware('can:pos.create-order')->name('pos.show');
    Route::get('/pos/sessions/{session}/bill', [BillController::class, 'show'])->middleware('can:payments.record')->name('pos.bill');
    Route::middleware('can:orders.view-own')->group(function () {
        Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::get('/transactions/{session}', [TransactionController::class, 'show'])->name('transactions.show');
    });
    Route::post('/transactions/payments/{payment}/refunds', [TransactionController::class, 'refund'])->middleware('can:payments.refund')->name('transactions.refunds');
    Route::post('/transactions/payments/{payment}/corrections', [TransactionController::class, 'correct'])->middleware('can:corrections.create')->name('transactions.corrections');
    Route::get('/receipts/{session}', [ReceiptController::class, 'show'])->middleware('can:receipts.view')->name('receipts.show');
    Route::patch('/tables/{table}/status', [FloorController::class, 'updateStatus'])->middleware('can:tables.update-status')->name('tables.status');

    Route::middleware('can:tables.manage')->group(function () {
        Route::get('/tables', [TableController::class, 'index'])->name('tables.index');
        Route::get('/tables/create', [TableController::class, 'create'])->name('tables.create');
        Route::post('/tables', [TableController::class, 'store'])->name('tables.store');
        Route::get('/tables/{table}/edit', [TableController::class, 'edit'])->name('tables.edit');
        Route::put('/tables/{table}', [TableController::class, 'update'])->name('tables.update');
        Route::patch('/tables/{table}/active', [TableController::class, 'active'])->name('tables.active');
    });

    Route::get('/products', [ProductController::class, 'index'])->middleware('can:products.view')->name('products.index');

    // Inventory (Phase 3)
    Route::middleware('can:inventory.view')->group(function () {
        Route::get('/inventory', [InventoryItemController::class, 'index'])->name('inventory.index');
        Route::get('/inventory/movements', [InventoryMovementController::class, 'index'])->name('inventory.movements'); // before /inventory/{item}
        Route::get('/inventory/{item}', [InventoryItemController::class, 'show'])->withTrashed()->name('inventory.show');
        Route::get('/stock-receipts', [StockReceiptController::class, 'index'])->name('stock-receipts.index');
    });
    Route::middleware('can:inventory.stock-in')->group(function () {
        Route::get('/stock-receipts/create', [StockReceiptController::class, 'create'])->name('stock-receipts.create');
        Route::post('/stock-receipts', [StockReceiptController::class, 'store'])->name('stock-receipts.store');
    });
    Route::get('/stock-receipts/{receipt}', [StockReceiptController::class, 'show'])->middleware('can:inventory.view')->name('stock-receipts.show');
    Route::post('/inventory/{item}/stock-outs', [InventoryItemController::class, 'stockOut'])->middleware('can:inventory.adjust')->name('inventory.stock-outs');
    Route::middleware('can:inventory.manage')->group(function () {
        Route::post('/inventory', [InventoryItemController::class, 'store'])->name('inventory.store');
        Route::put('/inventory/{item}', [InventoryItemController::class, 'update'])->name('inventory.update');
        Route::put('/inventory/{item}/units', [InventoryItemController::class, 'units'])->name('inventory.units');
        Route::delete('/inventory/{item}', [InventoryItemController::class, 'archive'])->name('inventory.archive');
        Route::post('/inventory/{item}/restore', [InventoryItemController::class, 'restore'])->withTrashed()->name('inventory.restore');

        Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit');
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::delete('/suppliers/{supplier}', [SupplierController::class, 'archive'])->name('suppliers.archive');
        Route::post('/suppliers/{supplier}/restore', [SupplierController::class, 'restore'])->withTrashed()->name('suppliers.restore');

        Route::put('/products/{product}/recipe', [RecipeController::class, 'update'])->name('products.recipe');
    });

    Route::middleware('can:products.manage')->group(function () {
        Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::patch('/products/{product}/availability', [ProductController::class, 'availability'])->name('products.availability');
        Route::delete('/products/{product}', [ProductController::class, 'archive'])->name('products.archive');
        Route::post('/products/{product}/restore', [ProductController::class, 'restore'])->withTrashed()->name('products.restore');

        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
        Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [CategoryController::class, 'archive'])->name('categories.archive');
        Route::post('/categories/{category}/restore', [CategoryController::class, 'restore'])->withTrashed()->name('categories.restore');
    });
});

require __DIR__.'/auth.php';
