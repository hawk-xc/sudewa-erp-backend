<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\Tax;
use App\Models\UnitTransaction;
use App\Models\UnitTransactionItem;
use App\Models\UnitType;
use App\Traits\CalculateDecimalAmount;
use App\Traits\GlobalCodeNumberTrait;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UnitTransactionItemController extends Controller
{
    use CalculateDecimalAmount, GlobalCodeNumberTrait, ResponseTrait;

    protected array $unitTransactionItemTable;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only('update');
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);

        $this->unitTransactionItemTable = [
            'id',
            'uuid',
            'unit_transaction_id',
            'unit_type_id',
            'sparepart_id',
            'qty_total',
            'price',
            'price_per_unit_usd',
            'price_usd',
            'bbn_price',
            'hpp_per_unit_price',
            'dpp_per_unit_price',
            'ppn_per_unit_price',
            'hpp_total_price',
            'dpp_total_price',
            'ppn_total_price',
            'expedition_fee',
            'other_fee',
            'dpp_tax_id',
            'dpp_tax_rate',
            'ppn_tax_id',
            'ppn_tax_rate',
            'created_at',
            'updated_at',
        ];
    }

    public function index(Request $request)
    {
        $request->validate([
            'type' => 'nullable|in:purchase,sales',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
        ]);

        try {
            $query = UnitTransactionItem::query();

            $query->select($this->unitTransactionItemTable)
                ->with([
                    'unitTransaction:id,uuid,code,warehouse_id',
                    'dppTax:id,tax_id,name,rate',
                    'dppTax.tax:id,code,name',
                    'ppnTax:id,tax_id,name,rate',
                    'ppnTax.tax:id,code,name',
                ]);

            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('uuid', 'like', "%$search%")
                        ->orWhere('qty_total', 'like', "%$search%")
                        ->orWhere('price', 'like', "%$search%")
                        ->orWhere('price_per_unit_usd', 'like', "%$search%")
                        ->orWhere('price_usd', 'like', "%$search%");
                });
            }

            if ($request->filled('type') && in_array($request->type, ['purchase', 'sales'])) {
                $query->whereHas('unitTransaction', function ($searchQuery) use ($request) {
                    $searchQuery->where('type', $request->type);
                });
            }

            foreach ($this->unitTransactionItemTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $allowedSort = $this->unitTransactionItemTable;

            $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->per_page ?? 10;

            $data = $query->paginate($perPage);

            return $this->responseSuccess($data, 'Unit Transaction Item list retrieved successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));

            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error While retrieved Unit Transaction Item data : ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Unit Transaction Item list retrieved Failed', 500);
        }
    }

    public function show(string $id)
    {
        try {
            $item = UnitTransactionItem::with([
                'unitTransaction',
                'unitTransactionItemDetails',
                'unitTransactionItemSales',
                'dppTax',
                'ppnTax',
            ])->select($this->unitTransactionItemTable)->findOrFail($id);

            return $this->responseSuccess($item, 'Unit Transaction Item retrieved successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));

            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            return $this->responseError($err->getMessage(), 'Unit Transaction Item not found', 404);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'unit_transaction_id' => 'required|integer|exists:unit_transactions,id',
                'unit_type_id' => 'nullable|integer|exists:unit_types,id',
                'sparepart_id' => 'nullable|integer|exists:spareparts,id',
                'qty_total' => 'required|integer|min:1',
                'price' => 'required|decimal:0,2',
                'price_per_unit_usd' => 'nullable|numeric|min:0',
                'price_usd' => 'nullable|numeric|min:0',
                'bbn_price' => 'nullable|decimal:0,2',
                'hpp_per_unit_price' => 'nullable|numeric',
                'dpp_per_unit_price' => 'nullable|numeric',
                'ppn_per_unit_price' => 'nullable|numeric',
                'expedition_fee' => 'nullable|numeric',
                'other_fee' => 'nullable|numeric',
                'dpp_tax_id' => 'nullable|integer|exists:taxes,id',
                'ppn_tax_id' => 'nullable|integer|exists:taxes,id',
            ]);

            if ($request->filled('dpp_tax_id')) {
                $taxDefault = $this->resolveTax('dpp', $request->dpp_tax_id);
                
                $validated['dpp_tax_rate'] = $taxDefault['rate'];
            } else {
                $taxDefault = $this->resolveTax('dpp', null);

                $validated['dpp_tax_id'] = $taxDefault['id'];
                $validated['dpp_tax_rate'] = $taxDefault['rate'];
            }

            if ($request->filled('ppn_tax_id')) {
                $taxDefault = $this->resolveTax('ppn', $request->ppn_tax_id);

                $validated['ppn_tax_rate'] = $taxDefault['rate'];
            } else {
                $taxDefault = $this->resolveTax('ppn', null);

                $validated['ppn_tax_id'] = $taxDefault['id'];
                $validated['ppn_tax_rate'] = $taxDefault['rate'];
            }

            $unitTransaction = UnitTransaction::findOrFail($request->unit_transaction_id);
            $unitTransactionItems = $unitTransaction->unitTransactionItems;

            if ($unitTransaction->unitTransactionBilling()->exists()) {
                return $this->responseError(
                    'Cannot create data. The selected unit transaction has already been billed.',
                    'Validation failed',
                    422
                );
            }

            // Unit Type Stock Guard
            if ($unitTransaction->type == 'sales') {
                $unitTypeRealStock = UnitType::findOrFail((int) $request->unit_type_id)->getRealStock($unitTransaction->warehouse->id);

                // Stock Guard
                if ($unitTypeRealStock == 0) {
                    return $this->responseError(
                        'Cannot create data. The selected unit type has no stock available in the warehouse.',
                        'Validation failed',
                        422
                    );
                }

                // Quota Guard
                if ($request->qty_total > $unitTypeRealStock) {
                    return $this->responseError(
                        'Cannot create data. The requested quantity exceeds the available stock in the warehouse.',
                        'Validation failed',
                        422
                    );
                }
            }

            // warehouse capacity guard
            if ($unitTransaction->type === 'purchase') {
                $warehouse = $unitTransaction->warehouse;
                $warehouseForecastCapacity = $warehouse->capacity - $warehouse->getWarehouseCapacityUsage();
                if ($request->qty_total > $warehouseForecastCapacity) {
                    return $this->responseError(
                        'Cannot create data. The quantity exceeds the remaining warehouse capacity.',
                        'Validation failed',
                        422
                    );
                }
            }

            // duplicate unit type guard
            if ($unitTransactionItems->where('unit_type_id', $request->unit_type_id)->isNotEmpty()) {
                return $this->responseError(
                    'Cannot create data. The selected unit type already exists in this unit transaction.',
                    'Validation failed',
                    422
                );
            }

            if (isset($request->unit_type_id) && isset($request->sparepart_id)) {
                return $this->responseError(
                    'Cannot create data. Please select either unit_type_id or sparepart_id, not both.',
                    'Validation failed',
                    422
                );
            }

            $item = DB::transaction(function () use ($request, $validated) {

                $additional_fee =
                    ($request->bbn_price ?? 0) +
                    ($request->expedition_fee ?? 0) +
                    ($request->other_fee ?? 0);

                $hpp = $request->price - $additional_fee;

                $hpp = $this->calculateDecimalAmount($hpp);
                
                $dpp = $hpp / ($validated['dpp_tax_rate'] / 100);
                $ppn = $dpp * ($validated['ppn_tax_rate'] / 100);

                $validated['hpp_per_unit_price'] = $this->calculateDecimalAmount($hpp);
                $validated['dpp_per_unit_price'] = $this->calculateDecimalAmount($dpp);
                $validated['ppn_per_unit_price'] = $this->calculateDecimalAmount($ppn);

                $validated['hpp_total_price'] = $this->calculateDecimalAmount($hpp * $request->qty_total);
                $validated['dpp_total_price'] = $this->calculateDecimalAmount($dpp * $request->qty_total);
                $validated['ppn_total_price'] = $this->calculateDecimalAmount($ppn * $request->qty_total);

                $validated['price_per_unit_usd'] = $request->price_per_unit_usd ?? 0;
                $validated['price_usd'] = $request->price_usd ?? ($validated['price_per_unit_usd'] * $request->qty_total);

                return UnitTransactionItem::create($validated);
            });

            return $this->responseSuccess($item->fresh('unitTransaction'), 'Unit Transaction Item created successfully', 201);
        } catch (ValidationException $e) {
            return $this->responseError($e->errors(), 'Validation failed', 422);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));

            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error While storing Unit Transaction Item data : ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Unit Transaction Item creation failed', 500);
        }
    }

    public function getFormula(Request $request)
    {
        try {
            $request->validate([
                'qty_total' => 'nullable|integer|min:1',
                'price' => 'nullable|numeric|min:0',
                'price_per_unit_usd' => 'nullable|numeric|min:0',
                'price_usd' => 'nullable|numeric|min:0',
                'bbn_price' => 'nullable|numeric|min:0',
                'expedition_fee' => 'nullable|numeric|min:0',
                'other_fee' => 'nullable|numeric|min:0',
                'dpp_tax_id' => 'nullable|integer|exists:taxes,id',
                'ppn_tax_id' => 'nullable|integer|exists:taxes,id',
            ]);

            if ($request->filled('dpp_tax_id')) {
                $dppTaxRate = $this->resolveTax('dpp', $request->dpp_tax_id)['rate'];
            } else {
                $taxDefault = $this->resolveTax('dpp', null);
                $dppTaxRate = $taxDefault['rate'];
            }

            if ($request->filled('ppn_tax_id')) {
                $ppnTaxRate = $this->resolveTax('ppn', $request->ppn_tax_id)['rate'];
            } else {
                $taxDefault = $this->resolveTax('ppn', null);
                $ppnTaxRate = $taxDefault['rate'];
            }

            $qty = $request->qty_total ?? 0;
            $price = $request->price ?? 0;
            $pricePerUnitUsd = $request->price_per_unit_usd ?? 0;

            $bbn = $request->bbn_price ?? 0;
            $expedition = $request->expedition_fee ?? 0;
            $other = $request->other_fee ?? 0;

            $additional_fee = $bbn + $expedition + $other;

            $hpp = $price - $additional_fee;

            $hpp = $this->calculateDecimalAmount($hpp);
            $dpp = $dppTaxRate > 0 ? ($hpp / ($dppTaxRate / 100)) : 0;
            $ppn = $dpp * ($ppnTaxRate / 100);

            $hppPerUnit = $this->calculateDecimalAmount($hpp);
            $dppPerUnit = $this->calculateDecimalAmount($dpp);
            $ppnPerUnit = $this->calculateDecimalAmount($ppn);

            $hppTotal = $this->calculateDecimalAmount($hpp * $qty);
            $dppTotal = $this->calculateDecimalAmount($dpp * $qty);
            $ppnTotal = $this->calculateDecimalAmount($ppn * $qty);

            $priceUsd = $request->price_usd ?? ($pricePerUnitUsd * $qty);

            $result = [
                'bbn_price' => $this->calculateDecimalAmount($bbn),
                'expedition_fee' => $this->calculateDecimalAmount($expedition),
                'other_fee' => $this->calculateDecimalAmount($other),

                'hpp_per_unit_price' => $hppPerUnit,
                'dpp_per_unit_price' => $dppPerUnit,
                'ppn_per_unit_price' => $ppnPerUnit,

                'hpp_total_price' => $hppTotal,
                'dpp_total_price' => $dppTotal,
                'ppn_total_price' => $ppnTotal,

                'price_per_unit_usd' => (float) $pricePerUnitUsd,
                'price_usd' => (float) $priceUsd,
            ];

            return $this->responseSuccess((object) $result, 'Transaction Item Formula', 200);
        } catch (ValidationException $e) {
            return $this->responseError($e->errors(), 'Validation failed', 422);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));

            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error While calculating Unit Transaction Item formula : ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Transaction Item Formula Failed', 500);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $item = UnitTransactionItem::findOrFail($id);

            $itemDetailsCount = $item->unitTransactionItemDetails()->count();

            $validated = $request->validate([
                'unit_transaction_id' => 'sometimes|integer|exists:unit_transactions,id',
                'unit_type_id' => 'sometimes|nullable|integer|exists:unit_types,id',
                'sparepart_id' => 'sometimes|nullable|integer|exists:spareparts,id',
                'qty_total' => 'sometimes|integer|min:1',
                'price' => 'sometimes|numeric',
                'price_per_unit_usd' => 'nullable|numeric|min:0',
                'price_usd' => 'nullable|numeric|min:0',
                'bbn_price' => 'nullable|numeric',
                'hpp_per_unit_price' => 'nullable|numeric',
                'dpp_per_unit_price' => 'nullable|numeric',
                'ppn_per_unit_price' => 'nullable|numeric',
                'expedition_fee' => 'nullable|numeric',
                'other_fee' => 'nullable|numeric',
            ]);

            if ($request->filled('qty_total') && $request->qty_total < $itemDetailsCount) {
                return $this->responseError('Cannot update data. The quantity exceeds the remaining warehouse capacity.', 'Validation failed', 422);
            }

            if ($request->filled('unit_transaction_id')) {
                $unitTransaction = UnitTransaction::findOrFail($request->unit_transaction_id);
                $unitTransactionItems = $unitTransaction->unitTransactionItems;

                // Unit Type Stock Guard
                if ($unitTransaction->type == 'sales') {
                    $unitTypeRealStock = UnitType::findOrFail((int) $request->unit_type_id)->getRealStock($unitTransaction->warehouse->id);

                    // Stock Guard
                    if ($unitTypeRealStock == 0) {
                        return $this->responseError(
                            'Cannot create data. The selected unit type has no stock available in the warehouse.',
                            'Validation failed',
                            422
                        );
                    }

                    // Quota Guard
                    if ($request->qty_total > $unitTypeRealStock) {
                        return $this->responseError(
                            'Cannot create data. The requested quantity exceeds the available stock in the warehouse.',
                            'Validation failed',
                            422
                        );
                    }
                }

                // warehouse capacity guard
                if ($unitTransaction->type === 'purchase') {
                    $warehouse = $unitTransaction->warehouse;
                    $warehouseForecastCapacity = $warehouse->capacity - $warehouse->getWarehouseCapacityUsage();
                    $currentInStockCount = $item->unitTransactionItemDetails()->where('in_stock', true)->count();
                    if ($request->qty_total > $warehouseForecastCapacity + $currentInStockCount) {
                        return $this->responseError(
                            'Cannot update data. The quantity exceeds the remaining warehouse capacity.',
                            'Validation failed',
                            422
                        );
                    }
                }

                // duplicate unit type guard
                if ($unitTransactionItems->where('unit_type_id', $request->unit_type_id)->isNotEmpty()) {
                    return $this->responseError(
                        'Cannot create data. The selected unit type already exists in this unit transaction.',
                        'Validation failed',
                        422
                    );
                }

                // transaction billing paid guard
                $paidTransaction = $item->unitTransaction->unitTransactionBilling;
                if ($paidTransaction) {
                    if ($paidTransaction->is_paid == true) {
                        return $this->responseError(
                            'Cannot update unit transaction data, unit transaction had billing data and status paid',
                            'Validation failed',
                            422
                        );
                    }
                }
            }

            if ($request->filled('unit_type_id') && isset($request->sparepart_id)) {
                return $this->responseError(
                    'Cannot create data. Please select either unit_type_id or sparepart_id, not both.',
                    'Validation failed',
                    422
                );
            }

            // SSOT guard
            if ($request->unit_type_id) {
                if ($item->qty_total < $request->qty_total) {
                    return $this->responseError(
                        'Cannot update unit_type_id, because this unit transaction item has unit transaction item details data',
                        'Validation failed',
                        422
                    );
                }
            }

            $unitTransactionId = $validated['unit_transaction_id'] ?? $item->unit_transaction_id;
            $unitTypeId = $validated['unit_type_id'] ?? $item->unit_type_id;

            $exists = UnitTransactionItem::where('unit_transaction_id', $unitTransactionId)
                ->where('unit_type_id', $unitTypeId)
                ->where('id', '!=', $item->id)
                ->exists();

            if ($exists) {
                return $this->responseError(
                    'Unit Type Already Exist in this unit transaction data',
                    'Validation failed',
                    422
                );
            }

            if (isset($validated['qty_total']) || isset($validated['price_per_unit_usd'])) {
                $pricePerUnitUsd = array_key_exists('price_per_unit_usd', $validated) ? $validated['price_per_unit_usd'] : $item->price_per_unit_usd;
                if ($pricePerUnitUsd !== null && ! isset($validated['price_usd'])) {
                    $qty = $validated['qty_total'] ?? $item->qty_total;
                    $validated['price_usd'] = $pricePerUnitUsd * $qty;
                }
            }

            DB::transaction(function () use ($item, $validated) {
                $item->update($validated);
            });

            return $this->responseSuccess($item->fresh(), 'Unit Transaction Item updated successfully', 200);
        } catch (ValidationException $e) {
            return $this->responseError($e->errors(), 'Validation failed', 422);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));

            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error While updating Unit Transaction Item data : ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Unit Transaction Item update failed', 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $item = UnitTransactionItem::findOrFail($id);

            DB::transaction(function () use ($item) {
                $item->delete();
            });

            return $this->responseSuccess([], 'Unit Transaction Item sucessfully Deleted', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));

            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            return $this->responseError([], 'Unit Transaction Item Not Found or Failed Deleted', 500);
        }
    }

    public function bulkDelete(string $id)
    {
        try {
            $item = UnitTransactionItem::findOrFail($id);

            DB::transaction(function () use ($item) {
                foreach ($item->unitTransactionItemDetails as $itemDetail) {
                    $itemDetail->delete();
                }
            });

            return $this->responseSuccess((object) null, 'Unit Transaction Item Detail sucessfully Deleted', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));

            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            return $this->responseError(null, 'Unit Transaction Item Detail Not Found or Failed Deleted', 500);
        }
    }

    private function resolveTax(string $code, ?int $id): array
    {
        if ($id) {
            $tax = Tax::findOrFail($id);
        } else {
            $tax = Tax::where('code', $code)->firstOrFail();
        }

        $version = $tax->getDefault();

        return [
            'id' => $tax->id,
            'rate' => $version->rate,
        ];
    }
}
