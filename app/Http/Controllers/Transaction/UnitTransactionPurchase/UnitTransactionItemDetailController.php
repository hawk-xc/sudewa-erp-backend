<?php

namespace App\Http\Controllers\Transaction\UnitTransactionPurchase;

use App\Http\Controllers\Controller;
use App\Models\UnitTransactionItemDetail;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UnitTransactionItemDetailController extends Controller
{
    use ResponseTrait;

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
            'created_at',
        ];
    }

    public function index(Request $request)
    {
        try {
            $query = UnitTransactionItemDetail::query();

            $query->select($this->unitTransactionItemDetailTable)
                ->with(['unitTransactionItem:id,uuid']);

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
        } catch (Exception $err) {
            Log::error('Error While retrieved Unit Transaction Item Detail data : '.$err->getMessage());

            return $this->responseError(null, 'Unit Transaction Item Detail list retrieved Failed', 500);
        }
    }

    public function show(string $id)
    {
        try {
            $data = UnitTransactionItemDetail::with(['unitTransactionItem:id,uuid,price'])
                ->select($this->unitTransactionItemDetailTable)
                ->findOrFail($id);

            return $this->responseSuccess($data, 'Unit Transaction Item Detail retrieved successfully', 200);
        } catch (Exception $err) {
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

            $validated['color'] = strtoupper($request->color);

            $data = DB::transaction(function () use ($validated) {
                return UnitTransactionItemDetail::create($validated);
            });

            return $this->responseSuccess($data->fresh(), 'Unit Transaction Item Detail created successfully', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->responseError($e->errors(), 'Validation failed', 422);
        } catch (Exception $err) {
            Log::error('Error While storing Unit Transaction Item Detail data : '.$err->getMessage());

            return $this->responseError(null, 'Unit Transaction Item Detail creation failed', 500);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $detail = UnitTransactionItemDetail::findOrFail((int) $id);

            $validated = $request->validate([
                'unit_transaction_item_id' => 'sometimes|integer|exists:unit_transaction_items,id',
                'color' => 'sometimes|required|string|max:255',
                'machine_number' => 'sometimes|required|string|max:255|unique:unit_transaction_item_details,machine_number,'.$id,
                'chassis_number' => 'sometimes|required|string|max:255|unique:unit_transaction_item_details,chassis_number,'.$id,
            ]);

            $validated['color'] = strtoupper($request->color);

            DB::transaction(function () use ($detail, $validated) {
                $detail->update($validated);
            });

            return $this->responseSuccess($detail->fresh(), 'Unit Transaction Item Detail updated successfully', 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->responseError($e->errors(), 'Validation failed', 422);
        } catch (Exception $err) {
            Log::error('Error While updating Unit Transaction Item Detail data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Unit Transaction Item Detail update failed', 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $detail = UnitTransactionItemDetail::findOrFail($id);

            DB::transaction(function () use ($detail) {
                $detail->delete();
            });

            return $this->responseSuccess([], 'Unit Transaction Item Detail successfully Deleted', 200);
        } catch (Exception $err) {
            Log::error('Error While deleting Unit Transaction Item Detail data : '.$err->getMessage());

            return $this->responseError([], 'Unit Transaction Item Detail Not Found or Failed Deleted', 500);
        }
    }
}
