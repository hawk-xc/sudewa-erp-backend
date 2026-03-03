<?php

namespace App\Http\Controllers\Transaction\UnitTransactionPurchase;

use App\Http\Controllers\Controller;
use App\Models\UnitTransaction;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UnitTransactionController extends Controller
{
    use ResponseTrait;

    protected $unitTransactionTable;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only(['update', 'updateState']);
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);

        $this->unitTransactionTable = [
            'id',
            'uuid',
            'warehouse_id',
            'person_id',
            'code',
            'type',
            'max_capacity',
            'stock_state',
            'created_at',
        ];
    }

    public function index(Request $request)
    {
        try {
            $query = UnitTransaction::query();

            $query->select($this->unitTransactionTable)
                ->with(['warehouse', 'person', 'transactionFlow', 'unitTransactionBilling']);

            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%$search%")
                        ->orWhere('type', 'like', "%$search%")
                        ->orWhere('stock_state', 'like', "%$search%");
                });
            }

            foreach ($this->unitTransactionTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $allowedSort = $this->unitTransactionTable;

            $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->per_page ?? 10;

            $data = $query->paginate($perPage);

            return $this->responseSuccess($data, 'Unit Transaction list retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error While retrieved Unit Transaction data : '.$err->getMessage());

            return $this->responseError(null, 'Unit Transaction list retrieved Failed', 500);
        }
    }

    public function show(string $id)
    {
        try {
            $data = UnitTransaction::with(['warehouse', 'person', 'transactionFlow', 'unitTransactionBilling'])
                ->select($this->unitTransactionTable)
                ->findOrFail($id);

            return $this->responseSuccess($data, 'Unit Transaction retrieved successfully', 200);
        } catch (Exception $err) {
            return $this->responseError($err->getMessage(), 'Unit Transaction not found', 404);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'warehouse_id' => 'required|integer|exists:warehouses,id',
                'person_id' => 'required|integer|exists:persons,id',
                'code' => 'required|string|max:255|unique:unit_transactions,code',
                'type' => 'required|string|in:purchase,sales',
                'max_capacity' => 'required|numeric|min:0|max:100',
                'stock_state' => 'required|string|in:draft,cancel,rejected,prepare,inbound_purcase_order,inbound_incoming_goods,inbound_receipt,inbound_return,outbound_reserved,outbound_in_transit,outbound_delivered,outbound_return',
            ]);

            $data = DB::transaction(function () use ($validated) {
                return UnitTransaction::create($validated);
            });

            return $this->responseSuccess($data->fresh(), 'Unit Transaction created successfully', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->responseError($e->errors(), 'Validation failed', 422);
        } catch (Exception $err) {
            Log::error('Error While storing Unit Transaction data : '.$err->getMessage());

            return $this->responseError(null, 'Unit Transaction creation failed', 500);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $unitTransaction = UnitTransaction::findOrFail((int) $id);

            $validated = $request->validate([
                'warehouse_id' => 'sometimes|integer|exists:warehouses,id',
                'person_id' => 'sometimes|integer|exists:persons,id',
                'code' => 'sometimes|required|string|max:255|unique:unit_transactions,code,'.$id,
                'type' => 'sometimes|required|string|in:purchase,sales',
                'max_capacity' => 'sometimes|numeric|min:0|max:100',
                'stock_state' => 'sometimes|string|in:draft,cancel,rejected,prepare,inbound_purcase_order,inbound_incoming_goods,inbound_receipt,inbound_return,outbound_reserved,outbound_in_transit,outbound_delivered,outbound_return',
            ]);

            DB::transaction(function () use ($unitTransaction, $validated) {
                $unitTransaction->update($validated);
            });

            return $this->responseSuccess($unitTransaction->fresh(), 'Unit Transaction updated successfully', 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->responseError($e->errors(), 'Validation failed', 422);
        } catch (Exception $err) {
            Log::error('Error While updating Unit Transaction data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Unit Transaction update failed', 500);
        }
    }

    public function updateState(Request $request, string $id)
    {
        try {
            $unitTransaction = UnitTransaction::findOrFail((int) $id);

            $validated = $request->validate([
                'stock_state' => 'required|string|in:draft,cancel,rejected,prepare,inbound_purcase_order,inbound_incoming_goods,inbound_receipt,inbound_return,outbound_reserved,outbound_in_transit,outbound_delivered,outbound_return',
            ]);

            DB::transaction(function () use ($unitTransaction, $validated) {
                $unitTransaction->update($validated);
            });

            return $this->responseSuccess($unitTransaction->fresh(), 'Unit Transaction state updated successfully', 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->responseError($e->errors(), 'Validation failed', 422);
        } catch (Exception $err) {
            Log::error('Error While updating Unit Transaction state : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Unit Transaction state update failed', 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $data = UnitTransaction::findOrFail($id);

            DB::transaction(function () use ($data) {
                $data->delete();
            });

            return $this->responseSuccess([], 'Unit Transaction successfully Deleted', 200);
        } catch (Exception $err) {
            Log::error('Error While deleting Unit Transaction data : '.$err->getMessage());

            return $this->responseError([], 'Unit Transaction Not Found or Failed Deleted', 500);
        }
    }
}
