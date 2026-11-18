<?php

use App\Http\Controllers\Api\V1\ActivityLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\UserController;
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
    });
});
