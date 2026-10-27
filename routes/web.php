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
use App\Http\Controllers\ReportTemplateController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;



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

    Route::middleware(['permission:sync_engine.view'])->group(function () {
        // Sync History (View Only)
        Route::get('/sync-history', [\App\Http\Controllers\SyncJobController::class, 'page'])->name('sync-history.index');
        Route::get('/api/sync-jobs', [\App\Http\Controllers\SyncJobController::class, 'index'])->name('api.sync-jobs.index');
        Route::get('/api/sync-jobs/active', [\App\Http\Controllers\SyncJobController::class, 'active'])->name('api.sync-jobs.active');
        Route::get('/api/sync-jobs/{id}', [\App\Http\Controllers\SyncJobController::class, 'show'])->name('api.sync-jobs.show');
        Route::post('/api/sync-jobs/{id}/abort', [\App\Http\Controllers\SyncJobController::class, 'abort'])->name('api.sync-jobs.abort');
        
        // Scheduler (View Only)
        Route::get('/scheduler', [\App\Http\Controllers\ScheduleController::class, 'index'])->name('scheduler.index');
        Route::get('/api/scheduler/{entity}', [\App\Http\Controllers\ScheduleController::class, 'show'])->name('api.scheduler.show');
        Route::post('/api/scheduler/preview', [\App\Http\Controllers\ScheduleController::class, 'preview'])->name('api.scheduler.preview');
    });

    Route::middleware(['permission:sync_engine.manage'])->group(function () {
        Route::post('/sync/all', [SyncController::class, 'syncAll'])->name('sync.all');
        Route::post('/sync/control/{id}', [SyncController::class, 'control'])->name('sync.control');
        Route::get('/sync/{entity}/status', [SyncController::class, 'entitySyncStatus'])->name('sync.entity-status');
        Route::post('/sync/{entity}/reset-stuck', [SyncController::class, 'resetStuckJob'])->name('sync.reset-stuck');
        Route::post('/sync/{entity}', [SyncController::class, 'syncEntity'])->name('sync.entity');

        // Scheduler (Manage)
        Route::put('/api/scheduler/{entity}', [\App\Http\Controllers\ScheduleController::class, 'update'])->name('api.scheduler.update');
        Route::patch('/api/scheduler/{entity}/toggle', [\App\Http\Controllers\ScheduleController::class, 'toggle'])->name('api.scheduler.toggle');
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
        Route::get('/api/entities/{key}/mappings', [EntityController::class, 'getMappings'])->name('api.entities.mappings.get');
        Route::put('/api/entities/{key}/mappings', [EntityController::class, 'updateMappings'])->name('api.entities.mappings.update');
        Route::post('/api/entities/{key}/discover', [EntityController::class, 'discover'])->name('api.entities.discover');
        Route::post('/api/entities/{key}/suggest-fields', [EntityController::class, 'suggestFields'])->name('api.entities.suggestFields');
        Route::post('/api/entities/batch-delete', [EntityController::class, 'batchDestroy'])->name('api.entities.batchDestroy');
        Route::delete('/api/entities/{key}', [EntityController::class, 'destroy'])->name('api.entities.destroy');
        Route::get('/api/entities/{entity}/fields', [SyncController::class, 'entityFields'])->name('api.entities.fields');
        Route::post('/api/entities/{entity}/filters', [SyncController::class, 'saveFilters'])->name('api.entities.filters.save');
    });

    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    */

    Route::middleware(['auth'])->group(function () {
        Route::get('/report/dynamic/{menuId}', [App\Http\Controllers\DynamicReportController::class, 'index'])->name('report.dynamic');
        Route::get('/report/sales-order', [ReportController::class, 'salesOrder'])->name('report.sales-order');
    });

    Route::middleware(['permission:report_builder.view'])->group(function () {
        Route::get('/report-builder', [ReportBuilderController::class, 'index'])->name('report.builder');
        Route::get('/api/report-builder/connections', [ReportBuilderController::class, 'connections']);
        Route::get('/api/report-builder/tables', [ReportBuilderController::class, 'tables']);
        Route::get('/api/report-builder/columns', [ReportBuilderController::class, 'columns']);
        Route::post('/api/report-builder/prepare', [ReportBuilderController::class, 'prepare']);
        Route::get('/api/report-builder/progress', [ReportBuilderController::class, 'checkProgress']);
        Route::get('/api/report-builder/download/{token}', [ReportBuilderController::class, 'download'])->name('report.builder.download');
        Route::get('/api/report-templates/{id}/shared-roles', [ReportTemplateController::class, 'getSharedRoles']);
        Route::post('/api/report-templates/{id}/share', [ReportTemplateController::class, 'share']);
        Route::resource('report-templates', ReportTemplateController::class);
    });

    Route::middleware(['permission:query_builder.view'])->group(function () {
        Route::get('/query-builder', [QueryBuilderController::class, 'index'])->name('query.builder');
        Route::get('/api/query-builder/tables', [QueryBuilderController::class, 'tables']);
        Route::get('/api/query-builder/columns', [QueryBuilderController::class, 'columns']);
        Route::get('/api/query-builder/download/{token}', [QueryBuilderController::class, 'download'])->name('query.builder.download');
        Route::post('/api/query-builder/execute', [QueryBuilderController::class, 'execute']);
        Route::post('/api/query-builder/export', [QueryBuilderController::class, 'export']);
        Route::post('/api/query-builder/save', [QueryBuilderController::class, 'save']);
    });

    // Query Repository (Create)
    Route::middleware(['permission:query_repository.create'])->group(function () {
        Route::get('/report/query-repository/create', [\App\Http\Controllers\QueryRepositoryController::class, 'create'])->name('query-repository.create');
        Route::post('/report/query-repository', [\App\Http\Controllers\QueryRepositoryController::class, 'store'])->name('query-repository.store');
    });

    // Query Repository (View)
    Route::middleware(['permission:query_repository.view'])->group(function () {
        Route::get('/report/query-repository', [\App\Http\Controllers\QueryRepositoryController::class, 'index'])->name('query-repository.index');
        Route::get('/report/query-repository/{query_repository}', [\App\Http\Controllers\QueryRepositoryController::class, 'show'])->name('query-repository.show');
        Route::get('/api/report/query-repository/{id}/columns', [\App\Http\Controllers\QueryRepositoryController::class, 'getColumns'])->name('api.query-repository.columns');
    });

    // Query Repository (Update)
    Route::middleware(['permission:query_repository.update'])->group(function () {
        Route::get('/report/query-repository/{query_repository}/edit', [\App\Http\Controllers\QueryRepositoryController::class, 'edit'])->name('query-repository.edit');
        Route::put('/report/query-repository/{query_repository}', [\App\Http\Controllers\QueryRepositoryController::class, 'update'])->name('query-repository.update');
    });

    // Query Repository (Delete)
    Route::middleware(['permission:query_repository.delete'])->group(function () {
        Route::delete('/report/query-repository/{query_repository}', [\App\Http\Controllers\QueryRepositoryController::class, 'destroy'])->name('query-repository.destroy');
    });

    // Query Repository (Execute)
    Route::middleware(['permission:query_repository.execute'])->group(function () {
        Route::post('/api/report/query-repository/preview', [\App\Http\Controllers\QueryRepositoryController::class, 'preview'])->name('api.query-repository.preview');
        Route::post('/api/report/query-repository/export', [\App\Http\Controllers\QueryRepositoryController::class, 'export'])->name('api.query-repository.export');
        Route::get('/api/report/query-repository/download/{token}', [\App\Http\Controllers\QueryRepositoryController::class, 'download'])->name('api.query-repository.download');
    });

    Route::middleware(['permission:endpoint_api.view'])->group(function () {
        Route::get('/endpoint-api', [\App\Http\Controllers\EndpointApiController::class, 'index'])->name('endpoint.api.index');
        Route::get('/endpoint-api/create', [\App\Http\Controllers\EndpointApiController::class, 'create'])->name('endpoint.api.create');
        Route::get('/endpoint-api/{id}/edit', [\App\Http\Controllers\EndpointApiController::class, 'edit'])->name('endpoint.api.edit');
    });

    Route::middleware(['permission:endpoint_api.manage'])->group(function () {
        Route::post('/endpoint-api', [\App\Http\Controllers\EndpointApiController::class, 'store'])->name('endpoint.api.store');
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

    Route::middleware(['permission:settings.view'])->group(function () {
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    });

    Route::middleware(['permission:settings.manage'])->group(function () {
        Route::get('/settings/system-utilities', [SettingsController::class, 'systemUtilities'])->name('settings.system-utilities');

        // System Utilities endpoints (Strict Super Admin Only)
        Route::middleware(['superadmin'])->group(function () {
            Route::get('/deploy', [SystemUtilityController::class, 'deploy'])->name('sys.deploy.web');
            Route::get('/sys-cron', [SystemUtilityController::class, 'runWebCron'])->name('sys.cron');
            Route::get('/sys-migrate', [SystemUtilityController::class, 'migrate'])->name('sys.migrate');
            Route::get('/sys-migrate-fresh', [SystemUtilityController::class, 'migrateFresh'])->name('sys.migrate-fresh');
            Route::get('/sys-install-default', [SystemUtilityController::class, 'installDefault'])->name('sys.install-default');
            Route::get('/sys-optimize', [SystemUtilityController::class, 'optimize'])->name('sys.optimize');
            Route::get('/sys-clear-logs', [SystemUtilityController::class, 'clearLogs'])->name('sys.clear-logs');
            Route::get('/sys-artisan/{command}', [SystemUtilityController::class, 'runArtisan'])->name('sys.artisan');
        });

        // Connections CRUD
        Route::get('/api/connections', [SettingsController::class, 'getConnections'])->name('api.connections.index');
        Route::post('/api/connections', [SettingsController::class, 'storeConnection'])->name('api.connections.store');
        Route::put('/api/connections/{key}', [SettingsController::class, 'updateConnection'])->name('api.connections.update');
        Route::delete('/api/connections/{key}', [SettingsController::class, 'destroyConnection'])->name('api.connections.destroy');
        Route::post('/api/connections/batch-delete', [SettingsController::class, 'batchDestroyConnections'])->name('api.connections.batchDestroy');
        Route::post('/api/connections/test', [SettingsController::class, 'testConnection'])->name('api.connections.test');
        Route::post('/api/connections/diagnostics', [SettingsController::class, 'runDiagnostics'])->name('api.connections.diagnostics');
        Route::post('/api/connections/{key}/disconnect', [SettingsController::class, 'disconnectConnection'])->name('api.connections.disconnect');
        Route::post('/api/connections/databases', [SettingsController::class, 'fetchDatabases'])->name('api.connections.databases');
        Route::get('/api/connections/{key}/objects', [SettingsController::class, 'getConnectionObjects'])->name('api.connections.objects');
        Route::get('/api/connections/{key}/objects/{objectName}/columns', [SettingsController::class, 'getConnectionObjectColumns'])
            ->where('objectName', '.*') // Allow slashes for SharePoint
            ->name('api.connections.objects.columns');
        Route::get('/api/connections/{key}/objects/{objectName}/sheets', [SettingsController::class, 'getConnectionObjectSheets'])
            ->where('objectName', '.*') // Allow slashes for SharePoint
            ->name('api.connections.objects.sheets');
        Route::get('/api/connections/{key}/objects/{objectName}/download', [SettingsController::class, 'downloadConnectionObject'])
            ->where('objectName', '.*') // Allow slashes for SharePoint
            ->name('api.connections.objects.download');
        Route::post('/api/connections/sharepoint/sites', [SettingsController::class, 'fetchSharePointSites'])->name('api.connections.sharepoint.sites');
        Route::post('/api/connections/{key}/set-analytics-source', [SettingsController::class, 'setAnalyticsSource'])->name('api.connections.set-analytics');
    });

    /*
    |--------------------------------------------------------------------------
    | User Management (admin only)
    |--------------------------------------------------------------------------
    */

    Route::middleware(['permission:users.view'])->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('/api/roles/{role}', [UserManagementController::class, 'showRole'])->name('api.roles.show');
        Route::get('/api/permissions', [UserManagementController::class, 'permissions'])->name('api.permissions.index');
    });

    Route::middleware(['permission:users.manage'])->group(function () {
        Route::post('/api/users', [UserManagementController::class, 'storeUser'])->name('api.users.store');
        Route::put('/api/users/{user}', [UserManagementController::class, 'updateUser'])->name('api.users.update');
        Route::delete('/api/users/{user}', [UserManagementController::class, 'destroyUser'])->name('api.users.destroy');
        Route::post('/api/users/{user}/reset-password', [UserManagementController::class, 'resetPassword'])->name('api.users.reset-password');
        Route::post('/api/users/{user}/toggle-active', [UserManagementController::class, 'toggleActiveStatus'])->name('api.users.toggle-active');

        Route::post('/api/roles', [UserManagementController::class, 'storeRole'])->name('api.roles.store');
        Route::put('/api/roles/{role}/permissions', [UserManagementController::class, 'updateRolePermissions'])->name('api.roles.permissions');
        Route::get('/api/roles/{role}/table-permissions', [UserManagementController::class, 'getTablePermissions'])->name('api.roles.table-permissions.get');
        Route::put('/api/roles/{role}/table-permissions', [UserManagementController::class, 'updateTablePermissions'])->name('api.roles.table-permissions.update');
        Route::delete('/api/roles/{role}', [UserManagementController::class, 'destroyRole'])->name('api.roles.destroy');
    });
    /*
    |--------------------------------------------------------------------------
    | Menu Management
    |--------------------------------------------------------------------------
    */

    Route::middleware(['permission:settings.manage'])->group(function () {
        Route::get('/settings/chatedp-analytics', [App\Http\Controllers\ChatEDPAnalyticsController::class, 'index'])->name('settings.chatedp.analytics');
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

    /*
    |--------------------------------------------------------------------------
    | AI Settings
    |--------------------------------------------------------------------------
    */
    Route::middleware(['permission:ai_settings.view'])->group(function () {
        Route::get('/settings/ai/openrouter', [\App\Http\Controllers\AiSettingsController::class, 'index'])->name('settings.ai.openrouter');
    });
    
    Route::middleware(['permission:ai_metadata.view'])->group(function () {
        Route::get('/settings/ai/metadata', [\App\Http\Controllers\AiSettingsController::class, 'metadataIndex'])->name('settings.ai.metadata');
    });

    Route::middleware(['permission:ai_settings.update'])->group(function () {
        Route::post('/settings/ai/openrouter', [\App\Http\Controllers\AiSettingsController::class, 'update'])->name('settings.ai.openrouter.update');
        Route::post('/settings/ai/openrouter/disconnect', [\App\Http\Controllers\AiSettingsController::class, 'disconnect'])->name('settings.ai.openrouter.disconnect');
    });

    Route::middleware(['permission:ai_metadata.update'])->group(function () {
        Route::post('/settings/ai/metadata/refresh', [\App\Http\Controllers\AiSettingsController::class, 'refreshSchema'])->name('settings.ai.metadata.refresh');
        Route::post('/settings/ai/metadata/{id}', [\App\Http\Controllers\AiSettingsController::class, 'metadataUpdate'])->name('settings.ai.metadata.update');
        Route::post('/settings/ai/metadata/{id}/suggest', [\App\Http\Controllers\AiSettingsController::class, 'suggestMetadata'])->name('settings.ai.metadata.suggest');
    });

    Route::middleware(['permission:ai_metadata.view'])->group(function () {
        Route::get('/settings/ai/metadata/{id}/detail', [\App\Http\Controllers\AiSettingsController::class, 'metadataDetail'])->name('settings.ai.metadata.detail');
    });

    Route::middleware(['permission:ai_settings.test'])->group(function () {
        Route::post('/settings/ai/openrouter/test', [\App\Http\Controllers\AiSettingsController::class, 'testConnection'])->name('settings.ai.openrouter.test');
    });

    Route::middleware(['permission:ai_settings.view'])->group(function () {
        Route::get('/settings/ai/openrouter/model-providers/{modelId}', [\App\Http\Controllers\AiSettingsController::class, 'getModelProviders'])->where('modelId', '.*')->name('settings.ai.openrouter.model-providers');
    });

    /*
    |--------------------------------------------------------------------------
    | ChatEDP Data Analysis
    |--------------------------------------------------------------------------
    */
    Route::middleware(['permission:chatedp.access'])->group(function () {
        Route::get('/chatedp', [\App\Http\Controllers\ChatEDPController::class, 'index'])->name('chatedp.index');
        Route::get('/api/chatedp/conversations', [\App\Http\Controllers\ChatEDPController::class, 'conversations'])->name('api.chatedp.conversations');
        Route::post('/api/chatedp/conversations', [\App\Http\Controllers\ChatEDPController::class, 'storeConversation'])->name('api.chatedp.conversations.store');
        Route::get('/api/chatedp/conversations/{id}', [\App\Http\Controllers\ChatEDPController::class, 'showConversation'])->name('api.chatedp.conversations.show');
        Route::post('/api/chatedp/messages', [\App\Http\Controllers\ChatEDPController::class, 'sendMessage'])->name('api.chatedp.messages.send');
        Route::get('/api/chatedp/export', [\App\Http\Controllers\ChatEDPController::class, 'exportExcel'])->name('api.chatedp.export');
        Route::get('/api/chatedp/report', [\App\Http\Controllers\ChatEDPController::class, 'generateReport'])->name('api.chatedp.report');
        Route::delete('/api/chatedp/conversations/{id}', [\App\Http\Controllers\ChatEDPController::class, 'deleteConversation'])->name('api.chatedp.conversations.delete');
        Route::post('/api/chatedp/conversations/batch-delete-by-date', [\App\Http\Controllers\ChatEDPController::class, 'deleteConversationsByDate'])->name('api.chatedp.conversations.batchDeleteByDate');
    });
    // Auto-generated route for AI Settings
    Route::middleware(['permission:ai_settings.view'])->group(function () {
        Route::get('/ai-settings/index', [\App\Http\Controllers\AiSettingsIndexController::class, 'index'])->name('ai-settings.index');
    });
});
