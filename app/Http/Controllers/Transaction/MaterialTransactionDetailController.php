<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\MaterialTransaction;
use App\Models\MaterialTransactionDetail;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MaterialTransactionDetailController extends Controller
{
    use ResponseTrait;

    // projection
    protected $materialTransactionDetailTable;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only('update');
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);

        $this->materialTransactionDetailTable = [
            'id',
            'uuid',
            'order_code',
            'material_transaction_id',
            'material_id',
            'qty',
            'price',
            'in_stock',
            'is_forecast',
            'description',
            'created_at',
        ];
    }

    /**
     * List all material transaction details.
     */
    public function index(Request $request)
    {   
        $query = MaterialTransactionDetail::with(['materialTransaction', 'material']);

        $query->select($this->materialTransactionDetailTable);

        try {
            if ($request->filled('type')) {
                $query->whereHas('materialTransaction', function ($q) use ($request) {
                    $q->where('type', $request->type);
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
                            ->orWhereHas('materialTransaction', function ($tq) use ($search) {
                                $tq->where('code', 'LIKE BINARY', "%$search%")
                                    ->orWhere('supplier_name', 'LIKE BINARY', "%$search%");
                            });
                    } else {
                        $q->where('description', 'like', "%$search%")
                            ->orWhereHas('material', function ($mq) use ($search) {
                                $mq->where('name', 'like', "%$search%")
                                    ->orWhere('code', 'like', "%$search%");
                            })
                            ->orWhereHas('materialTransaction', function ($tq) use ($search) {
                                $tq->where('code', 'like', "%$search%")
                                    ->orWhere('supplier_name', 'like', "%$search%");
                            });
                    }
                });
            }

            foreach ($this->materialTransactionDetailTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $allowedSort = $this->materialTransactionDetailTable;

            $sortBy = in_array($request->sort_by, $allowedSort)
                ? $request->sort_by
                : 'id';

            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->per_page ?? 10;

            $data = $query->paginate($perPage);

            return $this->responseSuccess($data, 'Material Transaction Detail list retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error While retrieved Material Transaction Detail data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Material Transaction Detail list retrieved Failed', 500);
        }
    }

    /**
     * Store a new material transaction detail.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'material_transaction_id' => 'required|exists:material_transactions,id',
            'order_code' => 'required|unique:material_transaction_details,order_code',
            'material_id' => [
                'required',
                'exists:materials,id',
                Rule::unique('material_transaction_details')->where(function ($query) use ($request) {
                    return $query->where('material_transaction_id', $request->material_transaction_id);
                }),
            ],
            'qty' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ], [
            'material_id.unique' => 'This material already exists in this transaction.',
        ]);

        try {
            $data = DB::transaction(function () use ($validated) {
                $transaction = MaterialTransaction::findOrFail($validated['material_transaction_id']);

                if ($transaction->materialTransactionBillings()->where('is_paid', true)->exists()) {
                    throw new Exception('Cannot add items to a transaction that has already have payments.');
                }

                if (empty($validated['description'])) {
                    $typeState = $transaction->type == "purchase" ? "pembelian" : "penjualan";
                    $validated['description'] = "Pembayaran " . $typeState . " material ke " . $transaction->supplier_name;
                }

                return MaterialTransactionDetail::create($validated);
            });

            return $this->responseSuccess($data, 'Material Transaction Detail created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error while trying create Material Transaction Detail Data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Material Transaction Detail creation failed', 500);
        }
    }

    /**
     * Get material transaction detail.
     */
    public function show($id)
    {
        try {
            $data = MaterialTransactionDetail::with(['materialTransaction', 'material'])
                ->select($this->materialTransactionDetailTable)
                ->findOrFail($id);

            return $this->responseSuccess($data, 'Material Transaction Detail detail retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error While retrieved Material Transaction Detail data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Material Transaction Detail not found', 404);
        }
    }

    /**
     * Update a material transaction detail.
     */
    public function update(Request $request, $id)
    {
        $detail = MaterialTransactionDetail::findOrFail($id);

        $request->validate([
            'material_id' => [
                'sometimes',
                'required',
                'exists:materials,id',
                Rule::unique('material_transaction_details')->where(function ($query) use ($request, $detail) {
                    $materialTransactionId = $request->material_transaction_id ?? $detail->material_transaction_id;
                    return $query->where('material_transaction_id', $materialTransactionId);
                })->ignore($id),
            ],
            'order_code' => 'sometimes|unique:material_transaction_details,order_code,' . $id,
            'qty' => 'sometimes|required|integer|min:1',
            'price' => 'sometimes|required|numeric|min:0',
            'in_stock' => 'nullable|boolean',
            'is_forecast' => 'nullable|boolean',
            'description' => 'nullable|string',
        ], [
            'material_id.unique' => 'This material already exists in this transaction.',
        ]);

        try {
            $data = array_filter(
                $request->only(['material_transaction_id', 'material_id', 'qty', 'price', 'in_stock', 'is_forecast', 'description']),
                fn ($val) => ! is_null($val) && $val !== ''
            );

            if (empty($data)) {
                return $this->responseError(null, 'No data provided to update', 422);
            }

            DB::transaction(function () use ($data, $detail) {
                $materialTransactionId = $data['material_transaction_id'] ?? $detail->material_transaction_id;
                $materialId = $data['material_id'] ?? $detail->material_id;
                $qty = $data['qty'] ?? $detail->qty;
                $inStock = isset($data['in_stock']) ? $data['in_stock'] : $detail->in_stock;

                $transaction = MaterialTransaction::findOrFail($materialTransactionId);

                if ($transaction->materialTransactionBillings()->where('is_paid', true)->exists()) {
                    throw new Exception('Cannot update items in a transaction that has already have payments.');
                }

                // Validation for sales: check stock if in_stock is true
                if ($transaction->type == 'sales' && $inStock) {
                    $availableStock = $this->getAvailableStock($materialId, $detail->id);
                    if ($qty > $availableStock) {
                        throw new Exception("Insufficient stock. Available: {$availableStock}");
                    }
                }

                $detail->update($data);
            });

            return $this->responseSuccess($detail->fresh(), 'Material Transaction Detail updated successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying update Material Transaction Detail data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Material Transaction Detail update failed', 500);
        }
    }

    /**
     * Delete a material transaction detail.
     */
    public function destroy($id)
    {
        try {
            $data = MaterialTransactionDetail::findOrFail($id);

            DB::transaction(function () use ($data) {
                if ($data->materialTransaction->materialTransactionBillings()->where('is_paid', true)->exists()) {
                    throw new Exception('Cannot delete items from a transaction that has already have payments.');
                }
                
                $data->delete();
            });

            return $this->responseSuccess(null, 'Material Transaction Detail deleted successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying delete Material Transaction Detail data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Material Transaction Detail deletion failed', 500);
        }
    }

    /**
     * Calculate available stock for a material.
     */
    private function getAvailableStock($materialId, $excludeDetailId = null)
    {
        $purchased = MaterialTransactionDetail::where('material_id', $materialId)
            ->where('in_stock', true)
            ->whereHas('materialTransaction', function ($q) {
                $q->where('type', 'purchase');
            })
            ->sum('qty');

        $soldQuery = MaterialTransactionDetail::where('material_id', $materialId)
            ->where('in_stock', true)
            ->whereHas('materialTransaction', function ($q) {
                $q->where('type', 'sales');
            });

        if ($excludeDetailId) {
            $soldQuery->where('id', '!=', $excludeDetailId);
        }

        $sold = $soldQuery->sum('qty');

        return $purchased - $sold;
    }
}
