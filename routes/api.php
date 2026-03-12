<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Global\GlobalCompanyController;
use App\Http\Controllers\Global\GlobalModuleController;
use App\Http\Controllers\MasterData\MasterAccountController;
use App\Http\Controllers\MasterData\MasterAccountGroupController;
use App\Http\Controllers\MasterData\MasterBrandController;
use App\Http\Controllers\MasterData\MasterCashController;
use App\Http\Controllers\MasterData\MasterCustomerController;
use App\Http\Controllers\MasterData\MasterSparepartCategoryController;
use App\Http\Controllers\MasterData\MasterSparepartController;
use App\Http\Controllers\MasterData\MasterSupplierController;
use App\Http\Controllers\MasterData\MasterUnitTypeController;
use App\Http\Controllers\Permission\PermissionController;
use App\Http\Controllers\Role\RoleController;
use App\Http\Controllers\Transaction\TransactionFlowController;
use App\Http\Controllers\Transaction\UnitTransactionBillingController as UnitTransactionBillingPurchaseController;
use App\Http\Controllers\Transaction\UnitTransactionController as UnitTransactionPurchaseController;
use App\Http\Controllers\Transaction\UnitTransactionItemController as UnitTransactionItemPurchaseController;
use App\Http\Controllers\Transaction\UnitTransactionItemDetailController as UnitTransactionItemDetailPurchaseController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\Warehouse\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::options('{any}', function () {
    return response()->json([], 200);
})->where('any', '.*');

Route::group(
    [
        'middleware' => 'api',
    ],
    function () {
        // Auth API
        Route::group(['prefix' => 'auth', 'as' => 'users.'], function () {
            Route::post('login', [AuthController::class, 'login']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('refresh', [AuthController::class, 'refresh']);
            Route::get('me', [AuthController::class, 'me']);
            Route::put('me', [AuthController::class, 'update']);
            Route::put('new-password', [AuthController::class, 'newPassword'])->name('new-password');
            Route::post('forgot-password', [AuthController::class, 'sendResetLink'])->name('forgot-password');
            Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
        });

        // User API
        Route::group(['prefix' => 'users', 'as' => 'users.'], function () {
            Route::apiResource('/', UserController::class)->parameters(['' => 'user']);
            Route::post('{id}/assign-role', [UserController::class, 'assignRole'])->name('assign-role');
            Route::post('{id}/revoke-role', [UserController::class, 'revokeRole'])->name('revoke-role');
            Route::put('{id}/activate-user', [UserController::class, 'activateUser'])->name('activate-user');
            Route::put('{id}/deactivate-user', [UserController::class, 'deactivateUser'])->name('deactivate-user');
            Route::get('{id}/get-user-password', [UserController::class, 'getUserPassword'])->name('get-user-password');
            Route::get('/action/get-status', [UserController::class, 'getStatus'])->name('get-status');
        });

        // Role API
        Route::group(['prefix' => 'roles', 'as' => 'roles.'], function () {
            Route::apiResource('', RoleController::class)->parameters(['' => 'roles']);
            Route::get('{id}/without-permissions', [RoleController::class, 'showWithoutPermissions'])->name('without-permissions');
            Route::post('{id}/assign-permissions', [RoleController::class, 'assignPermissions'])->name('assign-permissions');
        });

        // Permission API
        Route::group(['prefix' => 'permissions', 'as' => 'permission.'], function () {
            Route::get('', [PermissionController::class, 'index']);
            Route::get('{id}', [PermissionController::class, 'show']);
        });

        // Global API
        Route::group(['prefix' => 'global', 'as' => 'global.'], function () {
            Route::apiResource('company', GlobalCompanyController::class);
            Route::apiResource('module', GlobalModuleController::class);

            // Additional Route
            Route::put('company-assign-module/{id}', [GlobalCompanyController::class, 'assignModule']);
            Route::get('convert-idr-to-usd', [GlobalCompanyController::class, 'covertIdrToUsd']);
            Route::group(['prefix' => 'company', 'as' => 'company.id'], function () {
                Route::get('slug/{slug}', [GlobalCompanyController::class, 'showBySlug'])->name('by-slug');
            });
        });

        // Master Data API
        Route::group(['prefix' => 'master-data', 'as' => 'master-data.'], function () {
            Route::apiResource('account-group', MasterAccountGroupController::class);
            Route::apiResource('account', MasterAccountController::class);
            Route::apiResource('cash', MasterCashController::class);
            Route::apiResource('customer', MasterCustomerController::class);
            Route::apiResource('supplier', MasterSupplierController::class);
            Route::apiResource('brand', MasterBrandController::class);
            Route::apiResource('unit-type', MasterUnitTypeController::class);
            Route::apiResource('sparepart-category', MasterSparepartCategoryController::class);
            Route::apiResource('sparepart', MasterSparepartController::class);
        });

        // Warehouse API
        Route::group(['prefix' => 'warehouse', 'as' => 'warehouse.'], function () {
            Route::get('warehouse-stock/{id}', [WarehouseController::class, 'getStock']);
            Route::get('warehouse-get-stock/{id}', [WarehouseController::class, 'getWarehouseStock']);
            Route::get('warehouse-unit-transaction-data/{id}', [WarehouseController::class, 'getUnitTransaction']);
            Route::get('warehouse-unit-transaction-item-data/{id}', [WarehouseController::class, 'getUnitTransactionItem']);

            Route::apiResource('warehouse-data', WarehouseController::class);
        });

        // Transaction API
        Route::group(['prefix' => 'transaction', 'as' => 'transaction.'], function () {
            Route::apiResource('transaction-flow', TransactionFlowController::class);

            // Unit Transaction API
            Route::group(['prefix' => 'unit-transaction', 'as' => 'unit-transaction.'], function () {
                // Additional Route
                Route::put('unit-transaction/{id}/update-state', [UnitTransactionPurchaseController::class, 'updateState'])->name('update-state');
                Route::get('unit-transaction-item/get-formula', [UnitTransactionItemPurchaseController::class, 'getFormula'])->name('get-formula');
                Route::delete('unit-transaction-item/transcation-item-detail-bulk-delete/{id}', [UnitTransactionItemPurchaseController::class, 'bulkDelete'])->name('bulk-delete');

                Route::apiResource('unit-transaction', UnitTransactionPurchaseController::class);
                Route::apiResource('unit-transaction-item', UnitTransactionItemPurchaseController::class);
                Route::apiResource('unit-transaction-item-detail', UnitTransactionItemDetailPurchaseController::class);
                Route::apiResource('unit-transaction-billing', UnitTransactionBillingPurchaseController::class);
            });

            // Unit Sales API
            // Route::group(['prefix' => 'unit-sales', 'as' => 'unit-sales.'], function() {
            //     Route::apiResource('unit-transaction', UnitSalesController::class);
            //     Route::apiResource('unit-transaction-item', UnitSalesController::class);
            //     Route::apiResource('unit-transaction-billing', UnitSalesController::class);
            // });
        });
    },
);
