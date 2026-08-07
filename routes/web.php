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

Route::get('/debug-sync/{entity}', function($entity) {
    \Illuminate\Support\Facades\Artisan::call('cache:clear');
    \Illuminate\Support\Facades\Artisan::call('config:clear');
    \Illuminate\Support\Facades\Artisan::call('route:clear');
    \Illuminate\Support\Facades\Artisan::call('view:clear');
    return response()->json(['success' => true, 'message' => 'Caches cleared.']);
});

Route::get('/test-save-filter', function() {
    $entity = 'vdatamentahsalesorder';
    $customFilters = [
        ['field' => 'OrderDate', 'operator' => 'ge', 'value' => '2026-06-30T00:00:00.000Z']
    ];

    \App\Models\SyncSchedule::updateOrCreate(
        ['entity_key' => $entity],
        ['custom_filters' => $customFilters]
    );

    $sched = \App\Models\SyncSchedule::where('entity_key', $entity)->first();
    return response()->json([
        'raw' => $sched->getRawOriginal('custom_filters'),
        'casted' => $sched->custom_filters
    ]);
});

Route::get('/debug-odata/{entity}', function($entity) {
    try {
        $entityConfig = config("acumatica.entities.{$entity}");
        if (!$entityConfig) return response()->json(['error' => 'Not found']);
        
        $odataService = app(\App\Services\AcumaticaODataService::class);
        
        $sourceConnKey = $entityConfig['source_connection'] ?? 'acumatica_default';
        $sourceConnection = app(\App\Services\ConnectionService::class)->get($sourceConnKey);
        if ($sourceConnection && ($sourceConnection['type'] ?? 'odata') === 'odata') {
            $odataService->setConnectionConfig($sourceConnection);
        }

        // Login if needed
        if ($odataService->usesSessionAuth()) {
            $odataService->login();
        }
        
        $result = $odataService->fetchPage($entityConfig['odata_entity'], 10, 0, '', true);
        
        return response()->json([
            'raw_response' => $result
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()]);
    }
});

/*
|--------------------------------------------------------------------------
| System Utilities (VPS Deployment)
|--------------------------------------------------------------------------
*/
Route::get('/sys-cron', [SystemUtilityController::class, 'runWebCron'])->name('sys.cron');
Route::get('/sys-migrate', [SystemUtilityController::class, 'migrate'])->name('sys.migrate');
Route::get('/sys-migrate-fresh', [SystemUtilityController::class, 'migrateFresh'])->name('sys.migrate-fresh');
Route::get('/sys-install-default', [SystemUtilityController::class, 'installDefault'])->name('sys.install-default');
Route::get('/sys-optimize', [SystemUtilityController::class, 'optimize'])->name('sys.optimize');
Route::get('/sys-clear-logs', [SystemUtilityController::class, 'clearLogs'])->name('sys.clear-logs');
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

Route::get('/dev/debug-logs', function() {
    $schedules = \App\Models\SyncSchedule::all();
    return response()->json([
        'schedules' => $schedules
    ]);
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
        Route::post('/sync/control/{id}', [SyncController::class, 'control'])->name('sync.control');
        Route::post('/sync/{entity}', [SyncController::class, 'syncEntity'])->name('sync.entity');

        // New Sync History & Progress MVP Routes
        Route::get('/sync-history', [\App\Http\Controllers\SyncJobController::class, 'page'])->name('sync-history.index');
        Route::get('/api/sync-jobs', [\App\Http\Controllers\SyncJobController::class, 'index'])->name('api.sync-jobs.index');
        Route::get('/api/sync-jobs/active', [\App\Http\Controllers\SyncJobController::class, 'active'])->name('api.sync-jobs.active');
        Route::get('/api/sync-jobs/{id}', [\App\Http\Controllers\SyncJobController::class, 'show'])->name('api.sync-jobs.show');
        
        // Scheduler MVP Routes
        Route::get('/scheduler', [\App\Http\Controllers\ScheduleController::class, 'index'])->name('scheduler.index');
        Route::get('/api/scheduler/{entity}', [\App\Http\Controllers\ScheduleController::class, 'show'])->name('api.scheduler.show');
        Route::put('/api/scheduler/{entity}', [\App\Http\Controllers\ScheduleController::class, 'update'])->name('api.scheduler.update');
        Route::patch('/api/scheduler/{entity}/toggle', [\App\Http\Controllers\ScheduleController::class, 'toggle'])->name('api.scheduler.toggle');
        Route::post('/api/scheduler/preview', [\App\Http\Controllers\ScheduleController::class, 'preview'])->name('api.scheduler.preview');
        Route::post('/api/scheduler/{entity}/run-now', [\App\Http\Controllers\ScheduleController::class, 'runNow'])->name('api.scheduler.run-now');
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
        Route::get('/api/report-builder/progress', [ReportBuilderController::class, 'checkProgress']);
        Route::get('/api/report-builder/download/{token}', [ReportBuilderController::class, 'download'])->name('report.builder.download');
    });

    Route::middleware(['permission:query_builder.manage'])->group(function () {
        Route::get('/query-builder', [QueryBuilderController::class, 'index'])->name('query.builder');
        Route::get('/api/query-builder/tables', [QueryBuilderController::class, 'tables']);
        Route::get('/api/query-builder/columns', [QueryBuilderController::class, 'columns']);
        Route::post('/api/query-builder/execute', [QueryBuilderController::class, 'execute']);
        Route::post('/api/query-builder/export', [QueryBuilderController::class, 'export']);
        Route::post('/api/query-builder/save', [QueryBuilderController::class, 'save']);
        Route::get('/api/query-builder/download/{token}', [QueryBuilderController::class, 'download'])->name('query.builder.download');
    });

    Route::middleware(['permission:query_repository.view'])->group(function () {
        Route::resource('/report/query-repository', \App\Http\Controllers\QueryRepositoryController::class)->names([
            'index' => 'query-repository.index',
            'create' => 'query-repository.create',
            'store' => 'query-repository.store',
            'show' => 'query-repository.show',
            'edit' => 'query-repository.edit',
            'update' => 'query-repository.update',
            'destroy' => 'query-repository.destroy',
        ]);
    });

    Route::middleware(['permission:query_repository.preview'])->group(function () {
        Route::post('/api/report/query-repository/preview', [\App\Http\Controllers\QueryRepositoryController::class, 'preview'])->name('api.query-repository.preview');
        Route::post('/api/report/query-repository/export', [\App\Http\Controllers\QueryRepositoryController::class, 'export'])->name('api.query-repository.export');
        Route::get('/api/report/query-repository/download/{token}', [\App\Http\Controllers\QueryRepositoryController::class, 'download'])->name('api.query-repository.download');
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
        
        // Enterprise API endpoints for Logs
        Route::get('/api/sync/logs', [\App\Http\Controllers\LogApiController::class, 'index'])->name('api.sync.logs');
        Route::post('/api/sync/logs/bulk', [\App\Http\Controllers\LogApiController::class, 'bulkAction'])->name('api.sync.logs.bulk');
        Route::get('/api/sync/analytics', [\App\Http\Controllers\LogApiController::class, 'analytics'])->name('api.sync.analytics');
        Route::get('/api/sync/export', [\App\Http\Controllers\LogApiController::class, 'export'])->name('api.sync.export');
        Route::get('/api/sync/logs/{id}/details', [\App\Http\Controllers\LogApiController::class, 'show'])->name('api.sync.logs.details');
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
        Route::post('/api/connections/diagnostics', [SettingsController::class, 'runDiagnostics'])->name('api.connections.diagnostics');
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
        Route::post('/menu-management/refresh-files/{route_name}', [App\Http\Controllers\MenuManagementController::class, 'refreshGeneratedFiles'])->name('menu-management.refresh-files');
    });
    // Auto-generated route for Receivables
    Route::get('/receivables/index', [\App\Http\Controllers\ReceivablesIndexController::class, 'index'])->name('receivables.index');
    // Auto-generated route for Master Warehouse
    Route::get('/master-warehouse/index', [\App\Http\Controllers\MasterWarehouseIndexController::class, 'index'])->name('master-warehouse.index');
});
