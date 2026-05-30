<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\GoodsTransaction;
use App\Models\GoodsTransactionDetail;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use App\Models\WarehouseActivity;
use App\Models\WarehouseMovement;
use App\Models\VehicleEquipment;
use Illuminate\Http\JsonResponse;

class GoodsTransactionDetailController extends Controller
{
    use ResponseTrait;

    // projection
    protected array $goodsTransactionDetailTable;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only('update');
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);

        $this->goodsTransactionDetailTable = [
            'id',
            'uuid',
            'goods_transaction_id',
            'material_id',
            'vehicle_equipment_id',
            'qty',
            'type',
            'price',
            'in_stock',
            'is_forecast',
            'description',
            'created_at',
        ];
    }

    /**
     * List all goods transaction details.
     */
    public function index(Request $request)
    {   
        $query = GoodsTransactionDetail::with(['goodsTransaction', 'material', 'vehicleEquipment']);

        $query->select($this->goodsTransactionDetailTable);

        try {
            if ($request->filled('type')) {
                $query->whereHas('goodsTransaction', function ($q) use ($request) {
                    $q->where('type', $request->type);
                });
            }

            if ($request->filled('category')) {
                $query->whereHas('goodsTransaction', function ($q) use ($request) {
                    $q->where('category', $request->category);
                });
            }

            if ($request->filled('company_id')) {
                $query->whereHas('goodsTransaction', function ($q) use ($request) {
                    $q->where('company_id', $request->company_id);
                });
            }

            if ($request->filled('supplier_id')) {
                $query->whereHas('goodsTransaction', function ($q) use ($request) {
                    $q->where('supplier_id', $request->supplier_id);
                });
            }

            if ($request->filled('driver_id')) {
                $query->whereHas('goodsTransaction', function ($q) use ($request) {
                    $q->where('driver_id', $request->driver_id);
                });
            }

            if ($request->filled('vehicle_fleet_id')) {
                $query->whereHas('goodsTransaction', function ($q) use ($request) {
                    $q->where('vehicle_fleet_id', $request->vehicle_fleet_id);
                });
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $caseSensitive = $request->boolean('case_sensitive');

                $query->where(function ($q) use ($search, $caseSensitive) {
                    if ($caseSensitive) {
                        $q->where('description', 'LIKE BINARY', "%$search%")
                            ->orWhereHas('material', function ($mq) use ($search) {
                                $mq->where('name', 'LIKE BINARY', "%$search%")
                                    ->orWhere('code', 'LIKE BINARY', "%$search%");
                            })
                            ->orWhereHas('vehicleEquipment', function ($veq) use ($search) {
                                $veq->where('name', 'LIKE BINARY', "%$search%")
                                    ->orWhere('code', 'LIKE BINARY', "%$search%");
                            })
                            ->orWhereHas('goodsTransaction', function ($tq) use ($search) {
                                $tq->where('code', 'LIKE BINARY', "%$search%");
                            });
                    } else {
                        $q->where('description', 'like', "%$search%")
                            ->orWhereHas('material', function ($mq) use ($search) {
                                $mq->where('name', 'like', "%$search%")
                                    ->orWhere('code', 'like', "%$search%");
                            })
                            ->orWhereHas('vehicleEquipment', function ($veq) use ($search) {
                                $veq->where('name', 'like', "%$search%")
                                    ->orWhere('code', 'like', "%$search%");
                            })
                            ->orWhereHas('goodsTransaction', function ($tq) use ($search) {
                                $tq->where('code', 'like', "%$search%");
                            });
                    }
                });
            }

            foreach ($this->goodsTransactionDetailTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $allowedSort = $this->goodsTransactionDetailTable;

            $sortBy = in_array($request->sort_by, $allowedSort)
                ? $request->sort_by
                : 'id';

            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->per_page ?? 10;

            $data = $query->paginate($perPage);

            return $this->responseSuccess($data, 'Goods Transaction Detail list retrieved successfully', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error('Error While retrieved Goods Transaction Detail data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Goods Transaction Detail list retrieved Failed', 500);
        }
    }

    public function store(Request $request)
    {
        $transactionId = $request->input('goods_transaction_id');
        $transaction = $transactionId ? GoodsTransaction::find($transactionId) : null;
        $isReceipt = $transaction && ($transaction->type == 'receipt' || $transaction->type == 'purchase');

        $validated = $request->validate([
            'goods_transaction_id' => 'required|exists:goods_transactions,id',
            'material_id' => [
                'required_without:vehicle_equipment_id',
                'nullable',
                'exists:materials,id',
                Rule::unique('goods_transaction_details')->where(function ($query) use ($request) {
                    return $query->where('goods_transaction_id', $request->goods_transaction_id)
                        ->whereNotNull('material_id');
                }),
            ],
            'vehicle_equipment_id' => [
                'required_without:material_id',
                'nullable',
                'exists:vehicle_equipments,id',
                Rule::unique('goods_transaction_details')->where(function ($query) use ($request) {
                    return $query->where('goods_transaction_id', $request->goods_transaction_id)
                        ->whereNotNull('vehicle_equipment_id');
                }),
            ],
            'qty' => 'required|integer|min:1',
            'type' => 'nullable|in:pcs,set,box',
            'price' => $isReceipt ? 'required|numeric|min:0' : 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'cash_id' => 'nullable|exists:cashes,id',
            'person_id' => 'nullable|exists:persons,id',
        ], [
            'material_id.unique' => 'This material already exists in this transaction.',
            'vehicle_equipment_id.unique' => 'This vehicle equipment already exists in this transaction.',
        ]);

        try {
            $transaction = GoodsTransaction::findOrFail($validated['goods_transaction_id']);
            $typeState = $transaction->type == 'receipt' ? 'penerimaan' : 'pengeluaran';

            if ($transaction->type == 'issue' || $transaction->type == 'sales') {
                if (!empty($request->material_id)) {
                    $availableStock = $this->getAvailableStock($request->material_id);

                    if ($request->qty > $availableStock) {
                        return $this->responseError(null, 'Material stock qty not enough of capacity!', 422);
                    }
                }

                if (!empty($request->vehicle_equipment_id)) {
                    $availableStock = $this->getAvailableVehicleEquipmentStock($request->vehicle_equipment_id);

                    if ($request->qty > $availableStock) {
                        return $this->responseError(null, 'Vehicle Equipment stock qty not enough of capacity!', 422);
                    }
                }
            }

            if ($transaction->goodsTransactionBillingPayments()->exists()) {
                return $this->responseError(null, 'Cannot add items to a transaction that already has payments!', 422);
            }

            $data = DB::transaction(function () use ($validated, $typeState, $transaction) {
                if (empty($validated['description'])) {
                    $validated['description'] = "Penambahahan " . $typeState . " barang";
                }

                $detail = GoodsTransactionDetail::create($validated);
                $warehouseId = $transaction->company->warehouse->id;

                if ($warehouseId) {
                    $existingMovement = WarehouseMovement::where('goods_transaction_id', $transaction->id)->first();
                    
                    if ($existingMovement && $existingMovement->warehouse_activity_id) {
                        $activity = WarehouseActivity::find($existingMovement->warehouse_activity_id);
                    }
                    
                    if (!isset($activity) || !$activity) {
                        $activity = WarehouseActivity::create([
                            'warehouse_id' => $warehouseId,
                            'activity_type' => $transaction->type,
                            'activity_date' => $transaction->transaction_date ?? now(),
                            'description' => 'Automatic ' . $transaction->type . ' from Goods Transaction',
                        ]);
                    }

                    WarehouseMovement::create([
                        'warehouse_activity_id' => $activity->id,
                        'goods_transaction_id' => $transaction->id,
                        'goods_transaction_detail_id' => $detail->id,
                        'status' => $transaction->type == 'receipt' ? 'in' : 'out',
                    ]);

                    $detail->update([
                        'in_stock' => $transaction->type == 'receipt',
                        'is_forecast' => false
                    ]);
                }

                return $detail;
            });

            return $this->responseSuccess($data, 'Goods Transaction Detail created successfully', 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error('Error while trying create Goods Transaction Detail Data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Goods Transaction Detail creation failed', 500);
        }
    }

    /**
     * Get goods transaction detail.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $data = GoodsTransactionDetail::with(['goodsTransaction', 'material', 'vehicleEquipment'])
                ->select($this->goodsTransactionDetailTable)
                ->findOrFail($id);

            return $this->responseSuccess($data, 'Goods Transaction Detail detail retrieved successfully', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error('Error While retrieved Goods Transaction Detail data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Goods Transaction Detail not found', 404);
        }
    }

    /**
     * Update a goods transaction detail.
     */
    public function update(Request $request, $id)
    {
        $detail = GoodsTransactionDetail::findOrFail($id);

        $request->validate([
            'material_id' => [
                'sometimes',
                'nullable',
                'exists:materials,id',
                Rule::unique('goods_transaction_details')->where(function ($query) use ($request, $detail) {
                    $goodsTransactionId = $request->goods_transaction_id ?? $detail->goods_transaction_id;
                    return $query->where('goods_transaction_id', $goodsTransactionId)->whereNotNull('material_id');
                })->ignore($id),
            ],
            'vehicle_equipment_id' => [
                'sometimes',
                'nullable',
                'exists:vehicle_equipments,id',
                Rule::unique('goods_transaction_details')->where(function ($query) use ($request, $detail) {
                    $goodsTransactionId = $request->goods_transaction_id ?? $detail->goods_transaction_id;
                    return $query->where('goods_transaction_id', $goodsTransactionId)->whereNotNull('vehicle_equipment_id');
                })->ignore($id),
            ],
            'code' => 'sometimes|unique:goods_transaction_details,code,' . $id,
            'qty' => 'sometimes|required|integer|min:1',
            'type' => 'sometimes|nullable|in:pcs,set,box',
            'price' => 'sometimes|required|numeric|min:0',
            'in_stock' => 'nullable|boolean',
            'is_forecast' => 'nullable|boolean',
            'description' => 'nullable|string',
            'warehouse_id' => 'sometimes|required|exists:warehouses,id',
            'cash_id' => 'sometimes|nullable|exists:cashes,id',
            'person_id' => 'sometimes|nullable|exists:persons,id',
        ], [
            'material_id.unique' => 'This material already exists in this transaction.',
            'vehicle_equipment_id.unique' => 'This vehicle equipment already exists in this transaction.',
        ]);

        try {
            $data = array_filter(
                $request->only(['goods_transaction_id', 'material_id', 'vehicle_equipment_id', 'code', 'qty', 'type', 'price', 'in_stock', 'is_forecast', 'description']),
                fn ($val) => ! is_null($val) && $val !== ''
            );

            if (empty($data)) {
                return $this->responseError(null, 'No data provided to update', 422);
            }

            $goodsTransactionId = $data['goods_transaction_id'] ?? $detail->goods_transaction_id;
            $materialId = $data['material_id'] ?? $detail->material_id;
            $qty = $data['qty'] ?? $detail->qty;

            $transaction = GoodsTransaction::findOrFail($goodsTransactionId);

            if ($transaction->goodsTransactionBillingPayments()->exists()) {
                return $this->responseError(null, 'Cannot update items in a transaction that already has payments.', 422);
            }

            // Validation for issue / sales: check stock
            if ($transaction->type == 'issue' || $transaction->type == 'sales') {
                if ($materialId) {
                    $availableStock = $this->getAvailableStock($materialId, $detail->id);
                    if ($qty > $availableStock) {
                        return $this->responseError(null, "Insufficient stock. Available: {$availableStock}", 422);
                    }
                }

                $vehicleEquipmentId = $data['vehicle_equipment_id'] ?? $detail->vehicle_equipment_id;
                if ($vehicleEquipmentId) {
                    $availableStock = $this->getAvailableVehicleEquipmentStock($vehicleEquipmentId, $detail->id);
                    if ($qty > $availableStock) {
                        return $this->responseError(null, "Insufficient stock. Available: {$availableStock}", 422);
                    }
                }
            }

            DB::transaction(function () use ($data, $detail, $transaction, $request) {
                $detail->update($data);

                if ($request->has('warehouse_id')) {
                    $movement = WarehouseMovement::where('goods_transaction_detail_id', $detail->id)->first();
                    $personId = $request->person_id ?? $transaction->supplier_id ?? $transaction->driver_id ?? $transaction->customer_id ?? 1;

                    if ($movement) {
                        $activity = $movement->warehouseActivity;
                        if ($activity) {
                            $activity->update([
                                'person_id' => $personId,
                                'cash_id' => $request->cash_id,
                                'warehouse_id' => $request->warehouse_id,
                            ]);
                        }
                    } else {
                        $existingMovement = WarehouseMovement::where('goods_transaction_id', $transaction->id)->first();
                        
                        if ($existingMovement && $existingMovement->warehouse_activity_id) {
                            $activity = WarehouseActivity::find($existingMovement->warehouse_activity_id);
                        } else {
                            $activity = WarehouseActivity::create([
                                'person_id' => $personId,
                                'cash_id' => $request->cash_id,
                                'warehouse_id' => $request->warehouse_id,
                                'activity_type' => $transaction->type,
                                'activity_date' => $transaction->transaction_date ?? now(),
                                'description' => 'Automatic ' . $transaction->type . ' from Goods Transaction',
                            ]);
                        }

                        WarehouseMovement::create([
                            'warehouse_activity_id' => $activity->id,
                            'goods_transaction_id' => $transaction->id,
                            'goods_transaction_detail_id' => $detail->id,
                            'status' => $transaction->type == 'receipt' ? 'in' : 'out',
                        ]);
                    }

                    $detail->update([
                        'in_stock' => $transaction->type == 'receipt',
                        'is_forecast' => false
                    ]);
                }
            });

            return $this->responseSuccess($detail->fresh(), 'Goods Transaction Detail updated successfully', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error('Error while trying update Goods Transaction Detail data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Goods Transaction Detail update failed', 500);
        }
    }

    /**
     * Delete a goods transaction detail.
     */
    public function destroy($id)
    {
        try {
            $data = GoodsTransactionDetail::findOrFail($id);

            if ($data->goodsTransaction->goodsTransactionBillingPayments()->exists()) {
                return $this->responseError(null, 'Cannot delete items from a transaction that already has payments.', 422);
            }

            DB::transaction(function () use ($data) {
                $data->delete();
            });

            return $this->responseSuccess(null, 'Goods Transaction Detail deleted successfully', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error('Error while trying delete Goods Transaction Detail data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Goods Transaction Detail deletion failed', 500);
        }
    }

    /**
     * Calculate available stock for a material.
     */
    private function getAvailableStock($materialId, $excludeDetailId = null)
    {
        $purchased = GoodsTransactionDetail::where('material_id', $materialId)
            ->whereHas('goodsTransaction', function ($q) {
                $q->where('type', 'receipt');
            })
            ->sum('qty');

        $soldQuery = GoodsTransactionDetail::where('material_id', $materialId)
            ->whereHas('goodsTransaction', function ($q) {
                $q->where('type', 'issue');
            });

        if ($excludeDetailId) {
            $soldQuery->where('id', '!=', $excludeDetailId);
        }

        $sold = $soldQuery->sum('qty');

        return $purchased - $sold;
    }

    private function getAvailableVehicleEquipmentStock($vehicleEquipmentId, $excludeDetailId = null)
    {
        $equipment = VehicleEquipment::findOrFail($vehicleEquipmentId);
        return $equipment->getAvailableStock(null, $excludeDetailId);
    }
}
