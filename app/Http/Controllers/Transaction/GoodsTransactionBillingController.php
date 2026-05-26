<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\GoodsTransaction;
use App\Models\GoodsTransactionBilling;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GoodsTransactionBillingController extends Controller
{
    use ResponseTrait;

    // projection
    protected $goodsTransactionBillingTable;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only('update');
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);

        $this->goodsTransactionBillingTable = [
            'id',
            'uuid',
            'goods_transaction_id',
            'cash_id',
            'amount',
            'payment_date',
            'description',
            'created_at',
        ];
    }

    /**
     * List all goods transaction billings.
     */
    public function index(Request $request)
    {
        $query = GoodsTransactionBilling::with(['goodsTransaction', 'cash']);

        $query->select($this->goodsTransactionBillingTable);

        try {
            if ($request->filled('search')) {
                $search = $request->search;
                $caseSensitive = $request->boolean('case_sensitive');

                $query->where(function ($q) use ($search, $caseSensitive) {
                    if ($caseSensitive) {
                        $q->where('description', 'LIKE BINARY', "%$search%")
                            ->orWhere('amount', 'LIKE BINARY', "%$search%");
                    } else {
                        $q->where('description', 'like', "%$search%")
                            ->orWhere('amount', 'like', "%$search%");
                    }
                });
            }

            foreach ($this->goodsTransactionBillingTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $allowedSort = $this->goodsTransactionBillingTable;

            $sortBy = in_array($request->sort_by, $allowedSort)
                ? $request->sort_by
                : 'id';

            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->per_page ?? 10;

            $data = $query->paginate($perPage);

            return $this->responseSuccess($data, 'Goods Transaction Billing list retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error While retrieved Goods Transaction Billing data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Goods Transaction Billing list retrieved Failed', 500);
        }
    }

    /**
     * Store a new goods transaction billing.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'goods_transaction_id' => 'required|exists:goods_transactions,id',
            'cash_id' => 'required|exists:cashes,id',
            'amount' => 'required|numeric|min:1',
            'payment_date' => 'required|date',
            'description' => 'nullable|string',
        ]);

        try {
            $transaction = GoodsTransaction::findOrFail($validated['goods_transaction_id']);

            if ($transaction->is_paid) {
                return $this->responseError('Transaction is already fully paid.', 'Access Denied', 403);
            }

            $totalAmount = $transaction->getTotalAmount();
            $totalPaidExisting = (int) $transaction->goodsTransactionBillings()->sum('amount');
            $newTotalPaid = $totalPaidExisting + (int) $validated['amount'];

            if ($newTotalPaid > $totalAmount) {
                return $this->responseError('Total payment (' . number_format($newTotalPaid) . ') exceeds total transaction amount (' . number_format($totalAmount) . ')', 'Validation Error', 422);
            }

            $data = DB::transaction(function () use ($validated, $transaction, $newTotalPaid, $totalAmount) {
                // Set is_paid to true for the billing record as it's a payment
                $data = GoodsTransactionBilling::create(array_merge($validated, ['is_paid' => true]));
                
                $isFullyPaid = $newTotalPaid >= $totalAmount;
                $transaction->update(['is_paid' => $isFullyPaid]);

                if ($isFullyPaid) {
                    $detailStatus = [
                        'is_forecast' => false,
                        'in_stock' => true
                    ];

                    $transaction->goodsTransactionDetails()->update($detailStatus);
                }

                return $data;
            });

            $responseData = array_merge($data->toArray(), [
                'remaining_payment' => $totalAmount - $newTotalPaid,
                'total' => $totalAmount - (int) $validated['amount']
            ]);

            return $this->responseSuccess($responseData, 'Goods Transaction Billing created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error while trying create Goods Transaction Billing Data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Goods Transaction Billing creation failed', 500);
        }
    }

    /**
     * Get goods transaction billing detail.
     */
    public function show($id)
    {
        try {
            $data = GoodsTransactionBilling::with(['goodsTransaction', 'cash'])
                ->select($this->goodsTransactionBillingTable)
                ->findOrFail($id);

            return $this->responseSuccess($data, 'Goods Transaction Billing detail retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error While retrieved Goods Transaction Billing data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Goods Transaction Billing not found', 404);
        }
    }

    /**
     * Update a goods transaction billing.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'goods_transaction_id' => 'sometimes|required|exists:goods_transactions,id',
            'cash_id' => 'sometimes|required|exists:cashes,id',
            'amount' => 'sometimes|required|numeric|min:1',
            'payment_date' => 'sometimes|required|date',
            'description' => 'nullable|string',
        ]);

        try {
            $billing = GoodsTransactionBilling::findOrFail($id);
            $transactionId = $request->goods_transaction_id ?? $billing->goods_transaction_id;
            $transaction = GoodsTransaction::findOrFail($transactionId);

            if ($transaction->is_paid) {
                return $this->responseError('Transaction is already fully paid.', 'Access Denied', 403);
            }

            $totalAmount = $transaction->getTotalAmount();
            $amountToApply = $request->amount ?? $billing->amount;

            $totalPaidOthers = (int) $transaction->goodsTransactionBillings()
                ->where('id', '!=', $id)
                ->sum('amount');

            $newTotalPaid = $totalPaidOthers + (int) $amountToApply;

            if ($newTotalPaid > $totalAmount) {
                return $this->responseError('Total payment (' . number_format($newTotalPaid) . ') exceeds total transaction amount (' . number_format($totalAmount) . ')', 'Validation Error', 422);
            }

            $data = array_filter(
                $request->only(['goods_transaction_id', 'cash_id', 'amount', 'payment_date', 'description']),
                fn ($val) => ! is_null($val) && $val !== ''
            );

            DB::transaction(function () use ($data, $billing, $transaction, $newTotalPaid, $totalAmount) {
                $billing->update($data);
                $transaction->update(['is_paid' => $newTotalPaid >= $totalAmount]);
            });

            $responseData = array_merge($billing->fresh()->toArray(), [
                'remaining_payment' => $totalAmount - $newTotalPaid,
                'total' => $totalAmount - (int) $amountToApply
            ]);

            return $this->responseSuccess($responseData, 'Goods Transaction Billing updated successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying update Goods Transaction Billing data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Goods Transaction Billing update failed', 500);
        }
    }

    /**
     * Delete a goods transaction billing.
     */
    public function destroy($id)
    {
        try {
            $data = GoodsTransactionBilling::findOrFail($id);
            
            DB::transaction(function () use ($data) {
                $transaction = $data->goodsTransaction;
                $data->delete();
                
                $transaction->update(['is_paid' => false]);

                $detailStatus = [
                    'is_forecast' => true,
                    'in_stock' => false
                ];

                $transaction->goodsTransactionDetails()->update($detailStatus);
            });

            return $this->responseSuccess(null, 'Goods Transaction Billing deleted successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying delete Goods Transaction Billing data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Goods Transaction Billing deletion failed', 500);
        }
    }
}
