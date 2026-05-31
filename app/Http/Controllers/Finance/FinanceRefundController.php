<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\FinanceRefund;
use App\Models\Cash;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class FinanceRefundController extends Controller
{
    use ResponseTrait;

    public function __construct()
    {
        $this->middleware(['permission:finance:list'])->only(['index', 'show']);
        $this->middleware(['permission:finance:edit'])->only('update');
    }

    public function index(Request $request)
    {
        try {
            $query = FinanceRefund::with([
                'unitTransactionRefund:id,uuid,unit_transaction_id,code,refund_date,refund_amount,note',
                'unitTransactionRefund.unitTransaction:id,uuid,person_id,code,type',
                'unitTransactionRefund.unitTransaction.person:id,uuid,name,type',
                'cash:id,uuid,company_id,code,type'
            ]);

            if ($request->filled('status')) {
                if (in_array($request->status, ['waiting', 'reject','approve'])) {
                    $query->where('status', $request->status);
                }
            }

            // Filter berdasarkan tanggal (refund_date)
            if ($request->filled('start_date') && $request->filled('end_date')) {
                $query->whereHas('unitTransactionRefund', function ($q) use ($request) {
                    $q->whereBetween('refund_date', [$request->start_date, $request->end_date]);
                });
            } elseif ($request->filled('date')) {
                $query->whereHas('unitTransactionRefund', function ($q) use ($request) {
                    $q->whereDate('refund_date', $request->date);
                });
            }

            // Filter berdasarkan nama person
            if ($request->filled('person_name')) {
                $query->whereHas('unitTransactionRefund.unitTransaction.person', function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->person_name . '%');
                });
            }

            // Filter berdasarkan total pembelian (refund_amount)
            if ($request->filled('refund_amount')) {
                $query->whereHas('unitTransactionRefund', function ($q) use ($request) {
                    $q->where('refund_amount', $request->refund_amount);
                });
            }

            // Filter berdasarkan kas masuk (cash_id)
            if ($request->filled('cash_id')) {
                $query->where('cash_id', $request->cash_id);
            }

            $data = $query->latest()->paginate($request->per_page ?? 10);

            $data->getCollection()->transform(function ($item) {
                if ($item->unitTransactionRefund && $item->unitTransactionRefund->unitTransaction) {
                    $item->unitTransactionRefund->unitTransaction->bruto_amount = (int) $item->unitTransactionRefund->unitTransaction->getBrutoAmount();
                }
                return $item;
            });

            return $this->responseSuccess($data, 'Finance Refund list retrieved successfully');
        } catch (Exception $err) {
            Log::error('Error Work While retrieving Finance Refund data: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Finance Refund list retrieval failed', 500);
        }
    }

    public function show($id)
    {
        try {
            $data = FinanceRefund::with([
                'unitTransactionRefund',
                'cash',
                'financeRefundPayments'
            ])->findOrFail($id);

            return $this->responseSuccess($data, 'Finance Refund retrieved successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            return $this->responseError($err->getMessage(), 'Finance Refund not found', 404);
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'cash_id' => 'sometimes|nullable|exists:cashes,id',
            'status' => 'sometimes|required|in:waiting,reject,approve',
        ]);

        try {
            $financeRefund = FinanceRefund::findOrFail($id);
            $oldStatus = $financeRefund->status;
            $oldCashId = $financeRefund->cash_id;

            DB::transaction(function () use ($financeRefund, $validated) {
                $financeRefund->update($validated);
            });

            $newStatus = $financeRefund->status;
            $newCashId = $financeRefund->cash_id;

            if ($newStatus === 'approve' && $oldStatus !== 'approve') {
                $cash = Cash::find($newCashId);
                if ($cash && $financeRefund->unitTransactionRefund) {
                    $refundAmount = (float) $financeRefund->unitTransactionRefund->refund_amount;
                    $trxType = $financeRefund->unitTransactionRefund->unitTransaction->type;
                    $cash->adjustAmount($refundAmount, 'refund_' . $trxType);
                }
            } elseif ($newStatus !== 'approve' && $oldStatus === 'approve') {
                $cash = Cash::find($oldCashId);
                if ($cash && $financeRefund->unitTransactionRefund) {
                    $refundAmount = (float) $financeRefund->unitTransactionRefund->refund_amount;
                    $trxType = $financeRefund->unitTransactionRefund->unitTransaction->type;
                    $cash->adjustAmount(-$refundAmount, 'refund_' . $trxType);
                }
            } elseif ($newStatus === 'approve' && $oldStatus === 'approve' && $newCashId !== $oldCashId) {
                if ($financeRefund->unitTransactionRefund) {
                    $refundAmount = (float) $financeRefund->unitTransactionRefund->refund_amount;
                    $trxType = $financeRefund->unitTransactionRefund->unitTransaction->type;

                    $oldCash = Cash::find($oldCashId);
                    if ($oldCash) {
                        $oldCash->adjustAmount(-$refundAmount, 'refund_' . $trxType);
                    }

                    $newCash = Cash::find($newCashId);
                    if ($newCash) {
                        $newCash->adjustAmount($refundAmount, 'refund_' . $trxType);
                    }
                }
            }

            return $this->responseSuccess($financeRefund->load(['unitTransactionRefund', 'cash']), 'Finance Refund updated successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error While updating Finance Refund data: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Finance Refund update failed', 500);
        }
    }
}
