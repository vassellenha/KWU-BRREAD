<?php

use App\Http\Controllers\PublicOrderController;
use App\Http\Controllers\Staff\AccountingController;
use App\Http\Controllers\Staff\AuthController;
use App\Http\Controllers\Staff\CaptchaController;
use App\Http\Controllers\Staff\HrController;
use App\Http\Controllers\Staff\MrpController;
use App\Http\Controllers\Staff\OrderManagementController;
use App\Http\Controllers\Staff\SalesController;
use App\Http\Controllers\Staff\SecurityController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

$rolesFor = fn (string $module): string => 'role:'.implode(',', User::MODULE_ROLES[$module]);

Route::middleware(['guest', 'lockdown'])->group(function () {
    Route::get('/staff', [AuthController::class, 'showStaffLogin'])->name('staff.login');
    Route::post('/staff', [AuthController::class, 'staffLogin'])->middleware(['throttle:login', 'throttle:login-username'])->name('staff.login.attempt');

    Route::get('/superadmin', [AuthController::class, 'showAdminLogin'])->name('admin.login');
    Route::post('/superadmin', [AuthController::class, 'adminLogin'])->middleware(['throttle:login', 'throttle:login-username'])->name('admin.login.attempt');
    Route::post('/superadmin/quick-login', [AuthController::class, 'adminQuickLogin'])->middleware('throttle:login')->name('admin.quick-login');
});

// Outside the lockdown group so a locked page still shows its (scrambled) captcha box.
Route::get('/captcha', CaptchaController::class)->middleware('throttle:60,1')->name('captcha.image');

Route::get(config('auth.lockdown_preview_path'), [AuthController::class, 'lockdownPreview'])->name('lockdown.preview');

Route::redirect('/staff/login', '/staff', 301);
Route::redirect('/admin', '/superadmin', 301);

Route::prefix('staff')->name('staff.')->middleware('auth')->group(function () use ($rolesFor) {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware($rolesFor(User::MODULE_ORDERS))->prefix('orders')->name('orders.')->group(function () {
        Route::get('/', [OrderManagementController::class, 'index'])->name('index');
        Route::post('/{order}/confirm', [OrderManagementController::class, 'confirm'])->name('confirm');
        Route::post('/{order}/cancel', [OrderManagementController::class, 'cancel'])->name('cancel');
    });

    Route::middleware($rolesFor(User::MODULE_MRP))->prefix('mrp')->name('mrp.')->group(function () {
        Route::get('/', [MrpController::class, 'index'])->name('index');
        Route::post('/{component}/adjust', [MrpController::class, 'adjustStock'])->name('adjust');
        Route::get('/movements', [MrpController::class, 'movements'])->name('movements');
    });

    Route::middleware($rolesFor(User::MODULE_HR))->prefix('hr')->name('hr.')->group(function () {
        Route::get('/', [HrController::class, 'index'])->name('index');
        Route::post('/', [HrController::class, 'store'])->name('store');
        Route::put('/{employee}', [HrController::class, 'update'])->name('update');
        Route::post('/{employee}/toggle', [HrController::class, 'toggleActive'])->name('toggle');
    });

    Route::middleware($rolesFor(User::MODULE_ACCOUNTING))->prefix('accounting')->name('accounting.')->group(function () {
        Route::get('/', [AccountingController::class, 'index'])->name('index');
        Route::get('/export', [AccountingController::class, 'export'])->name('export');
        Route::post('/expenses', [AccountingController::class, 'storeExpense'])->name('expenses.store');
        Route::delete('/expenses/{expense}', [AccountingController::class, 'destroyExpense'])->name('expenses.destroy');
    });

    Route::middleware($rolesFor(User::MODULE_SALES))->prefix('sales')->name('sales.')->group(function () {
        Route::get('/', [SalesController::class, 'index'])->name('index');
    });

    Route::middleware($rolesFor(User::MODULE_SECURITY))->prefix('security')->name('security.')->group(function () {
        Route::get('/', [SecurityController::class, 'index'])->name('index');
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

Route::get('/about', function () {
    return view('pages.about');
})->name('about');

Route::get('/contact', function () {
    return view('pages.contact');
})->name('contact');

Route::get('/privacy', function () {
    return view('pages.privacy');
})->name('privacy');

Route::get('/order/status/{token}', [PublicOrderController::class, 'show'])->name('order.status');
Route::post('/order/status/{token}/review', [PublicOrderController::class, 'submitReview'])->name('order.review');
