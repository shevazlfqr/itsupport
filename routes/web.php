<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LegacyActionController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'create'])->name('login');
Route::post('/login', [AuthController::class, 'store'])->name('login.store');

Route::middleware('auth')->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', fn () => redirect()->route('dashboard'))->name('dashboard.named');

    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/tickets/create', [TicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket}/comments', [TicketController::class, 'comment'])->name('tickets.comments.store');
    Route::post('/tickets/{ticket}/claim', [TicketController::class, 'claim'])->middleware('role:it')->name('tickets.claim');
    Route::patch('/tickets/{ticket}', [TicketController::class, 'update'])->middleware('role:it,admin')->name('tickets.update');
    Route::post('/tickets/{ticket}/resolution', [TicketController::class, 'resolution'])->name('tickets.resolution');
    Route::get('/tickets-export', [DashboardController::class, 'export'])->middleware('role:it,admin')->name('tickets.export');
    Route::get('/api/updates', [DashboardController::class, 'updates'])->name('updates');

    Route::middleware('role:admin')->group(function (): void {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
    });
});

Route::match(['get', 'post'], '/index.php', function (Request $request) {
    if ($request->isMethod('get')) {
        if ($request->input('api') === 'updates') {
            abort_unless($request->user(), 401);

            return app(DashboardController::class)->updates($request);
        }
        if ($request->input('export') === 'csv') {
            abort_unless($request->user(), 401);

            return app(DashboardController::class)->export($request);
        }
        if (! $request->user()) {
            return redirect()->route('login');
        }

        return app(DashboardController::class)->index($request);
    }

    return app(LegacyActionController::class)($request);
})->name('legacy.front-controller');

Route::post('/', LegacyActionController::class)->name('legacy.actions');
