<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DynamicReportController;
use App\Http\Controllers\EntityController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication (public)
|--------------------------------------------------------------------------
*/

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| Protected Routes (require login)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/api/connection-status', [DashboardController::class, 'connectionStatus'])->name('api.connection-status');

    /*
    |--------------------------------------------------------------------------
    | Sync Operations
    |--------------------------------------------------------------------------
    */

    Route::post('/sync/all', [SyncController::class, 'syncAll'])->name('sync.all');
    Route::post('/sync/{entity}', [SyncController::class, 'syncEntity'])->name('sync.entity');
    Route::get('/sync/logs', [SyncController::class, 'logs'])->name('sync.logs');
    Route::get('/sync/status', [SyncController::class, 'status'])->name('sync.status');

    /*
    |--------------------------------------------------------------------------
    | Master Data
    |--------------------------------------------------------------------------
    */

    Route::get('/master-data', [MasterDataController::class, 'index'])->name('master-data.index');
    Route::get('/master-data/{entity}', [MasterDataController::class, 'show'])->name('master-data.show');

    /*
    |--------------------------------------------------------------------------
    | Transactions
    |--------------------------------------------------------------------------
    */

    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/{entity}', [TransactionController::class, 'show'])->name('transactions.show');

    /*
    |--------------------------------------------------------------------------
    | Entity CRUD (API-style, used by Settings page)
    |--------------------------------------------------------------------------
    */

    Route::middleware(['permission:entities.manage'])->group(function () {
        Route::get('/api/entities', [EntityController::class, 'index'])->name('api.entities.index');
        Route::get('/api/entities/{key}', [EntityController::class, 'show'])->name('api.entities.show');
        Route::post('/api/entities', [EntityController::class, 'store'])->name('api.entities.store');
        Route::put('/api/entities/{key}', [EntityController::class, 'update'])->name('api.entities.update');
        Route::delete('/api/entities/{key}', [EntityController::class, 'destroy'])->name('api.entities.destroy');
    });

    Route::get('/api/entities/{entity}/fields', [SyncController::class, 'entityFields'])->name('api.entities.fields');

    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    */

    Route::middleware(['permission:reports.view'])->group(function () {
        Route::get('/report/sales-order', [ReportController::class, 'salesOrder'])->name('report.sales-order');
        Route::get('/report/{entityKey}', [DynamicReportController::class, 'show'])->name('report.dynamic.show');
        Route::get('/report/{entityKey}/export', [DynamicReportController::class, 'export'])->name('report.dynamic.export');
    });

    /*
    |--------------------------------------------------------------------------
    | Logs & Settings
    |--------------------------------------------------------------------------
    */

    Route::get('/logs', [SyncController::class, 'logsPage'])->name('logs.index');

    Route::middleware(['permission:settings.manage'])->group(function () {
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/api/settings/acumatica', [SettingsController::class, 'updateAcumatica'])->name('api.settings.acumatica');
        Route::post('/api/settings/database', [SettingsController::class, 'updateDatabase'])->name('api.settings.database');
        Route::post('/api/settings/test-connection', [SettingsController::class, 'testConnection'])->name('api.settings.test-connection');
    });

    /*
    |--------------------------------------------------------------------------
    | User Management (admin only)
    |--------------------------------------------------------------------------
    */

    Route::middleware(['permission:users.manage'])->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::post('/api/users', [UserManagementController::class, 'storeUser'])->name('api.users.store');
        Route::put('/api/users/{user}', [UserManagementController::class, 'updateUser'])->name('api.users.update');
        Route::delete('/api/users/{user}', [UserManagementController::class, 'destroyUser'])->name('api.users.destroy');
        Route::post('/api/users/{user}/reset-password', [UserManagementController::class, 'resetPassword'])->name('api.users.reset-password');

        Route::get('/api/roles/{role}', [UserManagementController::class, 'showRole'])->name('api.roles.show');
        Route::post('/api/roles', [UserManagementController::class, 'storeRole'])->name('api.roles.store');
        Route::put('/api/roles/{role}/permissions', [UserManagementController::class, 'updateRolePermissions'])->name('api.roles.permissions');
        Route::delete('/api/roles/{role}', [UserManagementController::class, 'destroyRole'])->name('api.roles.destroy');

        Route::get('/api/permissions', [UserManagementController::class, 'permissions'])->name('api.permissions.index');
    });
});
