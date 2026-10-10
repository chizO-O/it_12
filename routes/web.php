<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LotController;
use App\Http\Controllers\PropertyController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
})->name('home');

Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->middleware(['guest', 'throttle:10,1'])->name('google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->middleware(['auth.session', 'throttle:10,1'])->name('google.callback');

Route::middleware(['auth', 'auth.session', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'redirect'])->name('dashboard');
    Route::get('/buyer/dashboard', [DashboardController::class, 'show'])->middleware('role:buyer')->name('buyer.dashboard');
    Route::get('/agent/dashboard', [DashboardController::class, 'show'])->middleware('role:agent')->name('agent.dashboard');
    Route::get('/office/dashboard', [DashboardController::class, 'show'])->middleware('role:admin,unit_manager,field_planner,cashier')->name('office.dashboard');
    Route::view('/account', 'account')->name('account');
    Route::patch('/account', [AccountController::class, 'update'])->name('account.update');
    Route::get('/account/google', [GoogleAuthController::class, 'link'])->middleware('password.confirm')->name('google.link');

    Route::scopeBindings()->group(function () {
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('/inventory/{property}', [InventoryController::class, 'show'])->name('inventory.show');
        Route::get('/inventory/{property}/lots/{lot}', [InventoryController::class, 'lot'])->name('inventory.lot');
        Route::resource('properties', PropertyController::class);
        Route::resource('properties.lots', LotController::class)->except(['index', 'show']);
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::post('/employees', [EmployeeController::class, 'store'])->middleware('throttle:10,1')->name('employees.store');
        Route::patch('/employees/{employee}/role', [EmployeeController::class, 'updateRole'])->middleware('throttle:10,1')->name('employees.role');
        Route::post('/employees/{employee}/invitation', [EmployeeController::class, 'invite'])->middleware('throttle:10,1')->name('employees.invite');
    });
});
