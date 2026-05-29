<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccessControlController;
use App\Http\Controllers\ChargePointDiagnosticsController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\OcppCommandController;
use App\Http\Controllers\ProfileController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'route_permission'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::prefix('master')->name('master.')->group(function () {
        Route::get('/charge-points', [MasterDataController::class, 'chargePoints'])->name('charge-points');
        Route::get('/charge-points/ocpp-live', [MasterDataController::class, 'chargePointsOcppLive'])->name('charge-points.ocpp-live');
        Route::get('/charge-points/{id}/diagnostics', [ChargePointDiagnosticsController::class, 'index'])->name('charge-points.diagnostics.index');
        Route::post('/charge-points/{id}/diagnostics', [ChargePointDiagnosticsController::class, 'store'])->name('charge-points.diagnostics.store');
        Route::get('/diagnostics/{requestId}/download', [ChargePointDiagnosticsController::class, 'download'])->name('diagnostics.download');
        Route::get('/sessions', [MasterDataController::class, 'meterValues'])->name('sessions');
        Route::get('/sessions/live', [MasterDataController::class, 'sessionsLive'])->name('sessions.live');
        Route::post('/sessions/stop', [MasterDataController::class, 'stopSession'])->name('sessions.stop');
        Route::get('/transactions', [MasterDataController::class, 'transactions'])->name('transactions');
        Route::get('/transactions/export', [MasterDataController::class, 'transactionsExport'])->name('transactions.export');
        Route::get('/transactions/{id}/detail', [MasterDataController::class, 'transactionDetail'])->name('transactions.detail');
        Route::get('/companies', [MasterDataController::class, 'companies'])->name('companies');
        Route::get('/timezones', [MasterDataController::class, 'timezoneOptions'])->name('timezones');
        Route::get('/users', [MasterDataController::class, 'users'])->name('users');
        Route::get('/connector-types', [MasterDataController::class, 'connectorTypes'])->name('connector-types');
        Route::get('/stop-reasons', [MasterDataController::class, 'stopReasons'])->name('stop-reasons');
        Route::get('/ocpp-versions', [MasterDataController::class, 'ocppVersions'])->name('ocpp-versions');
        Route::get('/connector-statuses', [MasterDataController::class, 'connectorStatuses'])->name('connector-statuses');
        Route::get('/transaction-statuses', [MasterDataController::class, 'transactionStatuses'])->name('transaction-statuses');
        Route::get('/ocpp-actions', [MasterDataController::class, 'ocppActions'])->name('ocpp-actions');
        Route::get('/meter-measurands', [MasterDataController::class, 'meterMeasurands'])->name('meter-measurands');
        Route::get('/reservation-statuses', [MasterDataController::class, 'reservationStatuses'])->name('reservation-statuses');
        Route::get('/diagnostics-statuses', [MasterDataController::class, 'diagnosticsStatuses'])->name('diagnostics-statuses');

        Route::middleware('role:admin')->group(function () {
            Route::post('/catalog/{catalog}', [MasterDataController::class, 'storeCatalog'])->name('catalog.store');
            Route::patch('/catalog/{catalog}/{id}', [MasterDataController::class, 'updateCatalog'])->name('catalog.update');
            Route::delete('/catalog/{catalog}/{id}', [MasterDataController::class, 'destroyCatalog'])->name('catalog.destroy');
            Route::post('/companies', [MasterDataController::class, 'storeCompany'])->name('companies.store');
            Route::patch('/companies/{id}', [MasterDataController::class, 'updateCompany'])->name('companies.update');
            Route::delete('/companies/{id}', [MasterDataController::class, 'destroyCompany'])->name('companies.destroy');
            Route::post('/charge-points', [MasterDataController::class, 'storeChargePoint'])->name('charge-points.store');
            Route::patch('/charge-points/{id}', [MasterDataController::class, 'updateChargePoint'])->name('charge-points.update');
            Route::delete('/charge-points/{id}', [MasterDataController::class, 'destroyChargePoint'])->name('charge-points.destroy');
            Route::post('/users', [MasterDataController::class, 'storeUser'])->name('users.store');
            Route::patch('/users/{id}', [MasterDataController::class, 'updateUser'])->name('users.update');
            Route::delete('/users/{id}', [MasterDataController::class, 'destroyUser'])->name('users.destroy');
        });
    });

    Route::prefix('ocpp')->name('ocpp.')->group(function () {
        Route::get('/commands', [OcppCommandController::class, 'index'])->name('commands.index');
        Route::post('/commands', [OcppCommandController::class, 'store'])->name('commands.store');
    });

    Route::prefix('access-control')->name('access-control.')->group(function () {
        Route::get('/', [AccessControlController::class, 'index'])->name('index');
        Route::get('/roles', [AccessControlController::class, 'rolesPage'])->name('roles.index');
        Route::get('/permissions', [AccessControlController::class, 'permissionsPage'])->name('permissions.index');
        Route::post('/permissions', [AccessControlController::class, 'storePermission'])->name('permissions.store');
        Route::post('/permissions/sync-routes', [AccessControlController::class, 'syncRoutePermissions'])->name('permissions.sync-routes');

        Route::post('/roles', [AccessControlController::class, 'storeRole'])->name('roles.store');
        Route::delete('/roles/{role}', [AccessControlController::class, 'destroyRole'])->name('roles.destroy');
        Route::put('/roles/{role}/permissions', [AccessControlController::class, 'syncRolePermissions'])->name('roles.sync-permissions');
        Route::put('/users/{user}/role', [AccessControlController::class, 'assignUserRole'])->name('users.assign-role');
    });
});

require __DIR__ . '/auth.php';
