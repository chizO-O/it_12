<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AgentManagementController;
use App\Http\Controllers\BuyerAssessmentController;
use App\Http\Controllers\BuyerInformationCorrectionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationCenterController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\CtsController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeActivationController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\InquiryNotificationController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LotAssignmentController;
use App\Http\Controllers\LotController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\SalesController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
})->name('home');

Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->middleware(['guest', 'throttle:10,1'])->name('google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->middleware(['auth.session', 'throttle:10,1'])->name('google.callback');

Route::middleware(['guest', 'throttle:15,1', \App\Http\Middleware\NoStoreActivationCodes::class])->group(function () {
    Route::get('/activate-employee', [EmployeeActivationController::class, 'create'])->name('employee-activation.create');
    Route::post('/activate-employee', [EmployeeActivationController::class, 'store'])
        ->middleware('throttle:5,1')->name('employee-activation.store');
});

Route::middleware(['auth', 'auth.session', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'redirect'])->name('dashboard');
    // Phase 10: universal in-app notification center and role-scoped office reports.
    Route::get('/notifications', [NotificationCenterController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationCenterController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/open', [NotificationCenterController::class, 'open'])->name('notifications.open');
    Route::get('/reports', [ReportsController::class, 'index'])->middleware('role:admin,unit_manager')->name('reports.index');
    Route::get('/buyer/dashboard', [DashboardController::class, 'show'])->middleware('role:buyer')->name('buyer.dashboard');
    Route::get('/agent/dashboard', [DashboardController::class, 'show'])->middleware('role:agent')->name('agent.dashboard');
    Route::get('/office/dashboard', [DashboardController::class, 'show'])->middleware('role:admin,unit_manager,field_planner,cashier')->name('office.dashboard');
    Route::view('/account', 'account')->name('account');
    Route::patch('/account', [AccountController::class, 'update'])->name('account.update');
    Route::get('/account/google', [GoogleAuthController::class, 'link'])->middleware('password.confirm')->name('google.link');

    Route::scopeBindings()->group(function () {
        Route::middleware('role:buyer,agent,admin,unit_manager')->group(function () {
            Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
            Route::get('/inventory/{property}', [InventoryController::class, 'show'])->name('inventory.show');
            Route::get('/inventory/{property}/lots/{lot}', [InventoryController::class, 'lot'])->name('inventory.lot');
        });
        Route::resource('properties', PropertyController::class);
        Route::resource('properties.lots', LotController::class)->except(['index', 'show']);
    });

    // Phase 6: buyers and assigned Agents may read/reply; Admin sees only inquiry routing metadata.
    Route::get('/inquiries', [InquiryController::class, 'index'])->middleware('role:buyer,agent')->name('inquiries.index');
    Route::post('/inquiries/notifications/read', [InquiryNotificationController::class, 'readAll'])->middleware('role:buyer,agent')->name('inquiries.notifications.read');
    Route::post('/inventory/{property}/lots/{lot}/inquiries', [InquiryController::class, 'store'])
        ->scopeBindings()->middleware(['role:buyer', 'throttle:10,1'])->name('inquiries.store');
    Route::get('/inquiries/{inquiry}', [InquiryController::class, 'show'])->name('inquiries.show');
    Route::post('/inquiries/{inquiry}/messages', [InquiryController::class, 'message'])
        ->middleware('throttle:30,1')->name('inquiries.messages.store');
    Route::get('/inquiries/{inquiry}/updates', [InquiryController::class, 'updates'])
        ->middleware('throttle:90,1')->name('inquiries.updates');

    // Phase 7: reservation requests and buyer-information review.
    Route::get('/reservations', [ReservationController::class, 'index'])->middleware('role:buyer,agent,unit_manager')->name('reservations.index');
    Route::post('/reservations/notifications/read', [ReservationController::class, 'readNotifications'])->middleware('role:buyer,agent,unit_manager')->name('reservations.notifications.read');
    Route::post('/inquiries/{inquiry}/reservations', [ReservationController::class, 'store'])
        ->middleware(['role:agent', 'throttle:10,1'])->name('reservations.store');
    Route::get('/reservations/{reservation}', [ReservationController::class, 'show'])->name('reservations.show');
    Route::put('/reservations/{reservation}/details', [ReservationController::class, 'details'])
        ->middleware('role:buyer')->name('reservations.details');
    Route::put('/reservations/{reservation}/assessment', [BuyerAssessmentController::class, 'update'])
        ->middleware('role:agent')->name('reservations.assessment');
    Route::post('/reservations/{reservation}/corrections', [BuyerInformationCorrectionController::class, 'store'])
        ->middleware(['role:buyer,agent', 'throttle:10,1'])->name('reservations.corrections.store');
    Route::patch('/reservations/{reservation}/corrections/{correction}', [BuyerInformationCorrectionController::class, 'review'])
        ->middleware('role:unit_manager')->name('reservations.corrections.review');
    Route::patch('/reservations/{reservation}/review', [ReservationController::class, 'review'])
        ->middleware('role:unit_manager')->name('reservations.review');
    Route::patch('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])
        ->middleware('role:agent,unit_manager')->name('reservations.cancel');

    // CTS: eligibility approval opens the record automatically; Secretary drafts only after both parties submit their details.
    Route::get('/cts', [CtsController::class, 'index'])->middleware('role:buyer,admin,field_planner')->name('cts.index');
    Route::get('/reservations/{reservation}/cts', [CtsController::class, 'forReservation'])->name('cts.for-reservation');
    // Legacy Secretary-only repair action for reservations approved before auto CTS creation.
    Route::post('/reservations/{reservation}/cts', [CtsController::class, 'initiate'])->middleware('role:admin')->name('cts.initiate');
    Route::get('/cts/{cts}', [CtsController::class, 'show'])->name('cts.show');
    Route::patch('/cts/{cts}/buyer-details', [CtsController::class, 'buyerDetails'])->middleware('role:buyer')->name('cts.buyer-details');
    Route::patch('/cts/{cts}/verify', [CtsController::class, 'verify'])->middleware('role:field_planner')->name('cts.verify');
    Route::patch('/cts/{cts}/prepare', [CtsController::class, 'prepare'])->middleware('role:admin')->name('cts.prepare');
    Route::post('/cts/{cts}/draft', [CtsController::class, 'uploadDraft'])->middleware('role:admin')->name('cts.draft');
    Route::patch('/cts/{cts}/review', [CtsController::class, 'review'])->middleware('role:buyer')->name('cts.review');
    Route::post('/cts/{cts}/complete', [CtsController::class, 'complete'])->middleware('role:admin')->name('cts.complete');
    Route::get('/cts/{cts}/documents/{kind}', [CtsController::class, 'document'])->name('cts.document');

    // Phase 9: confirmed sales terms, installment schedules, and verified physical receipts.
    Route::get('/sales', [SalesController::class, 'index'])->middleware('role:buyer,cashier,unit_manager')->name('sales.index');
    Route::get('/sales/{transaction}', [SalesController::class, 'show'])->name('sales.show');
    Route::post('/sales/{transaction}/confirm', [SalesController::class, 'confirm'])
        ->middleware('role:unit_manager')->name('sales.confirm');
    Route::post('/sales/{transaction}/payments', [SalesController::class, 'payment'])
        ->middleware(['role:cashier', 'throttle:15,1'])->name('sales.payments.store');

    Route::middleware('role:unit_manager')->group(function () {
        Route::get('/agents', [AgentManagementController::class, 'index'])->middleware(\App\Http\Middleware\NoStoreActivationCodes::class)->name('agents.index');
        Route::post('/agents', [AgentManagementController::class, 'store'])->middleware('throttle:10,1')->name('agents.store');
        Route::post('/agents/{agent}/invite', [AgentManagementController::class, 'invite'])->middleware('throttle:10,1')->name('agents.invite');
        Route::post('/agents/{agent}/claim', [AgentManagementController::class, 'claim'])->name('agents.claim');
        Route::get('/lot-assignments', [LotAssignmentController::class, 'index'])->name('assignments.index');
        Route::patch('/lot-assignments/{lot}', [LotAssignmentController::class, 'update'])->name('assignments.update');
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/employees', [EmployeeController::class, 'index'])->middleware(\App\Http\Middleware\NoStoreActivationCodes::class)->name('employees.index');
        Route::post('/employees', [EmployeeController::class, 'store'])->middleware('throttle:10,1')->name('employees.store');
        Route::patch('/employees/{employee}/role', [EmployeeController::class, 'updateRole'])->middleware('throttle:10,1')->name('employees.role');
        Route::post('/employees/{employee}/invitation', [EmployeeController::class, 'invite'])->middleware('throttle:10,1')->name('employees.invite');
    });
});
