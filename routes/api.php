<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EngineerController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => ['status' => 'ok'])->name('api.health');

    Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');

        Route::apiResource('users', UserController::class)->except(['create', 'edit']);

        Route::get('/engineers', [EngineerController::class, 'index'])->name('engineers.index');
        Route::post('/engineers', [EngineerController::class, 'store'])->name('engineers.store');
        Route::patch('/engineers/reorder', [EngineerController::class, 'reorder'])->name('engineers.reorder');
        Route::put('/engineers/{engineer}', [EngineerController::class, 'update'])->name('engineers.update');
        Route::delete('/engineers/{engineer}', [EngineerController::class, 'destroy'])->name('engineers.destroy');

        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::patch('/products/reorder', [ProductController::class, 'reorder'])->name('products.reorder');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

        Route::get('/issues', [IssueController::class, 'index'])->name('issues.index');
        Route::get('/issues/{issue}', [IssueController::class, 'show'])->name('issues.show');
        Route::put('/issues/{issue}', [IssueController::class, 'update'])->name('issues.update');
        Route::patch('/issues/{issue}/status', [IssueController::class, 'updateStatus'])->name('issues.status');
        Route::patch('/issues/{issue}/managed', [IssueController::class, 'toggleManaged'])->name('issues.managed');
        Route::delete('/issues/{issue}', [IssueController::class, 'destroy'])->name('issues.destroy');
        Route::put('/issues/{issue}/schedule', [IssueController::class, 'updateSchedule'])->name('issues.schedule');
    });
});
