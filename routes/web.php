<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\ActivityLogController;
use App\Http\Controllers\Web\BillController;
use App\Http\Controllers\Web\CategoryController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\FloorController;
use App\Http\Controllers\Web\PaymentMethodController;
use App\Http\Controllers\Web\ReceiptController;
use App\Http\Controllers\Web\PosController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\TableController;
use App\Http\Controllers\Web\RoleController;
use App\Http\Controllers\Web\SettingsController;
use App\Http\Controllers\Web\UserController;
use Illuminate\Support\Facades\Route;

// Staff app: no public landing page. Guests are sent on to sign in.
Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');

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
