<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\MaterialTransactionDetail;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MaterialTransactionDetailController extends Controller
{
    use ResponseTrait;

    public function index(Request $request)
    {
        try {
            $query = MaterialTransactionDetail::with(['materialTransaction', 'material']);

            if ($request->filled('material_transaction_id')) {
                $query->where('material_transaction_id', $request->material_transaction_id);
            }

            $data = $query->orderBy('id', 'desc')->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'Material Transaction Detail list retrieved successfully');
        } catch (Exception $e) {
            Log::error('Error retrieved Material Transaction Detail list: ' . $e->getMessage());
            return $this->responseError($e->getMessage(), 'Failed to retrieve Material Transaction Detail list', 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'material_transaction_id' => 'required|exists:material_transactions,id',
                'material_id' => 'required|exists:materials,id',
                'qty' => 'required|integer|min:1',
                'price' => 'required|numeric|min:0',
                'in_stock' => 'sometimes|boolean',
                'is_forecast' => 'sometimes|boolean',
                'description' => 'nullable|string',
            ]);

            $data = DB::transaction(function () use ($validated) {
                if (empty($validated['description'])) {
                    $transaction = \App\Models\MaterialTransaction::findOrFail($validated['material_transaction_id']);
                    $validated['description'] = "Pembayaran pembelian material ke " . $transaction->supplier_name;
                }
                return MaterialTransactionDetail::create($validated);
            });

            return $this->responseSuccess($data, 'Material Transaction Detail created successfully', 201);
        } catch (Exception $e) {
            Log::error('Error creating Material Transaction Detail: ' . $e->getMessage());
            return $this->responseError($e->getMessage(), 'Failed to create Material Transaction Detail', 500);
        }
    }

    public function show($id)
    {
        try {
            $data = MaterialTransactionDetail::with(['materialTransaction', 'material'])->findOrFail($id);
            return $this->responseSuccess($data, 'Material Transaction Detail detail retrieved successfully');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'Material Transaction Detail not found', 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'material_transaction_id' => 'sometimes|required|exists:material_transactions,id',
                'material_id' => 'sometimes|required|exists:materials,id',
                'qty' => 'sometimes|required|integer|min:1',
                'price' => 'sometimes|required|numeric|min:0',
                'in_stock' => 'sometimes|boolean',
                'is_forecast' => 'sometimes|boolean',
                'description' => 'nullable|string',
            ]);

            $data = MaterialTransactionDetail::findOrFail($id);
            
            DB::transaction(function () use ($validated, $data) {
                $data->update($validated);
            });

            return $this->responseSuccess($data, 'Material Transaction Detail updated successfully');
        } catch (Exception $e) {
            Log::error('Error updating Material Transaction Detail: ' . $e->getMessage());
            return $this->responseError($e->getMessage(), 'Failed to update Material Transaction Detail', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $data = MaterialTransactionDetail::findOrFail($id);
            DB::transaction(function () use ($data) {
                $data->delete();
            });
            return $this->responseSuccess(null, 'Material Transaction Detail deleted successfully');
        } catch (Exception $e) {
            Log::error('Error deleting Material Transaction Detail: ' . $e->getMessage());
            return $this->responseError($e->getMessage(), 'Failed to delete Material Transaction Detail', 500);
        }
    }
}
