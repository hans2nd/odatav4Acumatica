<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EntityController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

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

Route::get('/api/entities', [EntityController::class, 'index'])->name('api.entities.index');
Route::get('/api/entities/{key}', [EntityController::class, 'show'])->name('api.entities.show');
Route::post('/api/entities', [EntityController::class, 'store'])->name('api.entities.store');
Route::put('/api/entities/{key}', [EntityController::class, 'update'])->name('api.entities.update');
Route::delete('/api/entities/{key}', [EntityController::class, 'destroy'])->name('api.entities.destroy');

/*
|--------------------------------------------------------------------------
| Logs & Settings
|--------------------------------------------------------------------------
*/

Route::get('/logs', [SyncController::class, 'logsPage'])->name('logs.index');
Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
Route::post('/api/settings/acumatica', [SettingsController::class, 'updateAcumatica'])->name('api.settings.acumatica');
Route::post('/api/settings/database', [SettingsController::class, 'updateDatabase'])->name('api.settings.database');
Route::post('/api/settings/test-connection', [SettingsController::class, 'testConnection'])->name('api.settings.test-connection');

