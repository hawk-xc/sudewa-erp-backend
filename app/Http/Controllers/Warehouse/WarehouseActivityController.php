<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\UnitTransactionItemDetail;
use App\Models\GoodsTransactionDetail;
use App\Models\WarehouseActivity;
use App\Models\WarehouseMovement;
use App\Repositories\AuthRepository;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WarehouseActivityController extends Controller
{
    use ResponseTrait;

    protected AuthRepository $authRepository;

    protected array $activityTable = [
        'id',
        'uuid',
        'person_id',
        'cash_id',
        'warehouse_id',
        'activity_number',
        'activity_type',
        'activity_date',
        'description',
        'created_at',
    ];

    public function __construct(AuthRepository $ar)
    {
        $this->middleware(['permission:warehouse:list'])->only(['index', 'show']);
        $this->middleware(['permission:warehouse:create'])->only('store');
        $this->middleware(['permission:warehouse:edit'])->only('update');
        $this->middleware(['permission:warehouse:delete'])->only('destroy');

        $this->authRepository = $ar;
    }

    /**
     * Base query builder
     */
    private function baseQuery()
    {
        return WarehouseActivity::with([
            'warehouse:id,uuid,name',
            'person:id,uuid,name',
            'cash:id,uuid,code,description,type',
        ])->select($this->activityTable);
    }

    /**
     * Apply filters
     */
    private function applyFilters($query, Request $request)
    {
        return $query
            ->when($request->warehouse_id, fn($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->when($request->person_id, fn($q) => $q->where('person_id', $request->person_id))
            ->when($request->activity_type, fn($q) => $q->where('activity_type', $request->activity_type))
            ->when(
                $request->date_from && $request->date_to,
                fn($q) => $q->whereBetween('activity_date', [$request->date_from, $request->date_to])
            )
            ->when(
                $request->search,
                fn($q) => $q->where('activity_number', 'like', "%{$request->search}%")
            );
    }

    public function index(Request $request)
    {
        try {
            $query = $this->applyFilters($this->baseQuery(), $request);

            $data = $query
                ->latest()
                ->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'Warehouse activities retrieved successfully');
        } catch (Exception $e) {
            Log::error('WarehouseActivity index error', [
                'message' => $e->getMessage(),
            ]);

            return $this->responseError(null, 'Internal Server Error', 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'person_id' => 'required|exists:persons,id',
            'cash_id' => 'nullable|integer|exists:cashes,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'activity_type' => 'required|in:receipt,issue',
            'activity_date' => 'required|date',
            'description' => 'nullable|string',
        ]);

        try {
            $data = DB::transaction(fn() => WarehouseActivity::create($validated));

            return $this->responseSuccess($data, 'Warehouse activity created successfully', 201);
        } catch (Exception $err) {
            Log::error('WarehouseActivity store error', [
                'message' => $err->getMessage(),
            ]);

            return $this->responseError($err->getMessage(), 'Failed to create warehouse activity', 500);
        }
    }

    public function show(string $id)
    {
        try {
            $data = $this->baseQuery()->with([
                'warehouseMovements.unitTransactionItemDetail.unitTransactionItem:id,uuid,unit_transaction_id,unit_type_id',
                'warehouseMovements.unitTransactionItemDetail.unitTransactionItem.unitType:id,name'
            ])->findOrFail($id);

            $data->total_unit_transaction_item_details = $data->warehouseMovements->count();

            return $this->responseSuccess($data, 'Warehouse activity retrieved successfully');
        } catch (Exception $e) {
            Log::error('WarehouseActivity show error', [
                'message' => $e->getMessage(),
            ]);

            return $this->responseError(null, 'Warehouse activity not found', 404);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $warehouseActivity = WarehouseActivity::findOrFail($id);

            $validated = $request->validate([
                'person_id' => 'sometimes|exists:persons,id',
                'cash_id' => 'sometimes|nullable|integer|exists:cashes,id',
                'warehouse_id' => 'sometimes|exists:warehouses,id',
                'activity_type' => 'sometimes|in:receipt,issue',
                'activity_date' => 'sometimes|date',
                'description' => 'sometimes|string',
            ]);

            DB::transaction(
                fn() => $warehouseActivity->update($validated)
            );

            return $this->responseSuccess(
                $warehouseActivity->fresh(),
                'Warehouse activity updated successfully'
            );
        } catch (Exception $e) {
            Log::error('WarehouseActivity update error', [
                'message' => $e->getMessage(),
            ]);

            return $this->responseError(null, 'Failed to update warehouse activity', 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $warehouseActivity = WarehouseActivity::findOrFail($id);

            DB::transaction(function () use ($warehouseActivity) {
                $movements = $warehouseActivity->warehouseMovements()
                    ->with('unitTransactionItemDetail.unitTransactionItem.unitTransaction')
                    ->get();

                foreach ($movements as $movement) {
                    $detail = $movement->unitTransactionItemDetail;
                    if ($detail) {
                        if ($movement->status === 'in') {
                            $detail->update([
                                'in_stock' => false,
                                'is_forecast' => true,
                                'status' => 'normal',
                            ]);
                        } elseif ($movement->status === 'refund') {
                            $tx = $detail->unitTransactionItem?->unitTransaction;
                            if ($tx) {
                                if ($tx->type === 'purchase') {
                                    $detail->update([
                                        'in_stock' => true,
                                        'is_forecast' => false,
                                        'status' => 'normal',
                                    ]);
                                } elseif ($tx->type === 'sales') {
                                    $detail->update([
                                        'in_stock' => false,
                                        'is_forecast' => false,
                                        'status' => 'normal',
                                    ]);
                                }
                                $tx->recalculateBillingTotals();
                            }
                        } elseif ($movement->status === 'out') {
                            $detail->update([
                                'in_stock' => true,
                                'is_forecast' => false,
                                'status' => 'normal',
                            ]);
                        }
                    }
                }

                $warehouseActivity->delete();
            });

            return $this->responseSuccess($warehouseActivity, 'Warehouse activity deleted successfully');
        } catch (Exception $e) {
            Log::error('WarehouseActivity destroy error', [
                'message' => $e->getMessage(),
            ]);

            return $this->responseError(null, 'Failed to delete warehouse activity', 500);
        }
    }

    public function receiptStock(Request $request, string $activityId)
    {
        if (is_string($request->unit_transaction_details)) {
            $request->merge([
                'unit_transaction_details' => json_decode($request->unit_transaction_details, true),
            ]);
        }

        $validated = $request->validate([
            'unit_transaction_details' => 'required|array|min:1',
            'unit_transaction_details.*' => 'integer|exists:unit_transaction_item_details,id',
            'cash_id' => 'nullable|integer|exists:cashes,id',
        ]);

        try {
            $activity = WarehouseActivity::findOrFail($activityId);

            if ($activity->activity_type !== 'receipt') {
                return $this->responseError(null, 'Invalid activity type for receipt', 422);
            }

            $person = Person::findOrFail($activity->person_id);

            $allowedDetailIds = $person->unitTransactions()
                ->with('unitTransactionItems.unitTransactionItemDetails:id,unit_transaction_item_id')
                ->get()
                ->flatMap(fn($trx) => $trx->unitTransactionItems)
                ->flatMap(fn($item) => $item->unitTransactionItemDetails)
                ->pluck('id')
                ->toArray();

            $invalidIds = array_diff($validated['unit_transaction_details'], $allowedDetailIds);

            if (! empty($invalidIds)) {
                return $this->responseError(
                    $invalidIds,
                    'Some unit transaction details do not belong to this person',
                    422
                );
            }

            $unitTransactionItemDetailList = [];

            DB::transaction(function () use ($validated, $activity, &$unitTransactionItemDetailList) {
                if (isset($validated['cash_id'])) {
                    $activity->update([
                        'cash_id' => $validated['cash_id'],
                    ]);
                }

                $details = UnitTransactionItemDetail::with([
                    'unitTransactionItem.unitTransaction.unitTransactionBilling',
                ])->whereIn('id', $validated['unit_transaction_details'])->get();

                foreach ($details as $detail) {

                    $transaction = $detail->unitTransactionItem->unitTransaction;

                    $stockState = $transaction->stock_state;
                    $billing = $transaction->unitTransactionBilling;

                    if (! in_array($stockState, ['inbound_incoming_goods', 'inbound_receipt'])) {
                        throw new Exception(
                            "Invalid stock state '{$stockState}' for detail ID {$detail->id}"
                        );
                    }

                    if ($detail->in_stock) {
                        throw new Exception(
                            "Detail ID {$detail->id} already in stock"
                        );
                    }

                    $detail->update(['in_stock' => true, 'is_forecast' => false]);
                    $detail->receiptStock((int) $activity->id);

                    $unitTransactionItemDetailList[] = $detail;
                }
            });

            $responseData = [
                'activity' => $activity->fresh(),
                'unit_transaction_item_details' => $unitTransactionItemDetailList,
            ];

            return $this->responseSuccess(
                (object) $responseData,
                'Receipt stock processed successfully'
            );
        } catch (Exception $e) {
            Log::error('WarehouseActivity receiptStock error', [
                'message' => $e->getMessage(),
            ]);

            return $this->responseError(null, $e->getMessage(), 500);
        }
    }

    public function dispatchStock(Request $request, string $activityId)
    {
        if (is_string($request->unit_transaction_details)) {
            $request->merge([
                'unit_transaction_details' => json_decode($request->unit_transaction_details, true),
            ]);
        }

        $validated = $request->validate([
            'unit_transaction_details' => 'required|array|min:1',
            'unit_transaction_details.*' => 'integer|exists:unit_transaction_item_details,id',
            'cash_id' => 'nullable|integer|exists:cashes,id',
        ]);

        try {
            $activity = WarehouseActivity::findOrFail($activityId);

            if ($activity->activity_type !== 'issue') {
                return $this->responseError(null, 'Invalid activity type for dispatch', 422);
            }

            $unitTransactionItemDetailList = [];

            DB::transaction(function () use ($validated, $activity, &$unitTransactionItemDetailList) {
                if (isset($validated['cash_id'])) {
                    $activity->update([
                        'cash_id' => $validated['cash_id'],
                    ]);
                }

                $details = UnitTransactionItemDetail::with([
                    'unitTransactionItem.unitTransaction.unitTransactionBilling',
                ])->whereIn('id', $validated['unit_transaction_details'])->get();

                $availableStockCount = $details->where('in_stock', true)->count();

                if (count($validated['unit_transaction_details']) > $availableStockCount) {
                    throw new Exception(
                        "Dispatch quantity exceeds available stock ({$availableStockCount})"
                    );
                }

                foreach ($details as $detail) {
                    $transaction = $detail->unitTransactionItem->unitTransaction;

                    $billing = $transaction->unitTransactionBilling;

                    $detail->update(['in_stock' => false, 'is_forecast' => false]);

                    $detail->dispatchStock();

                    $unitTransactionItemDetailList[] = $detail;
                }
            });

            $responseData = [
                'activity' => $activity->fresh(),
                'unit_transaction_item_details' => $unitTransactionItemDetailList,
            ];

            return $this->responseSuccess(
                (object) $responseData,
                'Dispatch stock processed successfully'
            );
        } catch (Exception $e) {
            Log::error('WarehouseActivity dispatchStock error', [
                'message' => $e->getMessage(),
            ]);

            return $this->responseError(null, $e->getMessage(), 500);
        }
    }

    public function refundStock(Request $request)
    {
        if (is_string($request->unit_transaction_details)) {
            $request->merge([
                'unit_transaction_details' => json_decode($request->unit_transaction_details, true),
            ]);
        }

        $validated = $request->validate([
            'unit_transaction_details' => 'required|array|min:1',
            'unit_transaction_details.*' => 'integer|exists:unit_transaction_item_details,id',
            'cash_id' => 'required|integer|exists:cashes,id',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'description' => 'nullable|string',
        ]);

        try {
            $unitTransactionItemDetailList = [];
            $activity = null;

            DB::transaction(function () use ($validated, &$unitTransactionItemDetailList, &$activity) {
                $details = UnitTransactionItemDetail::with(['unitTransactionItem.unitTransaction'])
                    ->whereIn('id', $validated['unit_transaction_details'])
                    ->get();

                if ($details->isEmpty()) {
                    throw new Exception("No valid unit transaction details found.");
                }

                $firstDetail = $details->first();
                $personId = $firstDetail->unitTransactionItem->unitTransaction->person_id;

                // Create the WarehouseActivity automatically
                $activity = WarehouseActivity::create([
                    'person_id' => $personId,
                    'cash_id' => $validated['cash_id'],
                    'warehouse_id' => $validated['warehouse_id'],
                    'activity_type' => 'receipt', // sales refund is receipt of goods
                    'activity_date' => now(),
                    'description' => $validated['description'] ?? 'Automatic Sales Refund',
                ]);

                foreach ($details as $detail) {
                    if ($detail->unitTransactionItem->unitTransaction->type !== 'sales') {
                        throw new Exception("Detail ID {$detail->id} is not a sales transaction");
                    }
                    $detail->refundStock();

                    // Create movement of status refund associated with this warehouse activity
                    WarehouseMovement::create([
                        'warehouse_activity_id' => $activity->id,
                        'unit_transaction_id' => $detail->unitTransactionItem->unitTransaction->id,
                        'unit_transaction_item_detail_id' => $detail->id,
                        'status' => 'refund',
                    ]);

                    $unitTransactionItemDetailList[] = $detail;
                }
            });

            $responseData = [
                'activity' => $activity ? $activity->fresh() : null,
                'unit_transaction_item_details' => $unitTransactionItemDetailList,
            ];

            return $this->responseSuccess(
                (object) $responseData,
                'Refund stock processed successfully'
            );
        } catch (Exception $e) {
            Log::error('WarehouseActivity refundStock error', [
                'message' => $e->getMessage(),
            ]);

            return $this->responseError(null, $e->getMessage(), 500);
        }
    }

    public function returnStock(Request $request)
    {
        if (is_string($request->unit_transaction_details)) {
            $request->merge([
                'unit_transaction_details' => json_decode($request->unit_transaction_details, true),
            ]);
        }

        $validated = $request->validate([
            'unit_transaction_details' => 'required|array|min:1',
            'unit_transaction_details.*' => 'integer|exists:unit_transaction_item_details,id',
            'cash_id' => 'required|integer|exists:cashes,id',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'description' => 'nullable|string',
        ]);

        try {
            $unitTransactionItemDetailList = [];
            $activity = null;

            DB::transaction(function () use ($validated, &$unitTransactionItemDetailList, &$activity) {
                $details = UnitTransactionItemDetail::with(['unitTransactionItem.unitTransaction'])
                    ->whereIn('id', $validated['unit_transaction_details'])
                    ->get();

                if ($details->isEmpty()) {
                    throw new Exception("No valid unit transaction details found.");
                }

                $firstDetail = $details->first();
                $personId = $firstDetail->unitTransactionItem->unitTransaction->person_id;

                // Create the WarehouseActivity automatically
                $activity = WarehouseActivity::create([
                    'person_id' => $personId,
                    'cash_id' => $validated['cash_id'],
                    'warehouse_id' => $validated['warehouse_id'],
                    'activity_type' => 'issue', // purchase return is issue of goods
                    'activity_date' => now(),
                    'description' => $validated['description'] ?? 'Automatic Purchase Return',
                ]);

                foreach ($details as $detail) {
                    if ($detail->unitTransactionItem->unitTransaction->type !== 'purchase') {
                        throw new Exception("Detail ID {$detail->id} is not a purchase transaction");
                    }
                    $detail->returnStock();

                    // Create movement of status refund associated with this warehouse activity
                    WarehouseMovement::create([
                        'warehouse_activity_id' => $activity->id,
                        'unit_transaction_id' => $detail->unitTransactionItem->unitTransaction->id,
                        'unit_transaction_item_detail_id' => $detail->id,
                        'status' => 'refund',
                    ]);

                    $unitTransactionItemDetailList[] = $detail;
                }
            });

            $responseData = [
                'activity' => $activity ? $activity->fresh() : null,
                'unit_transaction_item_details' => $unitTransactionItemDetailList,
            ];

            return $this->responseSuccess(
                (object) $responseData,
                'Return stock processed successfully'
            );
        } catch (Exception $e) {
            Log::error('WarehouseActivity returnStock error', [
                'message' => $e->getMessage(),
            ]);

            return $this->responseError(null, $e->getMessage(), 500);
        }
    }
    public function receiptMaterialStock(Request $request, string $activityId)
    {
        if (is_string($request->goods_transaction_details)) {
            $request->merge([
                'goods_transaction_details' => json_decode($request->goods_transaction_details, true),
            ]);
        }

        $validated = $request->validate([
            'goods_transaction_details' => 'required|array|min:1',
            'goods_transaction_details.*' => 'integer|exists:goods_transaction_details,id',
        ]);

        try {
            $activity = WarehouseActivity::findOrFail($activityId);

            if ($activity->activity_type !== 'receipt') {
                return $this->responseError(null, 'Invalid activity type for receipt', 422);
            }

            $person = Person::findOrFail($activity->person_id);

            $allowedDetailIds = $person->goodsTransactions()
                ->with('goodsTransactionDetails:id,goods_transaction_id')
                ->get()
                ->flatMap(fn($trx) => $trx->goodsTransactionDetails)
                ->pluck('id')
                ->toArray();

            $invalidIds = array_diff($validated['goods_transaction_details'], $allowedDetailIds);

            if (! empty($invalidIds)) {
                return $this->responseError(
                    $invalidIds,
                    'Some goods transaction details do not belong to this person',
                    422
                );
            }

            $goodsTransactionDetailList = [];

            DB::transaction(function () use ($validated, $activity, &$goodsTransactionDetailList) {
                $details = GoodsTransactionDetail::with([
                    'goodsTransaction.goodsTransactionBillings',
                ])->whereIn('id', $validated['goods_transaction_details'])->get();

                foreach ($details as $detail) {

                    $transaction = $detail->goodsTransaction;

                    $stockState = $transaction->stock_state;
                    $billing = $transaction->goodsTransactionBillings->first(); // or handle differently if multiple billings

                    if (! in_array($stockState, ['inbound_incoming_goods', 'inbound_receipt'])) {
                        throw new Exception(
                            "Invalid stock state '{$stockState}' for detail ID {$detail->id}"
                        );
                    }

                    if (! $billing) {
                        throw new Exception(
                            "Transaction for detail ID {$detail->id} has no billing yet"
                        );
                    }

                    // Check if it's paid - wait, is_paid is on GoodsTransaction or billing? 
                    // Actually $transaction->is_paid exists. Or we check the transaction directly.
                    if (! $transaction->is_paid) {
                        throw new Exception(
                            "Transaction for detail ID {$detail->id} has no paid billing yet"
                        );
                    }

                    if ($detail->in_stock) {
                        throw new Exception(
                            "Detail ID {$detail->id} already in stock"
                        );
                    }

                    $detail->update(['in_stock' => true, 'is_forecast' => false]);
                    $detail->receiptStock((int) $activity->warehouse_id);

                    $goodsTransactionDetailList[] = $detail;
                }
            });

            $responseData = [
                'activity' => $activity,
                'goods_transaction_details' => $goodsTransactionDetailList,
            ];

            return $this->responseSuccess(
                (object) $responseData,
                'Receipt goods stock processed successfully'
            );
        } catch (Exception $e) {
            Log::error('WarehouseActivity receiptMaterialStock error', [
                'message' => $e->getMessage(),
            ]);

            return $this->responseError(null, $e->getMessage(), 500);
        }
    }

    public function dispatchMaterialStock(Request $request, string $activityId)
    {
        if (is_string($request->goods_transaction_details)) {
            $request->merge([
                'goods_transaction_details' => json_decode($request->goods_transaction_details, true),
            ]);
        }

        $validated = $request->validate([
            'goods_transaction_details' => 'required|array|min:1',
            'goods_transaction_details.*' => 'integer|exists:goods_transaction_details,id',
        ]);

        try {
            $activity = WarehouseActivity::findOrFail($activityId);

            if ($activity->activity_type !== 'issue') {
                return $this->responseError(null, 'Invalid activity type for dispatch', 422);
            }

            $goodsTransactionDetailList = [];

            DB::transaction(function () use ($validated, &$goodsTransactionDetailList) {

                $details = GoodsTransactionDetail::with([
                    'goodsTransaction',
                ])->whereIn('id', $validated['goods_transaction_details'])->get();

                foreach ($details as $detail) {
                    $transaction = $detail->goodsTransaction;

                    if (! $transaction->is_paid) {
                        throw new Exception(
                            "Transaction for detail ID {$detail->id} has no paid billing yet"
                        );
                    }

                    if (! $detail->in_stock) {
                        throw new Exception(
                            "Detail ID {$detail->id} is not available in stock"
                        );
                    }

                    $detail->update(['in_stock' => false]);

                    $detail->dispatchStock();

                    $goodsTransactionDetailList[] = $detail;
                }
            });

            $responseData = [
                'activity' => $activity,
                'goods_transaction_details' => $goodsTransactionDetailList,
            ];

            return $this->responseSuccess(
                (object) $responseData,
                'Dispatch goods stock processed successfully'
            );
        } catch (Exception $e) {
            Log::error('WarehouseActivity dispatchMaterialStock error', [
                'message' => $e->getMessage(),
            ]);

            return $this->responseError(null, $e->getMessage(), 500);
        }
    }

    public function refundMaterialStock(Request $request)
    {
        if (is_string($request->goods_transaction_details)) {
            $request->merge([
                'goods_transaction_details' => json_decode($request->goods_transaction_details, true),
            ]);
        }

        $validated = $request->validate([
            'goods_transaction_details' => 'required|array|min:1',
            'goods_transaction_details.*' => 'integer|exists:goods_transaction_details,id',
        ]);

        try {
            $goodsTransactionDetailList = [];

            DB::transaction(function () use ($validated, &$goodsTransactionDetailList) {
                $details = GoodsTransactionDetail::with(['goodsTransaction'])
                    ->whereIn('id', $validated['goods_transaction_details'])
                    ->get();

                foreach ($details as $detail) {
                    if ($detail->goodsTransaction->type !== 'sales') {
                        throw new Exception("Detail ID {$detail->id} is not a sales transaction");
                    }
                    $detail->refundStock();
                    $goodsTransactionDetailList[] = $detail;
                }
            });

            return $this->responseSuccess(
                $goodsTransactionDetailList,
                'Refund goods stock processed successfully'
            );
        } catch (Exception $e) {
            Log::error('WarehouseActivity refundMaterialStock error', [
                'message' => $e->getMessage(),
            ]);

            return $this->responseError(null, $e->getMessage(), 500);
        }
    }

    public function returnMaterialStock(Request $request)
    {
        if (is_string($request->goods_transaction_details)) {
            $request->merge([
                'goods_transaction_details' => json_decode($request->goods_transaction_details, true),
            ]);
        }

        $validated = $request->validate([
            'goods_transaction_details' => 'required|array|min:1',
            'goods_transaction_details.*' => 'integer|exists:goods_transaction_details,id',
        ]);

        try {
            $goodsTransactionDetailList = [];

            DB::transaction(function () use ($validated, &$goodsTransactionDetailList) {
                $details = GoodsTransactionDetail::with(['goodsTransaction'])
                    ->whereIn('id', $validated['goods_transaction_details'])
                    ->get();

                foreach ($details as $detail) {
                    if ($detail->goodsTransaction->type !== 'purchase') {
                        throw new Exception("Detail ID {$detail->id} is not a purchase transaction");
                    }
                    $detail->returnStock();
                    $goodsTransactionDetailList[] = $detail;
                }
            });

            return $this->responseSuccess(
                $goodsTransactionDetailList,
                'Return goods stock processed successfully'
            );
        } catch (Exception $e) {
            Log::error('WarehouseActivity returnMaterialStock error', [
                'message' => $e->getMessage(),
            ]);

            return $this->responseError(null, $e->getMessage(), 500);
        }
    }
}
