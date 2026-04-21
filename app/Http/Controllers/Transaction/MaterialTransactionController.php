<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\MaterialTransaction;
use App\Traits\ResponseTrait;
use App\Traits\MaterialTransactionTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MaterialTransactionController extends Controller
{
    use ResponseTrait, MaterialTransactionTrait;

    public function index(Request $request)
    {
        try {
            $query = MaterialTransaction::query();

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where('supplier_name', 'like', "%$search%");
            }

            $query->with(['materialTransactionDetails.material', 'materialTransactionBillings.cash']);

            $data = $query->orderBy('id', 'desc')->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'Material Transaction list retrieved successfully');
        } catch (Exception $e) {
            Log::error('Error retrieved Material Transaction list: ' . $e->getMessage());
            return $this->responseError($e->getMessage(), 'Failed to retrieve Material Transaction list', 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'type' => 'required|in:purchase,sales',
                'supplier_name' => 'required|string|max:255',
                'transaction_date' => 'required|date',
                'description' => 'nullable|string',
            ]);

            $data = DB::transaction(function () use ($request, $validated) {
                $validated['code'] = $this->generateMaterialCode($validated['type']);
                $typeState = $request->type == "purchase" ? "pembelian" : "penjualan";
                
                if (empty($validated['description'])) {
                    $validated['description'] = "Pembayaran " . $typeState . " material ke " . $validated['supplier_name'];
                }

                return MaterialTransaction::create($validated);
            });

            return $this->responseSuccess($data, 'Material Transaction created successfully', 201);
        } catch (Exception $e) {
            Log::error('Error creating Material Transaction: ' . $e->getMessage());
            return $this->responseError($e->getMessage(), 'Failed to create Material Transaction', 500);
        }
    }

    public function show($id)
    {
        try {
            $data = MaterialTransaction::with(['materialTransactionDetails.material', 'materialTransactionBillings.cash'])->findOrFail($id);
            return $this->responseSuccess($data, 'Material Transaction detail retrieved successfully');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'Material Transaction not found', 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'supplier_name' => 'sometimes|required|string|max:255',
                'transaction_date' => 'sometimes|required|date',
                'description' => 'nullable|string',
            ]);

            $data = MaterialTransaction::findOrFail($id);
            
            DB::transaction(function () use ($validated, $data) {
                $data->update($validated);
            });

            return $this->responseSuccess($data, 'Material Transaction updated successfully');
        } catch (Exception $e) {
            Log::error('Error updating Material Transaction: ' . $e->getMessage());
            return $this->responseError($e->getMessage(), 'Failed to update Material Transaction', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $data = MaterialTransaction::findOrFail($id);
            DB::transaction(function () use ($data) {
                $data->delete();
            });
            return $this->responseSuccess(null, 'Material Transaction deleted successfully');
        } catch (Exception $e) {
            Log::error('Error deleting Material Transaction: ' . $e->getMessage());
            return $this->responseError($e->getMessage(), 'Failed to delete Material Transaction', 500);
        }
    }
}
