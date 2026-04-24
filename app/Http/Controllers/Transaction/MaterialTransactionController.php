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

    // projection
    protected $materialTransactionTable;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only('update');
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);

        $this->materialTransactionTable = [
            'id',
            'uuid',
            'code',
            'type',
            'supplier_name',
            'is_paid',
            'transaction_date',
            'description',
            'created_at',
        ];
    }

    /**
     * List all material transactions.
     */
    public function index(Request $request)
    {
        $query = MaterialTransaction::query();
        
        if ($request->filled('type')) {
            if ($request->type == 'purchase') {
                $query->where('type', 'purchase');
            } else {
                $query->where('type', 'sales');
            }
        } 

        $query->select($this->materialTransactionTable);

        try {
            if ($request->filled('search')) {
                $search = $request->search;
                $caseSensitive = $request->boolean('case_sensitive');

                $query->where(function ($q) use ($search, $caseSensitive) {
                    if ($caseSensitive) {
                        $q->where('supplier_name', 'LIKE BINARY', "%$search%")
                            ->orWhere('code', 'LIKE BINARY', "%$search%");
                    } else {
                        $q->where('supplier_name', 'like', "%$search%")
                            ->orWhere('code', 'like', "%$search%");
                    }
                });
            }

            foreach ($this->materialTransactionTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $allowedSort = $this->materialTransactionTable;

            $sortBy = in_array($request->sort_by, $allowedSort)
                ? $request->sort_by
                : 'id';

            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $query->with(['materialTransactionDetails', 'materialTransactionBillings']);

            $perPage = $request->per_page ?? 10;

            $data = $query->paginate($perPage)
                ->through(function ($item) {
                    $item->total_amount = $item->getTotalAmount();
                    $item->total_paid = $item->getTotalPaidAmount();
                    $item->total_unpaid = $item->getRemainingAmount();

                    $item->unsetRelation('materialTransactionDetails');
                    $item->unsetRelation('materialTransactionBillings');

                    return $item;
                });

            return $this->responseSuccess($data, 'Material Transaction list retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error While retrieved Material Transaction data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Material Transaction list retrieved Failed', 500);
        }
    }

    /**
     * Store a new material transaction.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:purchase,sales',
            'supplier_name' => 'required|string|max:255',
            'transaction_date' => 'required|date',
            'description' => 'nullable|string',
        ]);

        try {
            $data = DB::transaction(function () use ($request, $validated) {
                $validated['code'] = $this->generateMaterialCode($validated['type']);
                $typeState = $request->type == "purchase" ? "pembelian" : "penjualan";
                
                if (empty($validated['description'])) {
                    $validated['description'] = "Pembayaran " . $typeState . " material ke " . $validated['supplier_name'];
                }

                return MaterialTransaction::create($validated);
            });

            return $this->responseSuccess($data, 'Material Transaction created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error while trying create Material Transaction Data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Material Transaction creation failed', 500);
        }
    }

    /**
     * Get material transaction details.
     */
    public function show($id)
    {
        try {
            $data = MaterialTransaction::with(['materialTransactionDetails.material', 'materialTransactionBillings.cash'])
                ->select($this->materialTransactionTable)
                ->findOrFail($id);

            $data['total_amount'] = $data->getTotalAmount();
            $data['total_paid'] = $data->getTotalPaidAmount();
            $data['total_unpaid'] = $data->getRemainingAmount();
            
            return $this->responseSuccess($data, 'Material Transaction detail retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error While retrieved Material Transaction data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Material Transaction not found', 404);
        }
    }

    /**
     * Update a material transaction.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'supplier_name' => 'sometimes|required|string|max:255',
            'transaction_date' => 'sometimes|required|date',
            'description' => 'nullable|string',
        ]);

        try {
            $transaction = MaterialTransaction::findOrFail($id);
            
            $data = array_filter(
                $request->only(['supplier_name', 'transaction_date', 'description']),
                fn ($val) => ! is_null($val) && $val !== ''
            );

            if (empty($data)) {
                return $this->responseError(null, 'No data provided to update', 422);
            }

            DB::transaction(function () use ($data, $transaction) {
                $transaction->update($data);
            });

            return $this->responseSuccess($transaction->fresh(), 'Material Transaction updated successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying update Material Transaction data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Material Transaction update failed', 500);
        }
    }

    /**
     * Delete a material transaction.
     */
    public function destroy($id)
    {
        try {
            $data = MaterialTransaction::findOrFail($id);

            DB::transaction(function () use ($data) {
                $data->delete();
            });

            return $this->responseSuccess(null, 'Material Transaction deleted successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying delete Material Transaction data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Material Transaction deletion failed', 500);
        }
    }
}
