<?php

use App\Http\Controllers\Api\V1\AlertController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BatchController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\StockMovementController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register'])->name('register');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('/me', [AuthController::class, 'me'])->name('me');
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        });
    });

    Route::prefix('company')->middleware('auth:sanctum')->name('company.')->group(function (): void {
        // Şirket/dükkan adını yalnızca işletme sahibi (admin) değiştirebilir.
        Route::put('/', [CompanyController::class, 'update'])->middleware('admin')->name('update');

        // Çalışan (staff) yönetimi - yalnızca admin.
        Route::middleware('admin')->prefix('staff')->name('staff.')->group(function (): void {
            Route::get('/', [CompanyController::class, 'staffIndex'])->name('index');
            Route::post('/', [CompanyController::class, 'staffStore'])->name('store');
            Route::put('/{user}', [CompanyController::class, 'staffUpdate'])->name('update');
            Route::delete('/{user}', [CompanyController::class, 'staffDestroy'])->name('destroy');
        });
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        // Ürünler: okuma herkes (staff dahil), yazma yalnızca admin.
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
        Route::middleware('admin')->group(function (): void {
            Route::post('/products', [ProductController::class, 'store'])->name('products.store');
            Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
            Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
        });

        // Partiler: okuma herkes, stok girişi (parti yaratma) yalnızca admin.
        Route::get('/batches', [BatchController::class, 'index'])->name('batches.index');
        Route::get('/batches/{batch}', [BatchController::class, 'show'])->name('batches.show');
        Route::post('/batches', [BatchController::class, 'store'])->middleware('admin')->name('batches.store');

        // Hareketler: okuma herkes; çıkış (out) herkes, düzeltme (adjustment) admin (request'te).
        Route::get('/stock-movements', [StockMovementController::class, 'index'])->name('movements.index');
        Route::post('/stock-movements', [StockMovementController::class, 'store'])->name('movements.store');

        // Uyarılar: yaklaşan SKT / geçmiş SKT / kritik stok (bildirim altyapısının kaynağı).
        Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
    });
});
