<?php

use App\Http\Controllers\Customer\AuthController as CustomerAuthController;
use App\Http\Controllers\Customer\OrderController as CustomerOrderController;
use App\Http\Controllers\Customer\ProfileController as CustomerProfileController;
use App\Http\Controllers\Staff\AccountingController;
use App\Http\Controllers\Staff\AuthController;
use App\Http\Controllers\Staff\HrController;
use App\Http\Controllers\Staff\MrpController;
use App\Http\Controllers\Staff\SalesController;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\Route;

$rolesFor = fn (string $module): string => 'role:'.implode(',', User::MODULE_ROLES[$module]);

// Customer Guest Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [CustomerAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [CustomerAuthController::class, 'login'])->middleware('throttle:login')->name('login.attempt');
    Route::get('/register', [CustomerAuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [CustomerAuthController::class, 'register'])->middleware('throttle:login')->name('register.attempt');
});

// Customer Authenticated Routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [CustomerAuthController::class, 'logout'])->name('customer.logout');

    Route::get('/profile', [CustomerProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [CustomerProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [CustomerProfileController::class, 'updatePassword'])->name('profile.password');

    Route::get('/orders', [CustomerOrderController::class, 'index'])->name('orders.index');
    Route::post('/orders/{order}/receive', [CustomerOrderController::class, 'markReceived'])->name('orders.receive');
    Route::post('/orders/{order}/review', [CustomerOrderController::class, 'storeReview'])->name('orders.review');
});

// Staff / Admin Guest Routes

Route::middleware('guest')->group(function () {
    Route::get('/staff', [AuthController::class, 'showStaffLogin'])->name('staff.login');
    Route::post('/staff', [AuthController::class, 'staffLogin'])->middleware('throttle:login')->name('staff.login.attempt');

    Route::get('/admin', [AuthController::class, 'showAdminLogin'])->name('admin.login');
    Route::post('/admin', [AuthController::class, 'adminLogin'])->middleware('throttle:login')->name('admin.login.attempt');
    Route::post('/admin/quick-login', [AuthController::class, 'adminQuickLogin'])->middleware('throttle:login')->name('admin.quick-login');
});

Route::redirect('/staff/login', '/staff', 301);

Route::prefix('staff')->name('staff.')->middleware('auth')->group(function () use ($rolesFor) {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

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
});

Route::get('/', function () {
    $customerReviews = Review::with(['user', 'order'])
        ->where('is_featured', true)
        ->latest('id')
        ->take(6)
        ->get();

    return view('pages.home', [
        'customerReviews' => $customerReviews,
    ]);
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
