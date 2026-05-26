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

class GoodsTransactionDetailController extends Controller
{
    use ResponseTrait;

    // projection
    protected $goodsTransactionDetailTable;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only('update');
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);

        $this->goodsTransactionDetailTable = [
            'id',
            'uuid',
            'order_code',
            'goods_transaction_id',
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
     * List all goods transaction details.
     */
    public function index(Request $request)
    {   
        $query = GoodsTransactionDetail::with(['goodsTransaction', 'material']);

        $query->select($this->goodsTransactionDetailTable);

        try {
            if ($request->filled('type')) {
                $query->whereHas('goodsTransaction', function ($q) use ($request) {
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
                            ->orWhereHas('goodsTransaction', function ($tq) use ($search) {
                                $tq->where('code', 'LIKE BINARY', "%$search%")
                                    ->orWhere('supplier_name', 'LIKE BINARY', "%$search%");
                            });
                    } else {
                        $q->where('description', 'like', "%$search%")
                            ->orWhereHas('material', function ($mq) use ($search) {
                                $mq->where('name', 'like', "%$search%")
                                    ->orWhere('code', 'like', "%$search%");
                            })
                            ->orWhereHas('goodsTransaction', function ($tq) use ($search) {
                                $tq->where('code', 'like', "%$search%")
                                    ->orWhere('supplier_name', 'like', "%$search%");
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
        } catch (Exception $err) {
            Log::error('Error While retrieved Goods Transaction Detail data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Goods Transaction Detail list retrieved Failed', 500);
        }
    }

    /**
     * Store a new goods transaction detail.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'goods_transaction_id' => 'required|exists:goods_transactions,id',
            'order_code' => 'required|unique:goods_transaction_details,order_code',
            'material_id' => [
                'required',
                'exists:materials,id',
                Rule::unique('goods_transaction_details')->where(function ($query) use ($request) {
                    return $query->where('goods_transaction_id', $request->goods_transaction_id);
                }),
            ],
            'qty' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ], [
            'material_id.unique' => 'This material already exists in this transaction.',
        ]);

        try {
            $transaction = GoodsTransaction::findOrFail($validated['goods_transaction_id']);

            if ($transaction->type == 'sales') {
                $availableStock = $this->getAvailableStock($request->material_id);

                if ($request->qty > $availableStock) {
                    return $this->responseError(null, 'Material stock qty not enough of capacity!', 422);
                }
            }

            if ($transaction->goodsTransactionBillings()->where('is_paid', true)->exists()) {
                return $this->responseError(null, 'Cannot add items to a transaction that has already have payments!', 422);
            }

            $data = DB::transaction(function () use ($request, $validated, $transaction) {
                if (empty($validated['description'])) {
                    $typeState = $transaction->type == "purchase" ? "pembelian" : "penjualan";
                    $validated['description'] = "Pembayaran " . $typeState . " material ke " . $transaction->supplier_name;
                }

                return GoodsTransactionDetail::create($validated);
            });

            return $this->responseSuccess($data, 'Goods Transaction Detail created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error while trying create Goods Transaction Detail Data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Goods Transaction Detail creation failed', 500);
        }
    }

    /**
     * Get goods transaction detail.
     */
    public function show($id)
    {
        try {
            $data = GoodsTransactionDetail::with(['goodsTransaction', 'material'])
                ->select($this->goodsTransactionDetailTable)
                ->findOrFail($id);

            return $this->responseSuccess($data, 'Goods Transaction Detail detail retrieved successfully', 200);
        } catch (Exception $err) {
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
                'required',
                'exists:materials,id',
                Rule::unique('goods_transaction_details')->where(function ($query) use ($request, $detail) {
                    $goodsTransactionId = $request->goods_transaction_id ?? $detail->goods_transaction_id;
                    return $query->where('goods_transaction_id', $goodsTransactionId);
                })->ignore($id),
            ],
            'order_code' => 'sometimes|unique:goods_transaction_details,order_code,' . $id,
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
                $request->only(['goods_transaction_id', 'material_id', 'qty', 'price', 'in_stock', 'is_forecast', 'description']),
                fn ($val) => ! is_null($val) && $val !== ''
            );

            if (empty($data)) {
                return $this->responseError(null, 'No data provided to update', 422);
            }

            $goodsTransactionId = $data['goods_transaction_id'] ?? $detail->goods_transaction_id;
            $materialId = $data['material_id'] ?? $detail->material_id;
            $qty = $data['qty'] ?? $detail->qty;

            $transaction = GoodsTransaction::findOrFail($goodsTransactionId);

            if ($transaction->goodsTransactionBillings()->where('is_paid', true)->exists()) {
                return $this->responseError(null, 'Cannot update items in a transaction that has already have payments.', 422);
            }

            // Validation for sales: check stock
            if ($transaction->type == 'sales') {
                $availableStock = $this->getAvailableStock($materialId, $detail->id);
                if ($qty > $availableStock) {
                    return $this->responseError(null, "Insufficient stock. Available: {$availableStock}", 422);
                }
            }

            DB::transaction(function () use ($data, $detail) {
                $detail->update($data);
            });

            return $this->responseSuccess($detail->fresh(), 'Goods Transaction Detail updated successfully', 200);
        } catch (Exception $err) {
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

            if ($data->goodsTransaction->goodsTransactionBillings()->where('is_paid', true)->exists()) {
                return $this->responseError(null, 'Cannot delete items from a transaction that has already have payments.', 422);
            }

            DB::transaction(function () use ($data) {
                $data->delete();
            });

            return $this->responseSuccess(null, 'Goods Transaction Detail deleted successfully', 200);
        } catch (Exception $err) {
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
                $q->where('type', 'purchase');
            })
            ->sum('qty');

        $soldQuery = GoodsTransactionDetail::where('material_id', $materialId)
            ->whereHas('goodsTransaction', function ($q) {
                $q->where('type', 'sales');
            });

        if ($excludeDetailId) {
            $soldQuery->where('id', '!=', $excludeDetailId);
        }

        $sold = $soldQuery->sum('qty');

        return $purchased - $sold;
    }
}
