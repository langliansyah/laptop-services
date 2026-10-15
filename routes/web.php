<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CustomerServiceController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\StaffServiceController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::post('/reservasi', [CustomerServiceController::class, 'store'])->name('reservations.store');
Route::get('/tracking', fn () => view('tracking.lookup'))->name('tracking.lookup');
Route::post('/tracking', [CustomerServiceController::class, 'lookup'])->middleware('throttle:10,1')->name('tracking.lookup.submit');
Route::get('/tracking/{service}', [CustomerServiceController::class, 'show'])->name('tracking.show');
Route::post('/tracking/{service}/approve', [CustomerServiceController::class, 'approve'])->middleware('throttle:10,1')->name('tracking.approve');
Route::post('/tracking/{service}/cancel', [CustomerServiceController::class, 'cancel'])->middleware('throttle:10,1')->name('tracking.cancel');
Route::post('/tracking/{service}/feedback', [CustomerServiceController::class, 'feedback'])->name('tracking.feedback');
Route::get('/tracking/{service}/diagnosis-image', [CustomerServiceController::class, 'diagnosisImage'])->name('tracking.diagnosis-image');
Route::get('/tracking/{service}/receipt', [CustomerServiceController::class, 'receipt'])->name('tracking.receipt');

Route::middleware('guest')->group(function () {
    Route::get('/daftar', [AccountController::class, 'registerForm'])->name('register');
    Route::post('/daftar', [AccountController::class, 'register'])->name('register.store');
    Route::get('/masuk', [AccountController::class, 'loginForm'])->name('login');
    Route::post('/masuk', [AccountController::class, 'login'])->middleware('throttle:10,1')->name('login.store');
});
Route::post('/keluar', [AccountController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/akun/riwayat', [AccountController::class, 'history'])->middleware('auth')->name('account.history');

Route::middleware(['auth', 'staff'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/', [StaffServiceController::class, 'index'])->name('index');
    Route::post('/{service}/terima', [StaffServiceController::class, 'receive'])->name('receive');
    Route::post('/{service}/diagnosis', [StaffServiceController::class, 'diagnose'])->name('diagnose');
    Route::post('/{service}/progres', [StaffServiceController::class, 'progress'])->name('progress');
    Route::post('/{service}/siap-diambil', [StaffServiceController::class, 'ready'])->name('ready');
    Route::post('/{service}/selesaikan', [StaffServiceController::class, 'complete'])->name('complete');
});
