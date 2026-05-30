<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Imports\UnitTransactionItemDetailImport;
use App\Models\UnitTransactionItem;
use App\Models\UnitTransactionItemDetail;
use App\Traits\GlobalCodeNumberTrait;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class UnitTransactionItemDetailController extends Controller
{
    use ResponseTrait, GlobalCodeNumberTrait;

    protected $unitTransactionItemDetailTable;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only('update');
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);

        $this->unitTransactionItemDetailTable = [
            'id',
            'uuid',
            'unit_transaction_item_id',
            'color',
            'machine_number',
            'chassis_number',
            'in_stock',
            'is_forecast',
            'status',
            'created_at',
        ];
    }

    public function index(Request $request)
    {
        try {
            $query = UnitTransactionItemDetail::query();

            $query->select($this->unitTransactionItemDetailTable)
                ->with(['unitTransactionItem:id,uuid,price']);

            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('uuid', 'like', "%$search%")
                        ->orWhere('color', 'like', "%$search%")
                        ->orWhere('machine_number', 'like', "%$search%")
                        ->orWhere('chassis_number', 'like', "%$search%");
                });
            }

            foreach ($this->unitTransactionItemDetailTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $allowedSort = $this->unitTransactionItemDetailTable;

            $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->per_page ?? 10;

            $data = $query->paginate($perPage);

            return $this->responseSuccess($data, 'Unit Transaction Item Detail list retrieved successfully', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error('Error While retrieved Unit Transaction Item Detail data : '.$err->getMessage());

            return $this->responseError(null, 'Unit Transaction Item Detail list retrieved Failed', 500);
        }
    }

    public function show(string $id)
    {
        try {
            $data = UnitTransactionItemDetail::with(['unitTransactionItem:id,uuid,unit_transaction_id,unit_type_id,sparepart_id,price', 'unitTransactionItem.unitType:id,uuid,brand_id,code,name,unit_type,unit_model', 'unitTransactionItem.unitType.brand:id,uuid,name', 'unitTransactionItem.sparepart:id,uuid,sparepart_category_id,code,name', 'unitTransactionItem.sparepart.sparepartCategory:id,uuid,code,name', 'unitTransactionItem.unitTransaction:id,uuid,warehouse_id,person_id,code,type,stock_state'])
                ->select($this->unitTransactionItemDetailTable)
                ->findOrFail($id);

            return $this->responseSuccess($data, 'Unit Transaction Item Detail retrieved successfully', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            return $this->responseError($err->getMessage(), 'Unit Transaction Item Detail not found', 404);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'unit_transaction_item_id' => 'required|integer|exists:unit_transaction_items,id',
                'color' => 'required|string|max:255',
                'machine_number' => 'required|string|max:255|unique:unit_transaction_item_details,machine_number',
                'chassis_number' => 'required|string|max:255|unique:unit_transaction_item_details,chassis_number',
            ]);

            $unitItemTransaction = UnitTransactionItem::findOrFail($request->unit_transaction_item_id);

            $unitTransactionGetType = $unitItemTransaction->unitTransaction->type;

            if ($unitTransactionGetType == 'sales') {
                return $this->responseError(
                    'Cannot create data. this operation only use in purchase state',
                    'Validation failed',
                    422
                );
            }

            $quantityChecker = $unitItemTransaction->qty_total;

            $currentCount = UnitTransactionItemDetail::where('unit_transaction_item_id', $unitItemTransaction->id)->count();

            if ($currentCount >= $quantityChecker) {
                return $this->responseError(null, 'Unit Transaction Item Capacity Reach Maximum value', 422);
            }

            $validated['color'] = strtoupper($request->color);

            $data = DB::transaction(function () use ($validated) {
                return UnitTransactionItemDetail::create($validated);
            });

            return $this->responseSuccess($data->fresh(), 'Unit Transaction Item Detail created successfully', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->responseError($e->errors(), 'Validation failed', 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error('Error While storing Unit Transaction Item Detail data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Unit Transaction Item Detail creation failed', 500);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $detail = UnitTransactionItemDetail::findOrFail((int) $id);

            $unitItemTransaction = UnitTransactionItem::findOrFail($detail->unit_transaction_item_id);
            $unitTransactionGetType = $unitItemTransaction->unitTransaction->type;

            if ($unitTransactionGetType == 'sales') {
                return $this->responseError(
                    'Cannot create data. this operation only use in purchase state',
                    'Validation failed',
                    422
                );
            }

            $validated = $request->validate([
                'unit_transaction_item_id' => 'sometimes|integer|exists:unit_transaction_items,id',
                'color' => 'sometimes|required|string|max:255',
                'machine_number' => 'sometimes|required|string|max:255|unique:unit_transaction_item_details,machine_number,'.$id,
                'chassis_number' => 'sometimes|required|string|max:255|unique:unit_transaction_item_details,chassis_number,'.$id,
                'status' => 'sometimes|string|in:minor_damage,major_damage,returned,refunded,lost,in_repair',
            ]);

            $validated['color'] = strtoupper($request->color);

            DB::transaction(function () use ($detail, $validated) {
                $detail->update($validated);
            });

            return $this->responseSuccess($detail->fresh(), 'Unit Transaction Item Detail updated successfully', 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->responseError($e->errors(), 'Validation failed', 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error('Error While updating Unit Transaction Item Detail data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Unit Transaction Item Detail update failed', 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $detail = UnitTransactionItemDetail::findOrFail($id);

            $unitItemTransaction = UnitTransactionItem::findOrFail($detail->unit_transaction_item_id);
            $unitTransactionGetType = $unitItemTransaction->unitTransaction->type;

            if ($unitTransactionGetType == 'sales') {
                return $this->responseError(
                    'Cannot create data. this operation only use in purchase state',
                    'Validation failed',
                    422
                );
            }

            DB::transaction(function () use ($detail) {
                $detail->delete();
            });

            return $this->responseSuccess([], 'Unit Transaction Item Detail successfully Deleted', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error('Error While deleting Unit Transaction Item Detail data : '.$err->getMessage());

            return $this->responseError([], 'Unit Transaction Item Detail Not Found or Failed Deleted', 500);
        }
    }

    public function import(Request $request, int $unitTransactionItemId)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        try {
            Excel::import(new UnitTransactionItemDetailImport((int) $unitTransactionItemId), $request->file('file'));

            return $this->responseSuccess(null, 'Unit Transaction Item Details imported successfully', 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error('Unit Transaction Item Details import error', [
                'message' => $err->getMessage(),
            ]);

            return $this->responseError(null, $err->getMessage(), 500);
        }
    }
}
