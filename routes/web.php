<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DynamicReportController;
use App\Http\Controllers\EntityController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\ReportBuilderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\QueryBuilderController;
use App\Http\Controllers\SystemUtilityController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| System Utilities (VPS Deployment)
|--------------------------------------------------------------------------
*/
Route::get('/sys-cron', [SystemUtilityController::class, 'runWebCron'])->name('sys.cron');
Route::get('/sys-migrate', [SystemUtilityController::class, 'migrate'])->name('sys.migrate');
Route::get('/sys-migrate-fresh', [SystemUtilityController::class, 'migrateFresh'])->name('sys.migrate-fresh');
Route::get('/sys-optimize', [SystemUtilityController::class, 'optimize'])->name('sys.optimize');
Route::get('/sys-artisan/{command}', [SystemUtilityController::class, 'runArtisan'])->name('sys.artisan');

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
| Secured API Data Endpoints (Basic Auth for PowerBI/Excel)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth.basic'])->group(function () {
    Route::get('/data/{slug}', [\App\Http\Controllers\Api\DataEndpointController::class, 'getData'])->name('api.data.endpoint');
});

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

    Route::middleware(['permission:sync_engine.manage'])->group(function () {
        Route::post('/sync/all', [SyncController::class, 'syncAll'])->name('sync.all');
        Route::post('/sync/stop/{id}', [SyncController::class, 'stopQueue'])->name('sync.stop');
        Route::post('/sync/{entity}', [SyncController::class, 'syncEntity'])->name('sync.entity');
        Route::get('/sync/{entity}/progress', [SyncController::class, 'progress'])->name('sync.progress');
        Route::get('/sync/status', [SyncController::class, 'status'])->name('sync.status');
    });

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

    Route::middleware(['permission:sync_engine.manage'])->group(function () {
        Route::get('/api/entities', [EntityController::class, 'index'])->name('api.entities.index');
        Route::get('/api/entities/{key}', [EntityController::class, 'show'])->name('api.entities.show');
        Route::post('/api/entities', [EntityController::class, 'store'])->name('api.entities.store');
        Route::put('/api/entities/{key}', [EntityController::class, 'update'])->name('api.entities.update');
        Route::post('/api/entities/{key}/schedule', [EntityController::class, 'updateSchedule'])->name('api.entities.schedule');
        Route::delete('/api/entities/{key}', [EntityController::class, 'destroy'])->name('api.entities.destroy');
    });

    Route::get('/api/entities/{entity}/fields', [SyncController::class, 'entityFields'])->name('api.entities.fields');
    Route::post('/api/entities/{entity}/filters', [SyncController::class, 'saveFilters'])->name('api.entities.filters.save');

    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    */

    Route::middleware(['auth'])->group(function () {
        Route::get('/report/sales-order', [ReportController::class, 'salesOrder'])->name('report.sales-order');
    });

    Route::middleware(['permission:report_builder.manage'])->group(function () {
        Route::get('/report-builder', [ReportBuilderController::class, 'index'])->name('report.builder');
        Route::get('/api/report-builder/connections', [ReportBuilderController::class, 'connections']);
        Route::get('/api/report-builder/tables', [ReportBuilderController::class, 'tables']);
        Route::get('/api/report-builder/columns', [ReportBuilderController::class, 'columns']);
        Route::post('/api/report-builder/prepare', [ReportBuilderController::class, 'prepare']);
        Route::get('/api/report-builder/download/{token}', [ReportBuilderController::class, 'download'])->name('report.builder.download');
    });

    Route::middleware(['permission:query_builder.manage'])->group(function () {
        Route::get('/query-builder', [QueryBuilderController::class, 'index'])->name('query.builder');
        Route::get('/api/query-builder/tables', [QueryBuilderController::class, 'tables']);
        Route::get('/api/query-builder/columns', [QueryBuilderController::class, 'columns']);
        Route::post('/api/query-builder/execute', [QueryBuilderController::class, 'execute']);
        Route::post('/api/query-builder/export', [QueryBuilderController::class, 'export']);
        Route::get('/api/query-builder/download/{token}', [QueryBuilderController::class, 'download'])->name('query.builder.download');
    });

    Route::middleware(['permission:endpoint_api.manage'])->group(function () {
        Route::get('/endpoint-api', [\App\Http\Controllers\EndpointApiController::class, 'index'])->name('endpoint.api.index');
        Route::get('/endpoint-api/create', [\App\Http\Controllers\EndpointApiController::class, 'create'])->name('endpoint.api.create');
        Route::post('/endpoint-api', [\App\Http\Controllers\EndpointApiController::class, 'store'])->name('endpoint.api.store');
        Route::get('/endpoint-api/{id}/edit', [\App\Http\Controllers\EndpointApiController::class, 'edit'])->name('endpoint.api.edit');
        Route::put('/endpoint-api/{id}', [\App\Http\Controllers\EndpointApiController::class, 'update'])->name('endpoint.api.update');
        Route::delete('/endpoint-api/{id}', [\App\Http\Controllers\EndpointApiController::class, 'destroy'])->name('endpoint.api.destroy');
        Route::post('/endpoint-api/{id}/toggle', [\App\Http\Controllers\EndpointApiController::class, 'toggle'])->name('endpoint.api.toggle');
    });

    /*
    |--------------------------------------------------------------------------
    | Logs & Settings
    |--------------------------------------------------------------------------
    */

    Route::middleware(['permission:logs.view'])->group(function () {
        Route::get('/logs', [SyncController::class, 'logsPage'])->name('logs.index');
        Route::get('/sync/logs', [SyncController::class, 'logs'])->name('sync.logs');
    });

    Route::middleware(['permission:settings.manage'])->group(function () {
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::get('/settings/system-utilities', [SettingsController::class, 'systemUtilities'])->name('settings.system-utilities');

        // Connections CRUD
        Route::get('/api/connections', [SettingsController::class, 'getConnections'])->name('api.connections.index');
        Route::post('/api/connections', [SettingsController::class, 'storeConnection'])->name('api.connections.store');
        Route::put('/api/connections/{key}', [SettingsController::class, 'updateConnection'])->name('api.connections.update');
        Route::delete('/api/connections/{key}', [SettingsController::class, 'destroyConnection'])->name('api.connections.destroy');
        Route::post('/api/connections/test', [SettingsController::class, 'testConnection'])->name('api.connections.test');
        Route::post('/api/connections/{key}/disconnect', [SettingsController::class, 'disconnectConnection'])->name('api.connections.disconnect');
        Route::post('/api/connections/databases', [SettingsController::class, 'fetchDatabases'])->name('api.connections.databases');
        Route::get('/api/connections/{key}/objects', [SettingsController::class, 'getConnectionObjects'])->name('api.connections.objects');
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
    /*
    |--------------------------------------------------------------------------
    | Menu Management
    |--------------------------------------------------------------------------
    */

    Route::middleware(['permission:settings.manage'])->group(function () {
        Route::get('/menu-management', [App\Http\Controllers\MenuManagementController::class, 'index'])->name('menu-management.index');
        Route::get('/api/menus/tree', [App\Http\Controllers\MenuManagementController::class, 'getTree'])->name('api.menus.tree');
        Route::post('/api/menus', [App\Http\Controllers\MenuManagementController::class, 'store'])->name('api.menus.store');
        Route::put('/api/menus/{id}', [App\Http\Controllers\MenuManagementController::class, 'update'])->name('api.menus.update');
        Route::delete('/api/menus/{id}', [App\Http\Controllers\MenuManagementController::class, 'destroy'])->name('api.menus.destroy');
        Route::post('/api/menus/reorder', [App\Http\Controllers\MenuManagementController::class, 'reorder'])->name('api.menus.reorder');
        Route::get('/api/menus/routes', [App\Http\Controllers\MenuManagementController::class, 'getRoutes'])->name('api.menus.routes');
        Route::get('/api/menus/icons', [App\Http\Controllers\MenuManagementController::class, 'getIcons'])->name('api.menus.icons');
        Route::get('/api/menus/permission-matrix', [App\Http\Controllers\MenuManagementController::class, 'getPermissionMatrix'])->name('api.menus.permission-matrix');
        Route::post('/api/menus/toggle-role-permission', [App\Http\Controllers\MenuManagementController::class, 'toggleRolePermission'])->name('api.menus.toggle-role-permission');
        Route::post('/api/menus/publish', [App\Http\Controllers\MenuManagementController::class, 'publish'])->name('api.menus.publish');
    });
    // Auto-generated route for Receivables
    Route::get('/receivables/index', [\App\Http\Controllers\ReceivablesIndexController::class, 'index'])->name('receivables.index');
    // Auto-generated route for Piutang
    Route::get('/piutang/index', [\App\Http\Controllers\PiutangIndexController::class, 'index'])->name('piutang.index');
    // Auto-generated route for Aging Piutang
    Route::get('/aging-piutang/index', [\App\Http\Controllers\AgingPiutangIndexController::class, 'index'])->name('aging-piutang.index');
});
