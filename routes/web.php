<?php

use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductVariantController;
use App\Http\Controllers\ReferenceDataController;
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
