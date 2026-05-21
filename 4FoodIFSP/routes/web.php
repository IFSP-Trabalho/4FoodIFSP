<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrdersController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Tablet\TabletOrderController;
use App\Http\Controllers\WhatsApp\InboxController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/login');

Route::get('/tablet', [TabletOrderController::class, 'index'])->name('tablet.order');
Route::post('/tablet/orders', [TabletOrderController::class, 'store'])->name('tablet.orders.store');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/auth/firebase', [AuthController::class, 'firebaseLogin'])->name('auth.firebase');

Route::middleware('firebase.auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/password/change', [AuthController::class, 'showPasswordChange'])->name('password.change.show');
    Route::post('/password/change', [AuthController::class, 'updatePasswordOnFirstAccess'])->name('password.change.update');

    Route::middleware('force.password.reset')->group(function () {
        Route::middleware(['role:admin'])->prefix('admin')->name('admin.')->group(function () {
            Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
            Route::get('/orders', [OrdersController::class, 'index'])->name('orders');
            Route::get('/orders/poll', [OrdersController::class, 'poll'])->name('orders.poll');
            Route::get('/orders/history', [OrdersController::class, 'history'])->name('orders.history');
            Route::patch('/orders/{order}/status', [OrdersController::class, 'updateStatus'])->name('orders.updateStatus');

            Route::prefix('cadastros')->name('cadastros.')->group(function () {
                Route::get('/users', [UsersController::class, 'index'])->name('users.index');
                Route::get('/departments', [UsersController::class, 'departments'])->name('departments.index');
                Route::put('/departments/{department}', [UsersController::class, 'updateDepartmentColor'])->name('departments.updateColor');
                Route::get('/dishes', [UsersController::class, 'dishes'])->name('dishes.index');
                Route::post('/dishes', [UsersController::class, 'storeDish'])->name('dishes.store');
                Route::put('/dishes/{dish}', [UsersController::class, 'updateDish'])->name('dishes.update');
                Route::delete('/dishes/{dish}', [UsersController::class, 'destroyDish'])->name('dishes.destroy');
                Route::post('/users', [UsersController::class, 'store'])->name('users.store');
                Route::put('/users/{user}', [UsersController::class, 'update'])->name('users.update');
                Route::delete('/users/{user}', [UsersController::class, 'destroy'])->name('users.destroy');
                Route::put('/users/{user}/departments', [UsersController::class, 'syncDepartments'])->name('users.syncDepartments');
            });
        });

        Route::get('/kitchen/dashboard', function () {
            return Inertia::render('Kitchen/Dashboard');
        })->name('kitchen.dashboard');

        Route::get('/finance/dashboard', function () {
            return Inertia::render('Finance/Dashboard');
        })->name('finance.dashboard');

        Route::get('/waiter/dashboard', function () {
            return Inertia::render('Waiter/Dashboard');
        })->name('waiter.dashboard');

        Route::middleware(['role:admin|whatsapp_agent'])->prefix('whatsapp')->name('whatsapp.')->group(function () {
            Route::get('/inbox', [InboxController::class, 'index'])->name('inbox');
        });
    });
});
