<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\FinanceRefund;
use App\Models\UnitTransactionRefund;
use App\Models\UnitTransactionRefundPayment;
use App\Traits\GlobalCodeNumberTrait;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UnitTransactionRefundPaymentController extends Controller
{
    use ResponseTrait, GlobalCodeNumberTrait;

    protected $paymentTable;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only('update');
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);

        $this->paymentTable = [
            'id',
            'uuid',
            'unit_transaction_refund_id',
            'code',
            'amount',
            'payment_date',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * List unit transaction refund payments.
     */
    public function index(Request $request)
    {
        try {
            $query = UnitTransactionRefundPayment::query();

            $query->select($this->paymentTable)
                ->with([
                    'unitTransactionRefund:id,uuid,code,refund_amount',
                ]);

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%$search%")
                        ->orWhereHas('unitTransactionRefund', function ($q) use ($search) {
                            $q->where('code', 'like', "%$search%");
                        });
                });
            }

            $sortBy = in_array($request->sort_by, $this->paymentTable) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
            $query->orderBy($sortBy, $sortOrder);

            $data = $query->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'Unit transaction refund payments retrieved successfully', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error('Error while retrieving unit transaction refund payments: ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Failed to retrieve unit transaction refund payments', 500);
        }
    }

    /**
     * Store a new unit transaction refund payment.
     */
    public function store(Request $request)
    {
        $request->validate([
            'unit_transaction_refund_id' => 'required|exists:unit_transaction_refunds,id',
            'amount' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
        ]);

        $refund = UnitTransactionRefund::with('unitTransactionRefundPayments')->findOrFail($request->unit_transaction_refund_id);
        $totalPaid = $refund->unitTransactionRefundPayments->sum('amount');
        $paymentRemaining = (int) $refund->refund_amount - $totalPaid;

        if ($paymentRemaining <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'amount' => ['This refund has already been fully paid.']
            ]);
        }

        if ($request->amount > $paymentRemaining) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'amount' => ["The payment amount cannot exceed the remaining payment of {$paymentRemaining}."]
            ]);
        }

        try {
            $payment = DB::transaction(function () use ($request) {
                $prefix = 'PAY-REF';
                $date = now()->format('Ymd');
                $lastPayment = UnitTransactionRefundPayment::whereDate('created_at', today())
                    ->lockForUpdate()
                    ->orderByDesc('id')
                    ->first();
                $lastNumber = $lastPayment ? (int) substr($lastPayment->code, -4) : 0;
                $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
                $code = "{$prefix}-{$date}-{$newNumber}";

                FinanceRefund::firstOrCreate([
                    'unit_transaction_refund_id' => $request->unit_transaction_refund_id
                ], [
                    'status' => 'waiting'
                ]);

                return UnitTransactionRefundPayment::create([
                    'unit_transaction_refund_id' => $request->unit_transaction_refund_id,
                    'code' => $code,
                    'amount' => $request->amount,
                    'payment_date' => $request->payment_date,
                ]);
            });

            $payment->load('unitTransactionRefund.unitTransactionRefundPayments');
            $refund = $payment->unitTransactionRefund;
            if ($refund) {
                $totalPaid = $refund->unitTransactionRefundPayments->sum('amount');
                $payment->payment_remaining = (int) $refund->refund_amount - $totalPaid;
            }

            return $this->responseSuccess($payment, 'Unit transaction refund payment created successfully', 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error('Error while creating unit transaction refund payment: ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Failed to create unit transaction refund payment', 500);
        }
    }

    /**
     * Show details of a unit transaction refund payment.
     */
    public function show(string $id)
    {
        try {
            $payment = UnitTransactionRefundPayment::with([
                'unitTransactionRefund:id,uuid,code,refund_amount',
            ])->findOrFail($id);

            return $this->responseSuccess($payment, 'Unit transaction refund payment retrieved successfully', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error('Error while retrieving unit transaction refund payment: ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Failed to retrieve unit transaction refund payment', 404);
        }
    }

    /**
     * Update a unit transaction refund payment.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'unit_transaction_refund_id' => 'nullable|exists:unit_transaction_refunds,id',
            'amount' => 'nullable|numeric|min:0',
            'payment_date' => 'nullable|date',
        ]);

        $payment = UnitTransactionRefundPayment::findOrFail($id);
        $refundId = $request->unit_transaction_refund_id ?? $payment->unit_transaction_refund_id;

        $refund = UnitTransactionRefund::with('unitTransactionRefundPayments')->findOrFail($refundId);
        $totalPaid = $refund->unitTransactionRefundPayments->sum('amount');

        if ($refundId == $payment->unit_transaction_refund_id) {
            $totalPaid -= $payment->amount;
        }

        $paymentRemaining = (int) $refund->refund_amount - $totalPaid;

        if ($request->filled('amount')) {
            if ($paymentRemaining <= 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'amount' => ['This refund has already been fully paid.']
                ]);
            }

            if ($request->amount > $paymentRemaining) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'amount' => ["The payment amount cannot exceed the remaining payment of {$paymentRemaining}."]
                ]);
            }
        }

        try {
            $payment = DB::transaction(function () use ($request, $id) {
                $payment = UnitTransactionRefundPayment::findOrFail($id);

                $data = array_filter($request->only([
                    'unit_transaction_refund_id',
                    'amount',
                    'payment_date'
                ]), fn ($value) => $value !== '' && $value !== null);

                $payment->update($data);

                return $payment;
            });

            $payment->load('unitTransactionRefund.unitTransactionRefundPayments');
            $refund = $payment->unitTransactionRefund;
            if ($refund) {
                $totalPaid = $refund->unitTransactionRefundPayments->sum('amount');
                $payment->payment_remaining = (int) $refund->refund_amount - $totalPaid;
            }

            return $this->responseSuccess($payment, 'Unit transaction refund payment updated successfully', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error('Error while updating unit transaction refund payment: ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Failed to update unit transaction refund payment', 500);
        }
    }

    /**
     * Delete a unit transaction refund payment.
     */
    public function destroy(string $id)
    {
        try {
            $payment = UnitTransactionRefundPayment::findOrFail($id);
            $payment->delete();

            return $this->responseSuccess(null, 'Unit transaction refund payment deleted successfully', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error('Error while deleting unit transaction refund payment: ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Failed to delete unit transaction refund payment', 500);
        }
    }
}
