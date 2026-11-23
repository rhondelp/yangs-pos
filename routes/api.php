<?php

use App\Http\Controllers\Api\V1\ActivityLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CorrectionController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\InventoryItemController;
use App\Http\Controllers\Api\V1\InventoryReportController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\OrderItemController;
use App\Http\Controllers\Api\V1\PaymentMethodController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ReceiptController;
use App\Http\Controllers\Api\V1\RecipeController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\StockReceiptController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\TableController;
use App\Http\Controllers\Api\V1\TableSessionController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Web\ExpenseController as WebExpenseController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');

        Route::middleware('can:users.manage')->group(function () {
            Route::apiResource('users', UserController::class)->except('destroy');
        });

        Route::get('activity-logs', [ActivityLogController::class, 'index'])->middleware('can:logs.view')->name('activity-logs.index');

        Route::middleware('can:roles.manage')->group(function () {
            Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
            Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        });

        Route::get('menu', MenuController::class)->middleware('can:pos.create-order')->name('menu');
        Route::get('payment-methods', PaymentMethodController::class)->middleware('can:payments.record')->name('payment-methods.index');

        Route::get('tables', [TableController::class, 'index'])->middleware('can:tables.view')->name('tables.index');
        Route::patch('tables/{table}/status', [TableController::class, 'updateStatus'])->middleware('can:tables.update-status')->name('tables.status');

        Route::get('sessions', [TableSessionController::class, 'index'])->middleware('can:pos.create-order')->name('sessions.index');
        Route::post('tables/{table}/sessions', [TableSessionController::class, 'store'])->middleware('can:pos.open-session')->name('sessions.store');
        Route::get('sessions/{session}', [TableSessionController::class, 'show'])->middleware('can:pos.create-order')->name('sessions.show');
        Route::post('sessions/{session}/cancel', [TableSessionController::class, 'cancel'])->middleware('can:pos.cancel-session')->name('sessions.cancel');
        Route::middleware('can:payments.record')->group(function () {
            Route::get('sessions/{session}/bill', [BillController::class, 'show'])->name('sessions.bill');
            Route::post('sessions/{session}/payments', [BillController::class, 'pay'])->name('sessions.payments');
            Route::post('sessions/{session}/settle', [BillController::class, 'settle'])->name('sessions.settle');
        });
        Route::middleware('can:orders.discount')->group(function () {
            Route::post('sessions/{session}/discount', [BillController::class, 'discount'])->name('sessions.discount');
            Route::delete('sessions/{session}/discount', [BillController::class, 'removeDiscount'])->name('sessions.discount.remove');
        });
        Route::post('order-items/{item}/void', [CorrectionController::class, 'voidItem'])->middleware('can:orders.void')->name('order-items.void');
        Route::post('orders/{order}/void', [CorrectionController::class, 'voidOrder'])->middleware('can:orders.void')->name('orders.void');
        Route::post('payments/{payment}/refunds', [CorrectionController::class, 'refund'])->middleware('can:payments.refund')->name('payments.refunds');
        Route::post('payments/{payment}/corrections', [CorrectionController::class, 'correct'])->middleware('can:corrections.create')->name('payments.corrections');
        Route::get('sessions/{session}/receipt', [ReceiptController::class, 'show'])->middleware('can:receipts.view')->name('sessions.receipt');
        Route::post('sessions/{session}/orders', [OrderController::class, 'store'])->middleware('can:pos.create-order')->name('orders.store');
        Route::patch('order-items/{item}', [OrderItemController::class, 'update'])->middleware('can:pos.create-order')->name('order-items.update');
        Route::post('order-items/{item}/cancel', [OrderItemController::class, 'cancel'])->middleware('can:orders.cancel-item')->name('order-items.cancel');

        Route::middleware('can:products.view')->group(function () {
            Route::apiResource('products', ProductController::class)->only(['index', 'show']);
            Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
        });

        Route::middleware('can:products.manage')->group(function () {
            Route::apiResource('products', ProductController::class)->only(['store', 'update', 'destroy']);
            Route::post('products/{product}/restore', [ProductController::class, 'restore'])->withTrashed()->name('products.restore');
            Route::apiResource('categories', CategoryController::class)->only(['store', 'update', 'destroy']);
            Route::post('categories/{category}/restore', [CategoryController::class, 'restore'])->withTrashed()->name('categories.restore');
        });

        // Expenses (Phase 4)
        Route::middleware('can:expenses.view')->group(function () {
            Route::get('expense-categories', [ExpenseController::class, 'categories'])->name('expense-categories.index');
            Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
            Route::get('expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');
            Route::get('expenses/{expense}/attachment', [WebExpenseController::class, 'attachment'])->name('expenses.attachment'); // same file response as the web
        });
        Route::middleware('can:expenses.manage')->group(function () {
            Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
            Route::post('expenses/{expense}/void', [ExpenseController::class, 'void'])->name('expenses.void');
        });

        // Inventory (Phase 3)
        Route::middleware('can:inventory.view')->group(function () {
            Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
            Route::get('inventory-items', [InventoryItemController::class, 'index'])->name('inventory-items.index');
            Route::get('inventory-items/{item}', [InventoryItemController::class, 'show'])->withTrashed()->name('inventory-items.show');
            Route::get('inventory-items/{item}/movements', [InventoryItemController::class, 'movements'])->withTrashed()->name('inventory-items.movements');
            Route::get('inventory/valuation', [InventoryReportController::class, 'valuation'])->name('inventory.valuation');
            Route::get('inventory-movements', [InventoryReportController::class, 'movements'])->name('inventory-movements.index');
            Route::get('stock-receipts', [StockReceiptController::class, 'index'])->name('stock-receipts.index');
            Route::get('stock-receipts/{receipt}', [StockReceiptController::class, 'show'])->name('stock-receipts.show');
            Route::get('products/{product}/recipe', [RecipeController::class, 'show'])->name('products.recipe.show');
        });
        Route::post('stock-receipts', [StockReceiptController::class, 'store'])->middleware('can:inventory.stock-in')->name('stock-receipts.store');
        Route::post('inventory-items/{item}/stock-outs', [InventoryItemController::class, 'stockOut'])->middleware('can:inventory.adjust')->name('inventory-items.stock-outs');
        Route::middleware('can:inventory.manage')->group(function () {
            Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
            Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
            Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
            Route::post('suppliers/{supplier}/restore', [SupplierController::class, 'restore'])->withTrashed()->name('suppliers.restore');
            Route::post('inventory-items', [InventoryItemController::class, 'store'])->name('inventory-items.store');
            Route::put('inventory-items/{item}', [InventoryItemController::class, 'update'])->name('inventory-items.update');
            Route::put('inventory-items/{item}/units', [InventoryItemController::class, 'units'])->name('inventory-items.units');
            Route::delete('inventory-items/{item}', [InventoryItemController::class, 'destroy'])->name('inventory-items.destroy');
            Route::post('inventory-items/{item}/restore', [InventoryItemController::class, 'restore'])->withTrashed()->name('inventory-items.restore');
            Route::put('products/{product}/recipe', [RecipeController::class, 'update'])->name('products.recipe.update');
        });
    });
});
