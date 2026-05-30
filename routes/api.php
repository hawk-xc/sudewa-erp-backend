<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Dashboard\BillingStatController;
use App\Http\Controllers\Finance\DailyCashFlowController;
use App\Http\Controllers\Finance\FinanceAssetController;
use App\Http\Controllers\Finance\FinanceBillingController;
use App\Http\Controllers\Finance\FinanceRefundController;
use App\Http\Controllers\Finance\PpnDataController;
use App\Http\Controllers\Finance\PurchaseRefundController;
use App\Http\Controllers\Finance\UnitTransactionAdjustmentController;
use App\Http\Controllers\Finance\WithHoldingTaxController;
use App\Http\Controllers\Global\GlobalCompanyController;
use App\Http\Controllers\Global\GlobalModuleController;
use App\Http\Controllers\MasterData\MasterAccountController;
use App\Http\Controllers\MasterData\MasterAccountGroupController;
use App\Http\Controllers\MasterData\MasterAssetController;
use App\Http\Controllers\MasterData\MasterBrandController;
use App\Http\Controllers\MasterData\MasterCashController;
use App\Http\Controllers\MasterData\MasterCustomerController;
use App\Http\Controllers\MasterData\MasterDealerController;
use App\Http\Controllers\MasterData\MasterDriverController;
use App\Http\Controllers\MasterData\MasterMaterialController;
use App\Http\Controllers\MasterData\MasterOwnershipTransferFeeController;
use App\Http\Controllers\MasterData\MasterRegionController;
use App\Http\Controllers\MasterData\MasterSparepartCategoryController;
use App\Http\Controllers\MasterData\MasterSparepartController;
use App\Http\Controllers\MasterData\MasterSupplierController;
use App\Http\Controllers\MasterData\MasterTarifController;
use App\Http\Controllers\MasterData\MasterUnitTypeController;
use App\Http\Controllers\MasterData\MasterUnitTypePriceArchiveController;
use App\Http\Controllers\MasterData\MasterVehicleEquipmentController;
use App\Http\Controllers\MasterData\MasterVendorController;
use App\Http\Controllers\MasterData\VehicleFleetController;
use App\Http\Controllers\Permission\PermissionController;
use App\Http\Controllers\Report\LiabilityController;
use App\Http\Controllers\Report\TransactionReportController;
use App\Http\Controllers\Report\UnitTypeDetailReportController;
use App\Http\Controllers\Role\RoleController;
use App\Http\Controllers\Transaction\BBNBillBillingController;
use App\Http\Controllers\Transaction\BBNBillBillingItemController;
use App\Http\Controllers\Transaction\BBNBillController;
use App\Http\Controllers\Transaction\DOExpeditionController;
use App\Http\Controllers\Transaction\DOInvoiceController;
use App\Http\Controllers\Transaction\DOOrderListController;
use App\Http\Controllers\Transaction\DOOrderListTarifController;
use App\Http\Controllers\Transaction\DOOrderListTarifItemController;
use App\Http\Controllers\Transaction\GoodsTransactionBillingController;
use App\Http\Controllers\Transaction\GoodsTransactionBillingPaymentController;
use App\Http\Controllers\Transaction\GoodsTransactionController;
use App\Http\Controllers\Transaction\GoodsTransactionDetailController;
use App\Http\Controllers\Transaction\TransactionFlowController;
use App\Http\Controllers\Transaction\UnitTransactionBillingController;
use App\Http\Controllers\Transaction\UnitTransactionBillingHistoryController;
use App\Http\Controllers\Transaction\UnitTransactionController;
use App\Http\Controllers\Transaction\UnitTransactionItemController;
use App\Http\Controllers\Transaction\UnitTransactionItemDetailController;
use App\Http\Controllers\Transaction\UnitTransactionItemSalesController;
use App\Http\Controllers\Transaction\UnitTransactionRefundController;
use App\Http\Controllers\Transaction\UnitTransactionRefundPaymentController;
use App\Http\Controllers\Transaction\VehicleDataController;
use App\Http\Controllers\Transaction\VehicleDocumentController;
use App\Http\Controllers\Transaction\VehicleRegistrationController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\Warehouse\GoodsTransactionStockController;
use App\Http\Controllers\Warehouse\VehicleEquipmentTransactionController;
use App\Http\Controllers\Warehouse\WarehouseActivityController;
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
            Route::get('check-token', [AuthController::class, 'checkToken']);
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
            Route::get('{id}/show-module', [UserController::class, 'showModule'])->name('show-module');
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
            // Import
            Route::post('account/{id}/import', [MasterAccountController::class, 'import']);
            Route::post('customer/{id}/import', [MasterCustomerController::class, 'import']);
            Route::post('supplier/{id}/import', [MasterSupplierController::class, 'import']);
            Route::post('dealer/{id}/import', [MasterDealerController::class, 'import']);
            Route::post('driver/{id}/import', [MasterDriverController::class, 'import']);
            Route::post('vendor/{id}/import', [MasterVendorController::class, 'import']);
            Route::post('asset/{id}/import', [MasterAssetController::class, 'import']);
            Route::post('region/import', [MasterRegionController::class, 'import']);
            Route::post('unit-type/import', [MasterUnitTypeController::class, 'import']);
            Route::post('cash/import', [MasterCashController::class, 'import']);
            Route::post('unit-type-price-archive/import', [MasterUnitTypePriceArchiveController::class, 'import']);
            Route::post('sparepart/import', [MasterSparepartController::class, 'import']);
            Route::post('material/import', [MasterMaterialController::class, 'import']);
            Route::post('tarif/import', [MasterTarifController::class, 'import']);
            Route::post('vehicle-fleet/import', [VehicleFleetController::class, 'import']);
            Route::post('vehicle-equipment/import', [MasterVehicleEquipmentController::class, 'import']);

            // Export
            Route::get('customer/export', [MasterCustomerController::class, 'export']);
            Route::get('cash/export', [MasterCashController::class, 'export']);
            Route::get('supplier/export', [MasterSupplierController::class, 'export']);
            Route::get('dealer/export', [MasterDealerController::class, 'export']);
            Route::get('driver/export', [MasterDriverController::class, 'export']);
            Route::get('vendor/export', [MasterVendorController::class, 'export']);
            Route::get('asset/export', [MasterAssetController::class, 'export']);
            Route::get('region/export', [MasterRegionController::class, 'export']);
            Route::get('unit-type/export', [MasterUnitTypeController::class, 'export']);
            Route::get('sparepart/export', [MasterSparepartController::class, 'export']);
            Route::get('tarif/export', [MasterTarifController::class, 'export']);
            Route::get('vehicle-equipment/export', [MasterVehicleEquipmentController::class, 'export']);
            Route::get('vehicle-fleet/export', [VehicleFleetController::class, 'export']);
            // Route::get('material/export', [MasterMaterialController::class, 'export']);
    
            // Master Data
            Route::apiResource('account-group', MasterAccountGroupController::class);
            Route::put('account/bulk-update', [MasterAccountController::class, 'bulkUpdate']);
            Route::apiResource('account', MasterAccountController::class);
            Route::apiResource('cash', MasterCashController::class);
            Route::apiResource('customer', MasterCustomerController::class);
            Route::apiResource('asset', MasterAssetController::class);
            Route::apiResource('supplier', MasterSupplierController::class);
            Route::apiResource('dealer', MasterDealerController::class);
            Route::apiResource('driver', MasterDriverController::class);
            Route::apiResource('brand', MasterBrandController::class);
            Route::apiResource('unit-type', MasterUnitTypeController::class);
            Route::apiResource('unit-type-price-archive', MasterUnitTypePriceArchiveController::class);
            Route::apiResource('sparepart-category', MasterSparepartCategoryController::class);
            Route::apiResource('sparepart', MasterSparepartController::class);
            Route::apiResource('region', MasterRegionController::class);
            Route::apiResource('bbn', MasterOwnershipTransferFeeController::class);
            Route::apiResource('material', MasterMaterialController::class);
            Route::apiResource('vendor', MasterVendorController::class);
            Route::apiResource('tarif', MasterTarifController::class);
            Route::apiResource('vehicle-equipment', MasterVehicleEquipmentController::class);
            Route::apiResource('vehicle-fleet', VehicleFleetController::class);
        });

        // Warehouse API
        Route::group(['prefix' => 'warehouse', 'as' => 'warehouse.'], function () {
            Route::get('warehouse-stock/{id}', [WarehouseController::class, 'getStock']);
            Route::get('warehouse-get-stock/{id}', [WarehouseController::class, 'getWarehouseStock']);
            Route::get('warehouse-get-unit-transaction-item-details/{id}', [WarehouseController::class, 'getWarehouseUnitTransactionsDetails']);
            Route::get('warehouse-unit-transaction-data/{id}', [WarehouseController::class, 'getUnitTransaction']);
            Route::get('warehouse-unit-transaction-item-data/{id}', [WarehouseController::class, 'getUnitTransactionItem']);
            Route::get('warehouse-unit-transaction-outstanding/{id}', [WarehouseController::class, 'getUnitTransactionOutstanding']);

            // Receipt Stock
            Route::put('warehouse-activity/{id}/receipt-stock', [WarehouseActivityController::class, 'receiptStock']);
            Route::put('warehouse-activity/{id}/dispatch-stock', [WarehouseActivityController::class, 'dispatchStock']);
            Route::post('warehouse-activity/refund-stock', [WarehouseActivityController::class, 'refundStock']);
            Route::post('warehouse-activity/return-stock', [WarehouseActivityController::class, 'returnStock']);

            // Receipt Material Stock
            Route::put('warehouse-activity/{id}/receipt-material-stock', [WarehouseActivityController::class, 'receiptMaterialStock']);
            Route::put('warehouse-activity/{id}/dispatch-material-stock', [WarehouseActivityController::class, 'dispatchMaterialStock']);
            Route::post('warehouse-activity/refund-material-stock', [WarehouseActivityController::class, 'refundMaterialStock']);
            Route::post('warehouse-activity/return-material-stock', [WarehouseActivityController::class, 'returnMaterialStock']);

            // Warehouse Stock Flow
            Route::apiResource('warehouse-activity', WarehouseActivityController::class);
            Route::apiResource('warehouse-data', WarehouseController::class);

            // Vehicle Stock status
            Route::get('good-transaction-maintenance', [GoodsTransactionController::class, 'maintenance']);

            // Goods Stock
            Route::get('goods-transaction-stock', [GoodsTransactionStockController::class, 'index']);
            Route::get('goods-transaction-stock-material', [GoodsTransactionStockController::class, 'indexMaterial']);
        });

        // Transaction API
        Route::group(['prefix' => 'transaction', 'as' => 'transaction.'], function () {
            Route::apiResource('transaction-flow', TransactionFlowController::class);

            // Unit Transaction API
            Route::group(['prefix' => 'unit-transaction', 'as' => 'unit-transaction.'], function () {
                Route::post('unit-transaction-item-detail/{unit_transaction_item_id}/import', [UnitTransactionItemDetailController::class, 'import']);

                // unit transaction item details counter validator
                Route::get('unit-transaction-billing/check-right-amount', [UnitTransactionBillingController::class, 'checkRightAmount']);

                // Additional Route
                Route::put('unit-transaction/{id}/update-state', [UnitTransactionController::class, 'updateState'])->name('update-state');
                Route::put('unit-transaction/{id}/refund', [UnitTransactionController::class, 'refund'])->name('refund');
                Route::put('unit-transaction/{id}/return', [UnitTransactionController::class, 'return'])->name('return');
                Route::get('unit-transaction-item/get-formula', [UnitTransactionItemController::class, 'getFormula'])->name('get-formula');
                Route::delete('unit-transaction-item/transcation-item-detail-bulk-delete/{id}', [UnitTransactionItemController::class, 'bulkDelete'])->name('bulk-delete');

                // upload unit transaction invoice
                Route::post('unit-transaction/{id}/upload-invoice', [UnitTransactionController::class, 'uploadInvoiceFile'])->name('upload-invoice-file');

                Route::post('unit-transaction/{id}/transaction-adjustment', [UnitTransactionController::class, 'storeTransactionAdjustment']);
                Route::apiResource('unit-transaction', UnitTransactionController::class);
                Route::apiResource('unit-transaction-item', UnitTransactionItemController::class);
                Route::apiResource('unit-transaction-item-sales', UnitTransactionItemSalesController::class);
                Route::apiResource('unit-transaction-item-detail', UnitTransactionItemDetailController::class);
                Route::apiResource('unit-transaction-billing', UnitTransactionBillingController::class);
                Route::apiResource('unit-transaction-billing-history', UnitTransactionBillingHistoryController::class);
                Route::apiResource('unit-transaction-refund', UnitTransactionRefundController::class);
                Route::apiResource('unit-transaction-refund-payment', UnitTransactionRefundPaymentController::class);
            });

            // Vehicle Data
            Route::post('vehicle-data/assign-registration', [VehicleDataController::class, 'assignRegistration']);
            Route::apiResource('vehicle-data', VehicleDataController::class);
            Route::apiResource('vehicle-document', VehicleDocumentController::class);
            Route::apiResource('vehicle-registration', VehicleRegistrationController::class);

            // Goods Transaction API
            Route::post('goods-transaction/{id}/upload-invoice', [GoodsTransactionController::class, 'uploadInvoice'])->name('goods-transaction.upload-invoice');
            Route::apiResource('goods-transaction', GoodsTransactionController::class);
            Route::apiResource('goods-transaction-detail', GoodsTransactionDetailController::class);
            Route::apiResource('goods-transaction-billing', GoodsTransactionBillingController::class);
            Route::apiResource('goods-transaction-billing-payment', GoodsTransactionBillingPaymentController::class, [
                'parameters' => [
                    'goods-transaction-billing-payment' => 'payment'
                ]
            ]);

            // DO Transaction API    
            Route::group(['prefix' => 'do-invoice', 'as' => 'do-invoice.'], function() {
                Route::post('process-invoice/{id}', [DOInvoiceController::class, 'processInvoice']);
                Route::post('process-expedition/{id}', [DOInvoiceController::class, 'processExpedition']);
            });

            Route::get('process-invoice', [DOExpeditionController::class, 'export']);
            Route::get('do-expedition/export', [DOExpeditionController::class, 'export']);
            Route::get('check-do-expedition-code', [DOExpeditionController::class, 'checkDOCode']);
            Route::apiResource('do-order-list', DOOrderListController::class);
            Route::apiResource('do-order-list-tarif', DOOrderListTarifController::class);
            Route::apiResource('do-order-list-tarif-item', DOOrderListTarifItemController::class);
            Route::apiResource('do-expedition', DOExpeditionController::class);
            Route::apiResource('do-invoice', DOInvoiceController::class);

            // BBN Bill
            Route::put('bbn-bill-detail/{vehicle_data_id}', [VehicleDataController::class, 'updateVehicleRegistrationData']);
            Route::apiResource('bbn-bill', BBNBillController::class);
            Route::apiResource('bbn-bill-billing', BBNBillBillingController::class);
            Route::apiResource('bbn-bill-billing-item', BBNBillBillingItemController::class);
        });

        // Finance
        Route::group(['prefix' => 'finance', 'as' => 'finance.'], function () {
            Route::post('finance-asset/import', [FinanceAssetController::class, 'import']);
            Route::get('finance-asset/export', [FinanceAssetController::class, 'export']);
            Route::apiResource('finance-asset', FinanceAssetController::class)->except(['store', 'destroy']);
            // Route::get('transaction-refund', [PurchaseRefundController::class, 'index']);
            Route::post('finance-billing-item', [FinanceBillingController::class, 'getBillingItem']);
            Route::post('finance-billing-item/{unit_transaction_billing_id}', [FinanceBillingController::class, 'addItem']);
            Route::put('finance-billing-item/{id}', [FinanceBillingController::class, 'updateItem']);
            Route::delete('finance-billing-item/{id}', [FinanceBillingController::class, 'destroyItem']);

            Route::apiResource('ppn', PpnDataController::class);
            Route::apiResource('cash-flow', DailyCashFlowController::class);
            Route::apiResource('adjustment', UnitTransactionAdjustmentController::class);
            Route::apiResource('finance-billing', FinanceBillingController::class);

            // Finance Refund
            Route::get('finance-refund', [FinanceRefundController::class, 'index']);
            Route::get('finance-refund/{id}', [FinanceRefundController::class, 'show']);
            Route::put('finance-refund/{id}', [FinanceRefundController::class, 'update']);
            // hold
            Route::apiResource('withholding-tax', WithHoldingTaxController::class);
        });

        // Report Data
        Route::group(['prefix' => 'report', 'as' => 'report.'], function () {
            Route::get('transaction-purchase-report', [TransactionReportController::class, 'purchaseTransactionReport']);
            Route::get('transaction-sales-report', [TransactionReportController::class, 'salesTransactionReport']);
            Route::apiResource('liability-report', LiabilityController::class)->only(['index', 'show']);
            Route::get('unit-type-detail-report', [UnitTypeDetailReportController::class, 'index']);
            Route::get('unit-type-detail-stock', [UnitTransactionController::class, 'getStock']);
            Route::get('unit-type-detail-stock/export', [UnitTransactionController::class, 'exportStock']);
        });

	// Stats
	Route::group(['prefix'=> 'stats','as'=> 'stats.'], function () {
    		Route::get('billing-stats', [BillingStatController::class, 'billingStat']);
    		Route::get('customer-stats', [BillingStatController::class, 'customerOverview']);
    		Route::get('unit-type-stats', [BillingStatController::class, 'unitTypeOverview']);
	});
    },
);
