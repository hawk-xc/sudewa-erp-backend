<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\GoodsTransactionBilling;
use App\Models\GoodsTransactionBillingPayment;
use App\Rules\RightCashRule;
use App\Traits\GlobalCodeNumberTrait;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GoodsTransactionBillingPaymentController extends Controller
{
    use ResponseTrait, GlobalCodeNumberTrait;

    protected array $goodsTransactionBillingPaymentTable;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only('update');
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);

        $this->goodsTransactionBillingPaymentTable = [
            'id',
            'uuid',
            'goods_transaction_billing_id',
            'cash_id',
            'amount',
            'transaction_date',
            'description',
            'created_at',
        ];
    }

    /**
     * List all goods transaction billing payments.
     */
    public function index(Request $request)
    {
        $query = GoodsTransactionBillingPayment::with(['goodsTransactionBilling', 'cash']);

        $query->select($this->goodsTransactionBillingPaymentTable);

        try {
            if ($request->filled('search')) {
                $search = $request->search;
                $caseSensitive = $request->boolean('case_sensitive');

                $query->where(function ($q) use ($search, $caseSensitive) {
                    if ($caseSensitive) {
                        $q->where('code', 'LIKE BINARY', "%$search%")
                            ->orWhere('description', 'LIKE BINARY', "%$search%");
                    } else {
                        $q->where('code', 'like', "%$search%")
                            ->orWhere('description', 'like', "%$search%");
                    }
                });
            }

            foreach ($this->goodsTransactionBillingPaymentTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $allowedSort = $this->goodsTransactionBillingPaymentTable;

            $sortBy = in_array($request->sort_by, $allowedSort)
                ? $request->sort_by
                : 'id';

            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->per_page ?? 10;

            $data = $query->paginate($perPage);

            return $this->responseSuccess($data, 'Goods Transaction Billing Payment list retrieved successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error While retrieved Goods Transaction Billing Payment data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Goods Transaction Billing Payment list retrieved Failed', 500);
        }
    }

    /**
     * Store a new goods transaction billing payment.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'goods_transaction_billing_id' => 'required|exists:goods_transaction_billings,id',
            'cash_id' => [
                'required',
                'exists:cashes,id',
                new RightCashRule(fn () => GoodsTransactionBilling::find($request->goods_transaction_billing_id)?->goodsTransaction?->company_id),
            ],
            'amount' => 'required|numeric|min:1',
            'transaction_date' => 'required|date',
            'description' => 'nullable|string',
        ]);

        try {
            $billing = GoodsTransactionBilling::findOrFail($validated['goods_transaction_billing_id']);

            if ($billing->is_paid) {
                return $this->responseError('Billing is already fully paid.', 'Access Denied', 403);
            }

            $totalAmount = $billing->grand_total;
            $totalPaidExisting = (int) $billing->payments()->sum('amount');
            $newTotalPaid = $totalPaidExisting + (int) $validated['amount'];

            if ($newTotalPaid > $totalAmount) {
                return $this->responseError('Total payment (' . number_format($newTotalPaid) . ') exceeds total billing amount (' . number_format($totalAmount) . ')', 'Validation Error', 422);
            }

            $data = DB::transaction(function () use ($validated, $billing, $newTotalPaid, $totalAmount) {
                $code = $this->code('wjt', 'goods_transaction_billing_payment'); // Generate PAY-WJT/... code
                
                $payment = GoodsTransactionBillingPayment::create(array_merge($validated, ['code' => $code]));
                
                $isFullyPaid = $newTotalPaid >= $totalAmount;
                if ($isFullyPaid) {
                    $billing->update(['is_paid' => true]);
                    
                    $transaction = $billing->goodsTransaction;
                    if ($transaction) {
                        $transaction->update(['is_paid' => true]);
                        $transaction->goodsTransactionDetails()->update([
                            'is_forecast' => false,
                            'in_stock' => true
                        ]);
                    }
                }

                return $payment;
            });

            $responseData = array_merge($data->toArray(), [
                'remaining_payment' => $totalAmount - $newTotalPaid,
                'grand_total' => $billing->grand_total,
            ]);

            return $this->responseSuccess($responseData, 'Goods Transaction Billing Payment created successfully', 201);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while trying create Goods Transaction Billing Payment Data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Goods Transaction Billing Payment creation failed', 500);
        }
    }

    /**
     * Get goods transaction billing payment detail.
     */
    public function show(string $id)
    {
        try {
            $data = GoodsTransactionBillingPayment::with(['goodsTransactionBilling', 'cash'])
                ->select($this->goodsTransactionBillingPaymentTable)
                ->findOrFail($id);

            return $this->responseSuccess($data, 'Goods Transaction Billing Payment detail retrieved successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error While retrieved Goods Transaction Billing Payment data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Goods Transaction Billing Payment not found', 404);
        }
    }

    /**
     * Update a goods transaction billing payment.
    public function update(Request $request, string $id)
    {
        try {
            $payment = GoodsTransactionBillingPayment::findOrFail($id);
            $billing = $payment->goodsTransactionBilling;

            $request->validate([
                'cash_id' => [
                    'sometimes',
                    'required',
                    'exists:cashes,id',
                    new RightCashRule(fn () => \App\Models\GoodsTransactionBilling::find($payment->goods_transaction_billing_id)?->goodsTransaction?->company_id),
                ],
                'amount' => 'sometimes|required|numeric|min:1',
                'transaction_date' => 'sometimes|required|date',
                'description' => 'nullable|string',
            ]);

            $totalAmount = $billing->grand_total;
            $amountToApply = $request->amount ?? $payment->amount;

            $totalPaidOthers = (int) $billing->payments()
                ->where('id', '!=', $id)
                ->sum('amount');

            $newTotalPaid = $totalPaidOthers + (int) $amountToApply;

            if ($newTotalPaid > $totalAmount) {
                return $this->responseError('Total payment (' . number_format($newTotalPaid) . ') exceeds total billing amount (' . number_format($totalAmount) . ')', 'Validation Error', 422);
            }

            $data = array_filter(
                $request->only(['cash_id', 'amount', 'transaction_date', 'description']),
                fn ($val) => ! is_null($val) && $val !== ''
            );

            DB::transaction(function () use ($data, $payment, $billing, $newTotalPaid, $totalAmount) {
                $payment->update($data);
                
                $isFullyPaid = $newTotalPaid >= $totalAmount;
                $billing->update(['is_paid' => $isFullyPaid]);
                
                $transaction = $billing->goodsTransaction;
                if ($transaction) {
                    $transaction->update(['is_paid' => $isFullyPaid]);
                    if ($isFullyPaid) {
                        $transaction->goodsTransactionDetails()->update([
                            'is_forecast' => false,
                            'in_stock' => true
                        ]);
                    } else {
                        $transaction->goodsTransactionDetails()->update([
                            'is_forecast' => true,
                            'in_stock' => false
                        ]);
                    }
                }
            });

            $responseData = array_merge($payment->fresh()->toArray(), [
                'remaining_payment' => $totalAmount - $newTotalPaid,
                'total' => $totalAmount - (int) $amountToApply
            ]);

            return $this->responseSuccess($responseData, 'Goods Transaction Billing Payment updated successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while trying update Goods Transaction Billing Payment data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Goods Transaction Billing Payment update failed', 500);
        }
    }

    /**
     * Delete a goods transaction billing payment.
     */
    public function destroy($id)
    {
        try {
            $data = GoodsTransactionBillingPayment::findOrFail($id);
            
            DB::transaction(function () use ($data) {
                $billing = $data->goodsTransactionBilling;
                $data->delete();
                
                $billing->update(['is_paid' => false]);
                
                $transaction = $billing->goodsTransaction;
                if ($transaction) {
                    $transaction->update(['is_paid' => false]);
                    $transaction->goodsTransactionDetails()->update([
                        'is_forecast' => true,
                        'in_stock' => false
                    ]);
                }
            });

            return $this->responseSuccess(null, 'Goods Transaction Billing Payment deleted successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while trying delete Goods Transaction Billing Payment data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Goods Transaction Billing Payment deletion failed', 500);
        }
    }
}
