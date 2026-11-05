<?php

use App\Http\Controllers\Staff\AccountingController;
use App\Http\Controllers\Staff\AuthController;
use App\Http\Controllers\Staff\HrController;
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

        Route::middleware('role:admin,hr')->prefix('hr')->name('hr.')->group(function () {
            Route::get('/', [HrController::class, 'index'])->name('index');
            Route::post('/', [HrController::class, 'store'])->name('store');
            Route::put('/{employee}', [HrController::class, 'update'])->name('update');
            Route::post('/{employee}/toggle', [HrController::class, 'toggleActive'])->name('toggle');
        });

        Route::middleware('role:admin,accountant')->prefix('accounting')->name('accounting.')->group(function () {
            Route::get('/', [AccountingController::class, 'index'])->name('index');
            Route::get('/export', [AccountingController::class, 'export'])->name('export');
            Route::post('/expenses', [AccountingController::class, 'storeExpense'])->name('expenses.store');
            Route::delete('/expenses/{expense}', [AccountingController::class, 'destroyExpense'])->name('expenses.destroy');
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
