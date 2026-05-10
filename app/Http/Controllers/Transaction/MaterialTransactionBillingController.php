<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\MaterialTransaction;
use App\Models\MaterialTransactionBilling;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MaterialTransactionBillingController extends Controller
{
    use ResponseTrait;

    // projection
    protected $materialTransactionBillingTable;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only('update');
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);

        $this->materialTransactionBillingTable = [
            'id',
            'uuid',
            'material_transaction_id',
            'cash_id',
            'amount',
            'payment_date',
            'description',
            'created_at',
        ];
    }

    /**
     * List all material transaction billings.
     */
    public function index(Request $request)
    {
        $query = MaterialTransactionBilling::with(['materialTransaction', 'cash']);

        $query->select($this->materialTransactionBillingTable);

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

            foreach ($this->materialTransactionBillingTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $allowedSort = $this->materialTransactionBillingTable;

            $sortBy = in_array($request->sort_by, $allowedSort)
                ? $request->sort_by
                : 'id';

            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->per_page ?? 10;

            $data = $query->paginate($perPage);

            return $this->responseSuccess($data, 'Material Transaction Billing list retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error While retrieved Material Transaction Billing data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Material Transaction Billing list retrieved Failed', 500);
        }
    }

    /**
     * Store a new material transaction billing.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'material_transaction_id' => 'required|exists:material_transactions,id',
            'cash_id' => 'required|exists:cashes,id',
            'amount' => 'required|numeric|min:1',
            'payment_date' => 'required|date',
            'description' => 'nullable|string',
        ]);

        try {
            $transaction = MaterialTransaction::findOrFail($validated['material_transaction_id']);

            if ($transaction->is_paid) {
                return $this->responseError('Transaction is already fully paid.', 'Access Denied', 403);
            }

            $totalAmount = $transaction->getTotalAmount();
            $totalPaidExisting = (int) $transaction->materialTransactionBillings()->sum('amount');
            $newTotalPaid = $totalPaidExisting + (int) $validated['amount'];

            if ($newTotalPaid > $totalAmount) {
                return $this->responseError('Total payment (' . number_format($newTotalPaid) . ') exceeds total transaction amount (' . number_format($totalAmount) . ')', 'Validation Error', 422);
            }

            $data = DB::transaction(function () use ($validated, $transaction, $newTotalPaid, $totalAmount) {
                // Set is_paid to true for the billing record as it's a payment
                $data = MaterialTransactionBilling::create(array_merge($validated, ['is_paid' => true]));
                
                $isFullyPaid = $newTotalPaid >= $totalAmount;
                $transaction->update(['is_paid' => $isFullyPaid]);

                if ($isFullyPaid) {
                    $detailStatus = [
                        'is_forecast' => false,
                        'in_stock' => true
                    ];

                    $transaction->materialTransactionDetails()->update($detailStatus);
                }

                return $data;
            });

            $responseData = array_merge($data->toArray(), [
                'remaining_payment' => $totalAmount - $newTotalPaid,
                'total' => $totalAmount - (int) $validated['amount']
            ]);

            return $this->responseSuccess($responseData, 'Material Transaction Billing created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error while trying create Material Transaction Billing Data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Material Transaction Billing creation failed', 500);
        }
    }

    /**
     * Get material transaction billing detail.
     */
    public function show($id)
    {
        try {
            $data = MaterialTransactionBilling::with(['materialTransaction', 'cash'])
                ->select($this->materialTransactionBillingTable)
                ->findOrFail($id);

            return $this->responseSuccess($data, 'Material Transaction Billing detail retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error While retrieved Material Transaction Billing data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Material Transaction Billing not found', 404);
        }
    }

    /**
     * Update a material transaction billing.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'material_transaction_id' => 'sometimes|required|exists:material_transactions,id',
            'cash_id' => 'sometimes|required|exists:cashes,id',
            'amount' => 'sometimes|required|numeric|min:1',
            'payment_date' => 'sometimes|required|date',
            'description' => 'nullable|string',
        ]);

        try {
            $billing = MaterialTransactionBilling::findOrFail($id);
            $transactionId = $request->material_transaction_id ?? $billing->material_transaction_id;
            $transaction = MaterialTransaction::findOrFail($transactionId);

            if ($transaction->is_paid) {
                return $this->responseError('Transaction is already fully paid.', 'Access Denied', 403);
            }

            $totalAmount = $transaction->getTotalAmount();
            $amountToApply = $request->amount ?? $billing->amount;

            $totalPaidOthers = (int) $transaction->materialTransactionBillings()
                ->where('id', '!=', $id)
                ->sum('amount');

            $newTotalPaid = $totalPaidOthers + (int) $amountToApply;

            if ($newTotalPaid > $totalAmount) {
                return $this->responseError('Total payment (' . number_format($newTotalPaid) . ') exceeds total transaction amount (' . number_format($totalAmount) . ')', 'Validation Error', 422);
            }

            $data = array_filter(
                $request->only(['material_transaction_id', 'cash_id', 'amount', 'payment_date', 'description']),
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

            return $this->responseSuccess($responseData, 'Material Transaction Billing updated successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying update Material Transaction Billing data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Material Transaction Billing update failed', 500);
        }
    }

    /**
     * Delete a material transaction billing.
     */
    public function destroy($id)
    {
        try {
            $data = MaterialTransactionBilling::findOrFail($id);
            
            DB::transaction(function () use ($data) {
                $transaction = $data->materialTransaction;
                $data->delete();
                
                $transaction->update(['is_paid' => false]);

                $detailStatus = [
                    'is_forecast' => true,
                    'in_stock' => false
                ];

                $transaction->materialTransactionDetails()->update($detailStatus);
            });

            return $this->responseSuccess(null, 'Material Transaction Billing deleted successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying delete Material Transaction Billing data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Material Transaction Billing deletion failed', 500);
        }
    }
}
