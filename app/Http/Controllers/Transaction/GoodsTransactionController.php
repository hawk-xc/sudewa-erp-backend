<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\GoodsTransaction;
use App\Traits\FileTrait;
use App\Traits\ResponseTrait;
use App\Traits\GoodsTransactionTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GoodsTransactionController extends Controller
{
    use ResponseTrait, GoodsTransactionTrait, FileTrait;

    // projection
    protected $goodsTransactionTable;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only(['update', 'updateState', 'uploadInvoice']);
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);

        $this->goodsTransactionTable = [
            'id',
            'uuid',
            'code',
            'type',
            'warehouse_id',
            'stock_state',
            'is_refunded',
            'supplier_name',
            'is_paid',
            'transaction_date',
            'invoice_file',
            'description',
            'created_at',
        ];
    }

    /**
     * List all goods transactions.
     */
    public function index(Request $request)
    {
        $query = GoodsTransaction::query();
        
        if ($request->filled('type')) {
            if ($request->type == 'purchase') {
                $query->where('type', 'purchase');
            } else {
                $query->where('type', 'sales');
            }
        } 

        $query->select($this->goodsTransactionTable);

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

            foreach ($this->goodsTransactionTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $allowedSort = $this->goodsTransactionTable;

            $sortBy = in_array($request->sort_by, $allowedSort)
                ? $request->sort_by
                : 'id';

            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $query->with(['goodsTransactionDetails', 'goodsTransactionBillings']);

            $perPage = $request->per_page ?? 10;

            $data = $query->paginate($perPage)
                ->through(function ($item) {
                    $item->makeHidden('total_brutto');

                    $item->unsetRelation('goodsTransactionDetails');
                    $item->unsetRelation('goodsTransactionBillings');

                    return $item;
                });

            return $this->responseSuccess($data, 'Goods Transaction list retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error While retrieved Goods Transaction data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Goods Transaction list retrieved Failed', 500);
        }
    }

    /**
     * Store a new goods transaction.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:purchase,sales',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'supplier_name' => 'nullable|string|max:255',
            'transaction_date' => 'required|date',
            'description' => 'nullable|string',
        ]);

        try {
            $data = DB::transaction(function () use ($request, $validated) {
                $validated['code'] = $this->generateMaterialCode($validated['type']);
                $typeState = $request->type == "purchase" ? "pembelian" : "penjualan";
                
                if (empty($validated['description'])) {
                    $name = $validated['supplier_name'] ?? 'supplier';
                    $validated['description'] = "Pembayaran " . $typeState . " material ke " . $name;
                }

                return GoodsTransaction::create($validated);
            });

            return $this->responseSuccess($data, 'Goods Transaction created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error while trying create Goods Transaction Data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Goods Transaction creation failed', 500);
        }
    }

    /**
     * Get goods transaction details.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $data = GoodsTransaction::with(['goodsTransactionDetails.material', 'goodsTransactionBillings.cash'])
                ->select($this->goodsTransactionTable)
                ->findOrFail($id);

            $data->makeHidden('total_brutto');
            
            return $this->responseSuccess($data, 'Goods Transaction detail retrieved successfully', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            return $this->responseError(null, 'Goods Transaction not found', 404);
        } catch (Exception $err) {
            Log::error('Error while retrieving Goods Transaction data: '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'An unexpected error occurred', 500);
        }
    }

    /**
     * Update a goods transaction.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'warehouse_id' => 'sometimes|exists:warehouses,id',
            'person_id' => 'sometimes|exists:persons,id',
            'stock_state' => 'sometimes|string',
            'supplier_name' => 'sometimes|nullable|string|max:255',
            'transaction_date' => 'sometimes|required|date',
            'description' => 'nullable|string',
        ]);

        try {
            $transaction = GoodsTransaction::findOrFail($id);
            
            $data = array_filter(
                $request->only(['warehouse_id', 'person_id', 'stock_state', 'supplier_name', 'transaction_date', 'description']),
                fn ($val) => ! is_null($val) && $val !== ''
            );

            if (empty($data)) {
                return $this->responseError(null, 'No data provided to update', 422);
            }

            DB::transaction(function () use ($data, $transaction) {
                $transaction->update($data);
            });

            return $this->responseSuccess($transaction->fresh(), 'Goods Transaction updated successfully', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            return $this->responseError(null, 'Goods Transaction not found', 404);
        } catch (Exception $err) {
            Log::error('Error while updating Goods Transaction data: '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Goods Transaction update failed', 500);
        }
    }

    /**
     * Delete a goods transaction.
     */
    public function destroy($id)
    {
        try {
            $data = GoodsTransaction::findOrFail($id);

            DB::transaction(function () use ($data) {
                $data->delete();
            });

            return $this->responseSuccess(null, 'Goods Transaction deleted successfully', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            return $this->responseError(null, 'Goods Transaction not found', 404);
        } catch (Exception $err) {
            Log::error('Error while deleting Goods Transaction data: '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Goods Transaction deletion failed', 500);
        }
    }

    /**
     * Update goods transaction stock state.
     */
    public function updateState(Request $request, string $id)
    {
        $purchaseStates = ['draft', 'cancel', 'rejected', 'prepare', 'inbound_purchase_order', 'inbound_incoming_goods', 'inbound_receipt'];
        $salesStates = ['draft', 'cancel', 'prepare', 'outbound_reserved', 'outbound_in_transit', 'outbound_delivered'];

        try {
            $transaction = GoodsTransaction::with('goodsTransactionDetails')->findOrFail((int) $id);

            if (is_string($request->goods_transaction_details)) {
                $request->merge(['goods_transaction_details' => json_decode($request->goods_transaction_details, true)]);
            }

            $validated = $request->validate([
                'stock_state' => 'required|string',
                'goods_transaction_details' => 'nullable|array',
                'goods_transaction_details.*' => 'integer|distinct|exists:goods_transaction_details,id',
            ]);

            $allowedStates = $transaction->type === 'purchase' ? $purchaseStates : $salesStates;

            if (! in_array($validated['stock_state'], $allowedStates)) {
                return $this->responseError(null, 'Invalid stock state for this transaction type', 422);
            }

            if ($transaction->goodsTransactionDetails->isEmpty()) {
                return $this->responseError(null, 'No transaction items found', 422);
            }

            if (! empty($validated['goods_transaction_details'])) {
                $detailIds = array_unique($validated['goods_transaction_details']);
            } else {
                $detailIds = $transaction->goodsTransactionDetails->pluck('id')->toArray();
            }

            $validDetails = \App\Models\GoodsTransactionDetail::whereIn('id', $detailIds)
                ->where('goods_transaction_id', $transaction->id)
                ->get();

            if ($validDetails->isEmpty()) {
                return $this->responseError(null, 'Selected details not found in this transaction', 422);
            }

            DB::transaction(function () use ($transaction, $validated, $validDetails) {

                $transaction->update(['stock_state' => $validated['stock_state']]);

                switch ($validated['stock_state']) {
                    case 'inbound_incoming_goods':
                        \App\Models\GoodsTransactionDetail::whereIn('id', $validDetails->pluck('id'))
                            ->update([
                                'is_forecast' => true,
                            ]);
                        break;

                    case 'outbound_delivered':
                        \App\Models\GoodsTransactionDetail::whereIn('id', $validDetails->pluck('id'))
                            ->update([
                                'is_forecast' => true,
                            ]);
                        break;
                }
            });

            return $this->responseSuccess(
                $transaction->fresh()->load([
                    'goodsTransactionDetails'
                ]),
                'Goods Transaction state updated successfully',
                200
            );

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->responseError($e->errors(), 'Validation failed', 422);
        } catch (Exception $err) {
            Log::error('Error updating Goods Transaction state: '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Goods Transaction state update failed', 500);
        }
    }

    public function uploadInvoice(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'invoice_file' => 'required|file|mimes:pdf,doc,docx|max:2048',
        ]);

        try {
            $transaction = GoodsTransaction::findOrFail((int) $id);

            $path = $this->updateFile(
                $request->file('invoice_file'),
                $transaction->invoice_file,
                'invoices'
            );

            $transaction->update(['invoice_file' => $path]);

            return $this->responseSuccess(null, 'Invoice uploaded successfully', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            return $this->responseError(null, 'Goods Transaction not found', 404);
        } catch (Exception $err) {
            Log::error('Error while uploading invoice: '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Invoice upload failed', 500);
        }
    }
}
