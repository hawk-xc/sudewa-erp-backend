<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Global\GlobalCompanyController;
use App\Http\Controllers\Global\GlobalModuleController;
use App\Http\Controllers\MasterData\MasterAccountController;
use App\Http\Controllers\MasterData\MasterAccountGroupController;
use App\Http\Controllers\MasterData\MasterBrandController;
use App\Http\Controllers\MasterData\MasterCashController;
use App\Http\Controllers\MasterData\MasterCustomerController;
use App\Http\Controllers\MasterData\MasterSparepartController;
use App\Http\Controllers\MasterData\MasterSupplierController;
use App\Http\Controllers\MasterData\MasterUnitTypeController;
use App\Http\Controllers\Permission\PermissionController;
use App\Http\Controllers\Role\RoleController;
use App\Http\Controllers\User\UserController;
use Illuminate\Support\Facades\Route;

Route::options('{any}', function () {
    return response()->json([], 200);
})->where('any', '.*');

Route::group(
    [
        'middleware' => 'api',
    ],
    function () {
        /**
         * Authentication Module
         */
        Route::group(['prefix' => 'auth'], function () {
            Route::post('login', [AuthController::class, 'login']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('refresh', [AuthController::class, 'refresh']);
            Route::get('me', [AuthController::class, 'me']);
            Route::put('me', [AuthController::class, 'update']);
            Route::put('new-password', [AuthController::class, 'newPassword'])->name('users.new-password');
            Route::post('forgot-password', [AuthController::class, 'sendResetLink'])->name('users.forgot-password');
            Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('users.reset-password');
        });

        // User API
        Route::group(['prefix' => 'users'], function () {
            Route::apiResource('/', UserController::class)->parameters(['' => 'user']);
            Route::post('{id}/assign-role', [UserController::class, 'assignRole'])->name('users.assign-role');
            Route::post('{id}/revoke-role', [UserController::class, 'revokeRole'])->name('users.revoke-role');
            Route::put('{id}/activate-user', [UserController::class, 'activateUser'])->name('users.activate-user');
            Route::put('{id}/deactivate-user', [UserController::class, 'deactivateUser'])->name('users.deactivate-user');
            Route::get('{id}/get-user-password', [UserController::class, 'getUserPassword'])->name('users.get-user-password');
            Route::get('/action/get-status', [UserController::class, 'getStatus'])->name('users.get-status');
        });

        // Role API
        Route::group(['prefix' => 'roles'], function () {
            Route::apiResource('', RoleController::class)->parameters(['' => 'roles']);
            Route::get('{id}/without-permissions', [RoleController::class, 'showWithoutPermissions'])->name('roles.without-permissions');
            Route::post('{id}/assign-permissions', [RoleController::class, 'assignPermissions'])->name('roles.assign-permissions');
        });

        // Permission API
        Route::group(['prefix' => 'permissions'], function () {
            Route::get('', [PermissionController::class, 'index']);
            Route::get('{id}', [PermissionController::class, 'show']);
        });

        // Global API
        Route::group(['prefix' => 'global'], function () {
            Route::apiResource('company', GlobalCompanyController::class);
            Route::apiResource('module', GlobalModuleController::class);

            // Additional Route
            Route::put('company-assign-module/{id}', [GlobalCompanyController::class, 'assignModule']);
            Route::get('convert-idr-to-usd', [GlobalCompanyController::class, 'covertIdrToUsd']);
        });

        // Master Data API
        Route::group(['prefix' => 'master-data'], function () {
            Route::apiResource('account-group', MasterAccountGroupController::class);
            Route::apiResource('account', MasterAccountController::class);
            Route::apiResource('cash', MasterCashController::class);
            Route::apiResource('customer', MasterCustomerController::class);
            Route::apiResource('supplier', MasterSupplierController::class);
            Route::apiResource('brand', MasterBrandController::class);
            Route::apiResource('unit-type', MasterUnitTypeController::class);
            Route::apiResource('sparepart', MasterSparepartController::class);
        });
    },
);
