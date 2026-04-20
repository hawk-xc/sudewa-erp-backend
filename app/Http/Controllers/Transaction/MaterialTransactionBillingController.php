<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\MaterialTransactionBilling;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MaterialTransactionBillingController extends Controller
{
    use ResponseTrait;

    public function index(Request $request)
    {
        try {
            $query = MaterialTransactionBilling::with(['materialTransaction', 'cash']);

            if ($request->filled('material_transaction_id')) {
                $query->where('material_transaction_id', $request->material_transaction_id);
            }

            if ($request->filled('cash_id')) {
                $query->where('cash_id', $request->cash_id);
            }

            $data = $query->orderBy('id', 'desc')->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'Material Transaction Billing list retrieved successfully');
        } catch (Exception $e) {
            Log::error('Error retrieved Material Transaction Billing list: ' . $e->getMessage());
            return $this->responseError($e->getMessage(), 'Failed to retrieve Material Transaction Billing list', 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'material_transaction_id' => 'required|exists:material_transactions,id',
                'cash_id' => 'required|exists:cashes,id',
                'amount' => 'required|numeric|min:0',
                'payment_date' => 'required|date',
                'description' => 'nullable|string',
            ]);

            $data = DB::transaction(function () use ($validated) {
                return MaterialTransactionBilling::create($validated);
            });

            return $this->responseSuccess($data, 'Material Transaction Billing created successfully', 201);
        } catch (Exception $e) {
            Log::error('Error creating Material Transaction Billing: ' . $e->getMessage());
            return $this->responseError($e->getMessage(), 'Failed to create Material Transaction Billing', 500);
        }
    }

    public function show($id)
    {
        try {
            $data = MaterialTransactionBilling::with(['materialTransaction', 'cash'])->findOrFail($id);
            return $this->responseSuccess($data, 'Material Transaction Billing detail retrieved successfully');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'Material Transaction Billing not found', 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'material_transaction_id' => 'sometimes|required|exists:material_transactions,id',
                'cash_id' => 'sometimes|required|exists:cashes,id',
                'amount' => 'sometimes|required|numeric|min:0',
                'payment_date' => 'sometimes|required|date',
                'description' => 'nullable|string',
            ]);

            $data = MaterialTransactionBilling::findOrFail($id);
            
            DB::transaction(function () use ($validated, $data) {
                $data->update($validated);
            });

            return $this->responseSuccess($data, 'Material Transaction Billing updated successfully');
        } catch (Exception $e) {
            Log::error('Error updating Material Transaction Billing: ' . $e->getMessage());
            return $this->responseError($e->getMessage(), 'Failed to update Material Transaction Billing', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $data = MaterialTransactionBilling::findOrFail($id);
            DB::transaction(function () use ($data) {
                $data->delete();
            });
            return $this->responseSuccess(null, 'Material Transaction Billing deleted successfully');
        } catch (Exception $e) {
            Log::error('Error deleting Material Transaction Billing: ' . $e->getMessage());
            return $this->responseError($e->getMessage(), 'Failed to delete Material Transaction Billing', 500);
        }
    }
}
