<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\ActivityLogController;
use App\Http\Controllers\Web\CategoryController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\RoleController;
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
