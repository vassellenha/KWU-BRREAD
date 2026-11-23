<?php

use App\Http\Controllers\HoneypotController;
use App\Http\Controllers\PublicOrderController;
use App\Http\Controllers\Staff\AccountingController;
use App\Http\Controllers\Staff\AuthController;
use App\Http\Controllers\Staff\CaptchaController;
use App\Http\Controllers\Staff\HrController;
use App\Http\Controllers\Staff\MenuController;
use App\Http\Controllers\Staff\MrpController;
use App\Http\Controllers\Staff\OrderManagementController;
use App\Http\Controllers\Staff\SalesController;
use App\Http\Controllers\Staff\SecurityController;
use App\Http\Controllers\Staff\SignatureComboController;
use App\Http\Controllers\Staff\SiteController;
use App\Models\User;
use App\Support\HomeMenu;
use App\Support\HoneypotTrap;
use App\Support\StudioFlavorTextures;
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

// The honeypot: the superadmin sign-in lives only at /superadmin, so these addresses are bait for a guest
// (see App\Support\HoneypotTrap). Anything else under them is bait too.
foreach (HoneypotTrap::PATHS as $trapPath) {
    Route::get('/'.$trapPath, HoneypotController::class);
    Route::get('/'.$trapPath.'/{any}', HoneypotController::class)->where('any', '.*');
}

Route::prefix('staff')->name('staff.')->middleware('auth')->group(function () use ($rolesFor) {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware($rolesFor(User::MODULE_ORDERS))->prefix('orders')->name('orders.')->group(function () {
        Route::get('/', [OrderManagementController::class, 'index'])->name('index');
        Route::post('/{order}/confirm', [OrderManagementController::class, 'confirm'])->name('confirm');
        Route::post('/{order}/paid', [OrderManagementController::class, 'markPaid'])->name('paid');
        Route::post('/{order}/cancel', [OrderManagementController::class, 'cancel'])->name('cancel');
        // Deleting wipes the order for good (and drops it from the charts), so only the admin may do it.
        Route::delete('/{order}', [OrderManagementController::class, 'destroy'])->middleware('role:'.User::ROLE_ADMIN)->name('destroy');
    });

    Route::middleware($rolesFor(User::MODULE_MRP))->prefix('mrp')->name('mrp.')->group(function () {
        Route::get('/', [MrpController::class, 'index'])->name('index');
        Route::post('/bulk', [MrpController::class, 'bulkAdjust'])->middleware('throttle:30,1')->name('bulk');
        Route::post('/{component}/adjust', [MrpController::class, 'adjustStock'])->name('adjust');
        Route::get('/movements', [MrpController::class, 'movements'])->name('movements');
    });

    Route::middleware($rolesFor(User::MODULE_MENU))->prefix('menu')->name('menu.')->group(function () {
        Route::get('/', [MenuController::class, 'index'])->name('index');
        Route::post('/', [MenuController::class, 'store'])->name('store');
        Route::post('/bulk', [MenuController::class, 'bulkUpdate'])->middleware('throttle:30,1')->name('bulk');
        Route::put('/{component}', [MenuController::class, 'update'])->name('update');
        Route::patch('/{component}/price', [MenuController::class, 'updatePrice'])->name('price');
        Route::post('/{component}/toggle', [MenuController::class, 'toggleActive'])->name('toggle');
        Route::delete('/{component}', [MenuController::class, 'destroy'])->name('destroy');

        Route::prefix('combos')->name('combos.')->group(function () {
            Route::post('/', [SignatureComboController::class, 'store'])->name('store');
            Route::put('/{combo}', [SignatureComboController::class, 'update'])->name('update');
            Route::post('/{combo}/toggle', [SignatureComboController::class, 'toggleActive'])->name('toggle');
            Route::post('/{combo}/move', [SignatureComboController::class, 'move'])->name('move');
            Route::delete('/{combo}', [SignatureComboController::class, 'destroy'])->name('destroy');
        });
    });

    Route::middleware($rolesFor(User::MODULE_HR))->prefix('hr')->name('hr.')->group(function () {
        Route::get('/', [HrController::class, 'index'])->name('index');
        Route::post('/', [HrController::class, 'store'])->name('store');
        Route::post('/bulk', [HrController::class, 'bulkUpdate'])->middleware('throttle:30,1')->name('bulk');
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

    Route::middleware($rolesFor(User::MODULE_SITE))->prefix('site')->name('site.')->group(function () {
        Route::get('/', [SiteController::class, 'index'])->name('index');
        Route::put('/contact', [SiteController::class, 'updateContact'])->name('contact');
        Route::put('/shop/{section}', [SiteController::class, 'updateShop'])->whereIn('section', ['location', 'hours', 'orders', 'promises'])->name('shop');
        Route::delete('/photos/{photo}', [SiteController::class, 'removePhoto'])->name('photos.remove');
        Route::post('/reviews/{order}/toggle', [SiteController::class, 'toggleReview'])->name('reviews.toggle');
    });

    Route::middleware($rolesFor(User::MODULE_SECURITY))->prefix('security')->name('security.')->group(function () {
        Route::get('/', [SecurityController::class, 'index'])->name('index');
        Route::post('/blocks', [SecurityController::class, 'block'])->name('block');
        Route::delete('/blocks/{ipBlock}', [SecurityController::class, 'unblock'])->name('unblock');
        Route::delete('/records', [SecurityController::class, 'forget'])->name('forget');
    });
});

Route::get('/', function () {
    return view('pages.home', ['menu' => HomeMenu::get(public: true)]);
})->name('home');

Route::get('/builder', function () {
    return view('pages.build', ['flavorOverrides' => StudioFlavorTextures::overrides()]);
})->name('build');

Route::get('/menu', function () {
    return view('pages.menu');
})->name('menu');

Route::get('/about', function () {
    return view('pages.about', ['menu' => HomeMenu::get(public: true)]);
})->name('about');

Route::get('/contact', function () {
    return view('pages.contact', ['menu' => HomeMenu::get(public: true)]);
})->name('contact');

Route::get('/privacy', function () {
    return view('pages.privacy');
})->name('privacy');

Route::get('/order/status/{token}', [PublicOrderController::class, 'show'])->name('order.status');
Route::post('/order/status/{token}/received', [PublicOrderController::class, 'receive'])->middleware('throttle:10,1')->name('order.received');
Route::post('/order/status/{token}/review', [PublicOrderController::class, 'submitReview'])->middleware('throttle:10,1')->name('order.review');
Route::get('/reviews', [PublicOrderController::class, 'reviews'])->name('reviews');
Route::get('/reviews/photo/{photo}', [PublicOrderController::class, 'photo'])->whereNumber('photo')->name('review.photo');
Route::get('/combos/photo/{photo}', [SignatureComboController::class, 'photo'])->whereNumber('photo')->name('signature.photo');

// Unknown addresses go through the web middleware too, so a held guest is trapped on them as well.
Route::fallback(fn () => abort(404));
