<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrdersController;
use App\Http\Controllers\Admin\TablesController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Admin\WaClosureReasonsController;
use App\Http\Controllers\Admin\WhatsApp\ConnectionsController;
use App\Http\Controllers\Tablet\TabletOrderController;
use App\Http\Controllers\Tablet\TabletSessionController;
use App\Http\Controllers\Webhooks\BaileysMessageWebhookController;
use App\Http\Controllers\Webhooks\BaileysWebhookController;
use App\Http\Controllers\WhatsApp\InboxController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::post('/webhooks/baileys/status', [BaileysWebhookController::class, 'handle'])
    ->name('webhooks.baileys.status');
Route::post('/webhooks/baileys/messages', [BaileysMessageWebhookController::class, 'handle'])
    ->name('webhooks.baileys.messages');

Route::redirect('/', '/login');

Route::get('/tablet', [TabletOrderController::class, 'index'])->name('tablet.order');
Route::post('/tablet/session/start', [TabletSessionController::class, 'store'])->name('tablet.session.start');
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

                Route::get('/wa-motivos', [WaClosureReasonsController::class, 'index'])->name('wa-motivos.index');
                Route::post('/wa-motivos', [WaClosureReasonsController::class, 'store'])->name('wa-motivos.store');
                Route::put('/wa-motivos/{reason}', [WaClosureReasonsController::class, 'update'])->name('wa-motivos.update');
                Route::delete('/wa-motivos/{reason}', [WaClosureReasonsController::class, 'destroy'])->name('wa-motivos.destroy');
            });

            Route::prefix('mesas')->name('mesas.')->group(function () {
                Route::get('/', [TablesController::class, 'index'])->name('index');
                Route::get('/operacional', [TablesController::class, 'indexOperacional'])->name('operacional');
                Route::post('/', [TablesController::class, 'store'])->name('store');
                Route::put('/{table}', [TablesController::class, 'update'])->name('update');
                Route::delete('/{table}', [TablesController::class, 'destroy'])->name('destroy');
                Route::post('/{table}/release', [TablesController::class, 'release'])->name('release');
                Route::post('/{table}/close-account', [TablesController::class, 'closeAccount'])->name('closeAccount');
            });

            Route::prefix('whatsapp')->name('whatsapp.')->group(function () {
                Route::get('/conexoes', [ConnectionsController::class, 'index'])->name('conexoes.index');
                Route::post('/conexoes', [ConnectionsController::class, 'store'])->name('conexoes.store');
                Route::patch('/conexoes/{connection}/disconnect', [ConnectionsController::class, 'disconnect'])->name('conexoes.disconnect');
                Route::delete('/conexoes/{connection}', [ConnectionsController::class, 'destroy'])->name('conexoes.destroy');
                Route::post('/conexoes/{connection}/request-qr', [ConnectionsController::class, 'requestQr'])->name('conexoes.requestQr');
                Route::get('/conexoes/{connection}/status', [ConnectionsController::class, 'status'])->name('conexoes.status');
                Route::post('/conexoes/{connection}/cancel-pairing', [ConnectionsController::class, 'cancelPairing'])->name('conexoes.cancelPairing');
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
            Route::get('/inbox/poll', [InboxController::class, 'poll'])->name('inbox.poll');
            Route::get('/inbox/tickets/{ticket}/messages', [InboxController::class, 'messages'])->name('inbox.messages');
            Route::patch('/inbox/tickets/{ticket}/accept', [InboxController::class, 'accept'])->name('inbox.accept');
            Route::patch('/inbox/tickets/{ticket}/close', [InboxController::class, 'close'])->name('inbox.close');
            Route::patch('/inbox/tickets/{ticket}/reopen', [InboxController::class, 'reopen'])->name('inbox.reopen');
            Route::patch('/inbox/tickets/{ticket}/triage', [InboxController::class, 'returnTriage'])->name('inbox.returnTriage');
            Route::patch('/inbox/tickets/{ticket}/unread', [InboxController::class, 'markUnread'])->name('inbox.markUnread');
            Route::post('/inbox/tickets/{ticket}/send', [InboxController::class, 'send'])->name('inbox.send');
        });
    });
});
