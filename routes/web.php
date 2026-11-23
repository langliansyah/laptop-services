<?php

use App\Http\Controllers\AkunController;
use App\Http\Controllers\BerandaController;
use App\Http\Controllers\ServisPelangganController;
use App\Http\Controllers\ServisStafController;
use Illuminate\Support\Facades\Route;

Route::get('/', BerandaController::class)->name('home');
Route::get('/reservasi', fn () => view('reservasi.buat'))->name('reservations.create');
Route::post('/reservasi', [ServisPelangganController::class, 'store'])->name('reservations.store');
Route::get('/tracking', fn () => view('pelacakan.cari'))->name('tracking.lookup');
Route::post('/tracking', [ServisPelangganController::class, 'lookup'])->middleware('throttle:10,1')->name('tracking.lookup.submit');
Route::get('/tracking/{service}', [ServisPelangganController::class, 'show'])->name('tracking.show');
Route::post('/tracking/{service}/approve', [ServisPelangganController::class, 'approve'])->middleware('throttle:10,1')->name('tracking.approve');
Route::post('/tracking/{service}/cancel', [ServisPelangganController::class, 'cancel'])->middleware('throttle:10,1')->name('tracking.cancel');
Route::post('/tracking/{service}/feedback', [ServisPelangganController::class, 'feedback'])->name('tracking.feedback');
Route::get('/tracking/{service}/diagnosis-image', [ServisPelangganController::class, 'diagnosisImage'])->name('tracking.diagnosis-image');
Route::get('/tracking/{service}/receipt', [ServisPelangganController::class, 'receipt'])->name('tracking.receipt');

Route::middleware('guest')->group(function () {
    Route::get('/daftar', [AkunController::class, 'registerForm'])->name('register');
    Route::post('/daftar', [AkunController::class, 'register'])->name('register.store');
    Route::get('/masuk', [AkunController::class, 'loginForm'])->name('login');
    Route::post('/masuk', [AkunController::class, 'login'])->middleware('throttle:10,1')->name('login.store');
});
Route::post('/keluar', [AkunController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/akun/riwayat', [AkunController::class, 'history'])->middleware('auth')->name('account.history');

Route::middleware(['auth', 'staff'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/', [ServisStafController::class, 'index'])->name('index');
    Route::get('/antrean', [ServisStafController::class, 'index'])->name('queue');
    Route::get('/keuangan', [ServisStafController::class, 'finance'])->name('finance');
    Route::get('/akun', [AkunController::class, 'staffAccounts'])->name('accounts');
    Route::post('/akun', [AkunController::class, 'storeStaffAccount'])->name('accounts.store');
    Route::get('/akun/{account}/edit', [AkunController::class, 'editStaffAccount'])->name('accounts.edit');
    Route::put('/akun/{account}', [AkunController::class, 'updateStaffAccount'])->name('accounts.update');
    Route::delete('/akun/{account}', [AkunController::class, 'destroyStaffAccount'])->name('accounts.destroy');
    Route::get('/laporan/harian', [ServisStafController::class, 'exportDailySummary'])->name('reports.daily');
    Route::get('/laporan/bulanan', [ServisStafController::class, 'exportMonthlySummary'])->name('reports.monthly');
    Route::delete('/{service}', [ServisStafController::class, 'destroy'])->name('destroy');
    Route::post('/{service}/terima', [ServisStafController::class, 'receive'])->name('receive');
    Route::post('/{service}/diagnosis', [ServisStafController::class, 'diagnose'])->name('diagnose');
    Route::post('/{service}/progres', [ServisStafController::class, 'progress'])->name('progress');
    Route::post('/{service}/siap-diambil', [ServisStafController::class, 'ready'])->name('ready');
    Route::post('/{service}/selesaikan', [ServisStafController::class, 'complete'])->name('complete');
});
