<?php

use App\Http\Controllers\Staff\AuthController;
use App\Http\Controllers\Staff\MrpController;
use Illuminate\Support\Facades\Route;

Route::prefix('staff')->name('staff.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
    });

    Route::middleware('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('/', fn () => redirect()->route('staff.mrp.index'))->name('dashboard');

        Route::middleware('role:admin,warehouse')->prefix('mrp')->name('mrp.')->group(function () {
            Route::get('/', [MrpController::class, 'index'])->name('index');
            Route::post('/{component}/adjust', [MrpController::class, 'adjustStock'])->name('adjust');
            Route::get('/movements', [MrpController::class, 'movements'])->name('movements');
        });
    });
});

Route::get('/', function () {
    return view('pages.home');
})->name('home');

Route::get('/builder', function () {
    return view('pages.build');
})->name('build');

Route::get('/menu', function () {
    return view('pages.menu');
})->name('menu');

Route::get('/contact', function () {
    return view('pages.contact');
})->name('contact');
