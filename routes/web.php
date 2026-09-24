<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\GeoController;
use App\Http\Controllers\OsController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
    Route::get('/esqueci-senha', [ForgotPasswordController::class, 'show'])->name('password.request');
    Route::post('/esqueci-senha', [ForgotPasswordController::class, 'send'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/redefinir-senha/{token}', [ResetPasswordController::class, 'show'])->name('password.reset');
    Route::post('/redefinir-senha', [ResetPasswordController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/fora-da-area', [GeoController::class, 'blocked'])->name('geo.blocked');
    Route::post('/localizacao', [GeoController::class, 'check'])->middleware('throttle:30,1')->name('geo.check');
    Route::get('/configuracoes', [GeoController::class, 'settings'])->name('settings.index');
    Route::post('/configuracoes', [GeoController::class, 'saveSettings'])->name('settings.save');

    Route::get('/os', [OsController::class, 'index'])->name('os.index');
    Route::post('/os', [OsController::class, 'store'])->name('os.store');
    Route::get('/os/{os}', [OsController::class, 'show'])->name('os.show');
    Route::patch('/os/{os}/status', [OsController::class, 'status'])->name('os.status');

    Route::post('/os/{os}/photos', [PhotoController::class, 'store'])->name('photos.store');
    Route::get('/os/{os}/photos/download', [PhotoController::class, 'downloadStage'])->name('photos.downloadStage');
    Route::get('/os/{os}/photos/download-all', [PhotoController::class, 'downloadAll'])->name('photos.downloadAll');
    Route::delete('/photos/{photo}', [PhotoController::class, 'destroy'])->name('photos.destroy');
    Route::get('/photos/{photo}/file', [PhotoController::class, 'file'])->name('photos.file');

    Route::get('/usuarios', [UserController::class, 'index'])->name('users.index');
    Route::post('/usuarios', [UserController::class, 'store'])->name('users.store');
    Route::patch('/usuarios/{user}', [UserController::class, 'update'])->name('users.update');
    Route::patch('/usuarios/{user}/senha', [UserController::class, 'password'])->name('users.password');
    Route::patch('/usuarios/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');

    Route::get('/auditoria', [AuditController::class, 'index'])->name('audit.index');
});
