<?php

use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ExchangeController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\OpeningStockController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductVariantController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReferenceDataController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SupplierController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:30,1');
    Route::view('/forgot-password', 'auth.forgot-password')->name('password.request');
    Route::post('/forgot-password', [PasswordController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', fn (Request $request, string $token) => view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]))->name('password.reset');
    Route::post('/reset-password', [PasswordController::class, 'reset'])->middleware('throttle:5,1')->name('password.store');
});

Route::post('/logout', [SessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'active', 'auth.session'])->group(function () {
    Route::middleware('can:exchanges.create')->group(function () {
        Route::get('/exchanges/lookup', [SaleController::class, 'lookup'])->name('exchanges.lookup');
        Route::resource('exchanges', ExchangeController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('/exchanges/{exchange}/complete', [ExchangeController::class, 'complete'])->name('exchanges.complete');
        Route::post('/exchanges/{exchange}/cancel', [ExchangeController::class, 'cancel'])->name('exchanges.cancel');
    });
    Route::middleware('can:refunds.create')->group(function () {
        Route::resource('refunds', RefundController::class)->only(['index', 'create', 'store', 'show']);
        foreach (['approve', 'complete', 'close'] as $action) {
            Route::post('/refunds/{refund}/'.$action, [RefundController::class, $action])->name('refunds.'.$action);
        }
    });
    Route::middleware('can:returns.create')->group(function () {
        Route::resource('returns', ReturnController::class)->only(['index', 'create', 'store', 'show']);
        foreach (['approve', 'complete', 'reject'] as $action) {
            Route::post('/returns/{return}/'.$action, [ReturnController::class, $action])->name('returns.'.$action);
        }
    });
    Route::middleware('can:orders.create')->group(function () {
        Route::get('/orders/lookup', [SaleController::class, 'lookup'])->name('orders.lookup');
        Route::resource('orders', OrderController::class)->only(['index', 'create', 'store', 'show']);
        foreach (['confirm', 'paid', 'convert', 'cancel', 'status'] as $action) {
            Route::post('/orders/{order}/'.$action, [OrderController::class, $action])->name('orders.'.$action);
        }
    });
    Route::middleware('can:sales.create')->group(function () {
        Route::get('/pos', [SaleController::class, 'create'])->name('sales.create');
        Route::get('/pos/lookup', [SaleController::class, 'lookup'])->name('sales.lookup');
        Route::post('/pos/review', [SaleController::class, 'review'])->name('sales.review');
        Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
        Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
        Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
        Route::post('/sales/{sale}/cancel', [SaleController::class, 'cancel'])->name('sales.cancel');
    });
    Route::resource('customers', CustomerController::class)->except('destroy')->middleware('can:customers.create');
    Route::get('/opening-stock', [OpeningStockController::class, 'index'])->name('opening-stock.index');
    Route::get('/opening-stock/{variant}', [OpeningStockController::class, 'create'])->name('opening-stock.create');
    Route::post('/opening-stock/{variant}/review', [OpeningStockController::class, 'review'])->name('opening-stock.review');
    Route::post('/opening-stock/{variant}/confirm', [OpeningStockController::class, 'confirm'])->name('opening-stock.confirm');
    Route::middleware('can:purchases.manage')->group(function () {
        Route::get('/purchases/lookup', [PurchaseController::class, 'lookup'])->name('purchases.lookup');
        Route::post('/purchases/{purchase}/confirm', [PurchaseController::class, 'confirm'])->name('purchases.confirm');
        Route::post('/purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])->name('purchases.cancel');
        Route::resource('purchases', PurchaseController::class)->except('destroy');
    });
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/{variant}/movements', [InventoryController::class, 'movements'])->withTrashed()->name('inventory.movements');
    Route::resource('suppliers', SupplierController::class)->except('destroy');
    Route::post('/products/{product}/restore', [ProductController::class, 'restore'])->withTrashed()->name('products.restore');
    Route::resource('products', ProductController::class)->withTrashed(['show']);
    Route::prefix('products/{product}/variants')->name('variants.')->group(function () {
        Route::get('/create', [ProductVariantController::class, 'create'])->name('create');
        Route::post('/', [ProductVariantController::class, 'store'])->name('store');
        Route::get('/{variant}/edit', [ProductVariantController::class, 'edit'])->whereNumber('variant')->name('edit');
        Route::put('/{variant}', [ProductVariantController::class, 'update'])->whereNumber('variant')->name('update');
        Route::delete('/{variant}', [ProductVariantController::class, 'destroy'])->whereNumber('variant')->name('destroy');
        Route::post('/{variant}/restore', [ProductVariantController::class, 'restore'])->whereNumber('variant')->name('restore');
    });
    Route::prefix('reference-data/{type}')->name('reference.')->middleware('can:reference-data.manage')->group(function () {
        Route::get('/', [ReferenceDataController::class, 'index'])->name('index');
        Route::get('/create', [ReferenceDataController::class, 'create'])->name('create');
        Route::post('/', [ReferenceDataController::class, 'store'])->name('store');
        Route::get('/{record}/edit', [ReferenceDataController::class, 'edit'])->whereNumber('record')->name('edit');
        Route::put('/{record}', [ReferenceDataController::class, 'update'])->whereNumber('record')->name('update');
    });
    Route::view('/dashboard', 'home')->name('dashboard');
    Route::get('/profile', function (Request $request) {
        Gate::authorize('view', $request->user());

        return view('auth.profile');
    })->name('profile');
    Route::put('/password', [PasswordController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
});
