<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\Cash;
use App\Models\CashFlow;
use App\Models\FinanceBilling;
use App\Models\TransactionFlow;
use App\Models\UnitTransaction;
use App\Models\UnitTransactionBilling;
use App\Models\UnitTransactionBillingHistory;
use App\Traits\FileTrait;
use App\Traits\GlobalCodeNumberTrait;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\CurrencyService;
use Illuminate\Validation\ValidationException;

class UnitTransactionBillingHistoryController extends Controller
{
    use FileTrait, ResponseTrait, GlobalCodeNumberTrait;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only('update');
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);
    }

    public function index(Request $request)
    {
        try {
            $query = UnitTransactionBillingHistory::with([
                'unitTransactionBilling:id,unit_transaction_id',
                'cashes:id,uuid,code',
            ]);

            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('uuid', 'like', "%$search%")
                        ->orWhereHas('cashes', function ($q) use ($search) {
                            $q->where('name', 'like', "%$search%")
                                ->orWhere('code', 'like', "%$search%");
                        });
                });
            }

            return $this->responseSuccess(
                $query->paginate($request->per_page ?? 10),
                'Billing history retrieved successfully',
                200
            );
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error($err->getMessage());

            return $this->responseError(null, 'Failed to retrieve data', 500);
        }
    }

    public function show(string $id)
    {
        try {
            $data = UnitTransactionBillingHistory::with([
                'unitTransactionBilling:id,unit_transaction_id',
                'cashes',
            ])->findOrFail($id);

            return $this->responseSuccess($data, 'Billing history retrieved successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            return $this->responseError($err->getMessage(), 'Data not found', 404);
        }
    }

    public function store(Request $request)
    {
        $cashSlug = ['cash_idr', 'bca_idr', 'bca_usd'];

        try {
            $validated = $request->validate([
                'unit_transaction_billing_id' => 'required|exists:unit_transaction_billings,id',
                'bca_payment_amount' => 'nullable|numeric|min:0',
                'bca_payment_usd_amount' => 'nullable|numeric|min:0',
                'cash_payment_amount' => 'nullable|numeric|min:0',
                'payment_at' => 'nullable|date',
                'note' => 'nullable|string',
                'payment_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            ]);

            $payments = array_filter([
                'bca_idr' => $validated['bca_payment_amount'] ?? 0,
                'bca_usd' => $validated['bca_payment_usd_amount'] ?? 0,
                'cash_idr' => $validated['cash_payment_amount'] ?? 0,
            ], fn($v) => $v > 0);

            if (empty($payments)) {
                throw ValidationException::withMessages([
                    'payment' => 'Payment amount must be greater than 0.',
                ]);
            }

            if ($request->hasFile('payment_proof')) {
                $validated['payment_proof'] = $this->storeFile(
                    $request->file('payment_proof'),
                    'payment_proof'
                );
            }

            $billing = UnitTransactionBilling::with([
                'unitTransaction.warehouse',
                'unitTransaction.unitTransactionItems.unitTransactionItemDetails',
            ])->findOrFail($validated['unit_transaction_billing_id']);

            $existingUsdPayment = DB::table('cash_unit_transaction_billing_history')
                ->join('unit_transaction_billing_histories', 'unit_transaction_billing_histories.id', '=', 'cash_unit_transaction_billing_history.unit_transaction_billing_history_id')
                ->join('cashes', 'cashes.id', '=', 'cash_unit_transaction_billing_history.cash_id')
                ->where('unit_transaction_billing_histories.unit_transaction_billing_id', $billing->id)
                ->where('cashes.code', 'bca_usd')
                ->exists();

            $usdPaymentSet = isset($payments['bca_usd']) || $existingUsdPayment;

            $paymentTotal = array_sum($payments);

            $totalPaidBeforeList = DB::table('cash_unit_transaction_billing_history')
                ->join('unit_transaction_billing_histories', 'unit_transaction_billing_histories.id', '=', 'cash_unit_transaction_billing_history.unit_transaction_billing_history_id')
                ->join('cashes', 'cashes.id', '=', 'cash_unit_transaction_billing_history.cash_id')
                ->where('unit_transaction_billing_histories.unit_transaction_billing_id', $billing->id)
                ->whereIn('cashes.code', ['cash_idr', 'bca_idr', 'bca_usd'])
                ->select('cashes.code', 'cash_unit_transaction_billing_history.amount')
                ->get();

            $totalPaidBefore = $totalPaidBeforeList->sum('amount');
            $newTotalPaid = $totalPaidBefore + $paymentTotal;

            if (!$usdPaymentSet && $newTotalPaid > $billing->grand_total) {
                throw ValidationException::withMessages([
                    'payment' => 'Total payment exceeds grand total.',
                ]);
            }

            $createdHistories = [];

            DB::transaction(function () use ($billing, $validated, $payments, $usdPaymentSet, &$createdHistories) {
                $companyId = $billing->unitTransaction->warehouse->company_id;

                foreach ($payments as $slug => $amount) {
                    $history = UnitTransactionBillingHistory::create([
                        'unit_transaction_billing_id' => $billing->id,
                        'payment_at' => $validated['payment_at'] ?? now(),
                        'payment_proof' => $validated['payment_proof'] ?? null,
                        'note' => $validated['note'] ?? null,
                    ]);

                    $cash = Cash::where('company_id', $companyId)->where('code', $slug)->first();
                    if ($cash) {
                        $history->cashes()->attach($cash->id, ['amount' => $amount]);
                    }

                    $createdHistories[] = $history;
                }

                $totalPaid = DB::table('cash_unit_transaction_billing_history')
                    ->join('unit_transaction_billing_histories', 'unit_transaction_billing_histories.id', '=', 'cash_unit_transaction_billing_history.unit_transaction_billing_history_id')
                    ->join('cashes', 'cashes.id', '=', 'cash_unit_transaction_billing_history.cash_id')
                    ->where('unit_transaction_billing_histories.unit_transaction_billing_id', $billing->id)
                    ->whereIn('cashes.code', ['cash_idr', 'bca_idr', 'bca_usd'])
                    ->sum('cash_unit_transaction_billing_history.amount');

                $totalIdrPaid = DB::table('cash_unit_transaction_billing_history')
                    ->join('unit_transaction_billing_histories', 'unit_transaction_billing_histories.id', '=', 'cash_unit_transaction_billing_history.unit_transaction_billing_history_id')
                    ->join('cashes', 'cashes.id', '=', 'cash_unit_transaction_billing_history.cash_id')
                    ->where('unit_transaction_billing_histories.unit_transaction_billing_id', $billing->id)
                    ->whereIn('cashes.code', ['cash_idr', 'bca_idr'])
                    ->sum('cash_unit_transaction_billing_history.amount');

                if ($usdPaymentSet) {
                    $billing->update([
                        'total_paid'        => $totalPaid,
                        'remaining_payment' => 0,
                        'last_payment_at'   => now(),
                    ]);
                } else {
                    $remaining = $billing->grand_total - $totalIdrPaid;
                    $billing->update([
                        'total_paid'        => $totalPaid,
                        'remaining_payment' => $remaining,
                        'last_payment_at'   => now(),
                    ]);
                }
            });

            $billingFresh = $billing->fresh('unitTransactionBillingHistories');
            $billingFresh->remaining_payment = $usdPaymentSet ? 0 : $billingFresh->getRemainingPayment();

            return $this->responseSuccess(
                array_merge($billingFresh->toArray(), [
                    'usd_payment_set' => $usdPaymentSet,
                ]),
                'Payment history created successfully',
                201
            );
        } catch (ValidationException $err) {
            return $this->responseError($err->errors(), 'Validation failed', 422);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error($err->getMessage());

            return $this->responseError($err->getMessage(), 'Create failed', 500);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $history = UnitTransactionBillingHistory::with(['unitTransactionBilling.unitTransaction.warehouse', 'cashes'])->findOrFail($id);

            $validated = $request->validate([
                'bca_payment_amount' => 'nullable|numeric|min:0',
                'bca_payment_usd_amount' => 'nullable|numeric|min:0',
                'cash_payment_amount' => 'nullable|numeric|min:0',
                'note' => 'nullable|string',
                'payment_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            ]);

            DB::transaction(function () use ($history, $validated, $request) {
                $billing = $history->unitTransactionBilling;
                $companyId = $billing->unitTransaction->warehouse->company_id;

                if ($request->hasFile('payment_proof')) {
                    $validated['payment_proof'] = $this->storeFile(
                        $request->file('payment_proof'),
                        'payment_proof'
                    );
                }

                $cashIdrPivot = $history->cashes->where('code', 'cash_idr')->first();
                $historyCashPaymentAmount = $cashIdrPivot ? $cashIdrPivot->pivot->amount : 0;

                $bcaIdrPivot = $history->cashes->where('code', 'bca_idr')->first();
                $historyBcaPaymentAmount = $bcaIdrPivot ? $bcaIdrPivot->pivot->amount : 0;

                $bcaUsdPivot = $history->cashes->where('code', 'bca_usd')->first();
                $historyBcaUsdPaymentAmount = $bcaUsdPivot ? $bcaUsdPivot->pivot->amount : 0;

                foreach (['cash_idr', 'bca_idr', 'bca_usd'] as $slug) {
                    $newVal = match ($slug) {
                        'cash_idr' => array_key_exists('cash_payment_amount', $validated) ? $validated['cash_payment_amount'] : $historyCashPaymentAmount,
                        'bca_idr' => array_key_exists('bca_payment_amount', $validated) ? $validated['bca_payment_amount'] : $historyBcaPaymentAmount,
                        'bca_usd' => array_key_exists('bca_payment_usd_amount', $validated) ? $validated['bca_payment_usd_amount'] : $historyBcaUsdPaymentAmount,
                    };

                    $cash = Cash::where('company_id', $companyId)->where('code', $slug)->first();
                    if ($cash) {
                        if ($newVal > 0) {
                            $existingPivot = $history->cashes->where('id', $cash->id)->first();
                            $pivotData = ['amount' => $newVal];
                            if ($existingPivot) {
                                $history->cashes()->updateExistingPivot($cash->id, $pivotData);
                            } else {
                                $history->cashes()->attach($cash->id, $pivotData);
                            }
                        } else {
                            $history->cashes()->detach($cash->id);
                        }
                    }
                }

                $history->update([
                    'note' => $validated['note'] ?? $history->note,
                    'payment_proof' => $validated['payment_proof'] ?? $history->payment_proof,
                ]);

                // Cek apakah billing ini memiliki pembayaran bca_usd (history lain atau current)
                $hasUsdPayment = DB::table('cash_unit_transaction_billing_history')
                    ->join('unit_transaction_billing_histories', 'unit_transaction_billing_histories.id', '=', 'cash_unit_transaction_billing_history.unit_transaction_billing_history_id')
                    ->join('cashes', 'cashes.id', '=', 'cash_unit_transaction_billing_history.cash_id')
                    ->where('unit_transaction_billing_histories.unit_transaction_billing_id', $billing->id)
                    ->where('cashes.code', 'bca_usd')
                    ->exists();

                // Hitung total_paid dari semua jenis pembayaran
                $totalPaid = DB::table('cash_unit_transaction_billing_history')
                    ->join('unit_transaction_billing_histories', 'unit_transaction_billing_histories.id', '=', 'cash_unit_transaction_billing_history.unit_transaction_billing_history_id')
                    ->join('cashes', 'cashes.id', '=', 'cash_unit_transaction_billing_history.cash_id')
                    ->where('unit_transaction_billing_histories.unit_transaction_billing_id', $billing->id)
                    ->whereIn('cashes.code', ['cash_idr', 'bca_idr', 'bca_usd'])
                    ->sum('cash_unit_transaction_billing_history.amount');

                // Hitung remaining hanya dari bca_idr dan cash_idr
                $totalIdrPaid = DB::table('cash_unit_transaction_billing_history')
                    ->join('unit_transaction_billing_histories', 'unit_transaction_billing_histories.id', '=', 'cash_unit_transaction_billing_history.unit_transaction_billing_history_id')
                    ->join('cashes', 'cashes.id', '=', 'cash_unit_transaction_billing_history.cash_id')
                    ->where('unit_transaction_billing_histories.unit_transaction_billing_id', $billing->id)
                    ->whereIn('cashes.code', ['cash_idr', 'bca_idr'])
                    ->sum('cash_unit_transaction_billing_history.amount');

                if ($hasUsdPayment) {
                    $billing->update([
                        'total_paid'        => $totalPaid,
                        'remaining_payment' => 0,
                    ]);
                } else {
                    $remaining = $billing->grand_total - $totalIdrPaid;
                    $billing->update([
                        'total_paid'        => $totalPaid,
                        'remaining_payment' => $remaining,
                    ]);
                }
            });

            return $this->responseSuccess($history->fresh(), 'History updated successfully', 200);
        } catch (ValidationException $err) {
            return $this->responseError($err->errors(), 'Validation failed', 422);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error($err->getMessage());

            return $this->responseError($err->getMessage(), 'Update failed', 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $history = UnitTransactionBillingHistory::with(['unitTransactionBilling.unitTransaction.warehouse', 'cashes'])->findOrFail($id);

            DB::transaction(function () use ($history) {
                $billing = $history->unitTransactionBilling;
                $companyId = $billing->unitTransaction->warehouse->company_id;

                $cashIdrPivot = $history->cashes->where('code', 'cash_idr')->first();
                $bcaIdrPivot = $history->cashes->where('code', 'bca_idr')->first();
                $bcaUsdPivot = $history->cashes->where('code', 'bca_usd')->first();

                $amountsToDeduct = [
                    'cash_idr' => $cashIdrPivot ? $cashIdrPivot->pivot->amount : 0,
                    'bca_idr' => $bcaIdrPivot ? $bcaIdrPivot->pivot->amount : 0,
                    'bca_usd' => $bcaUsdPivot ? $bcaUsdPivot->pivot->amount : 0,
                ];

                foreach (['cash_idr', 'bca_idr', 'bca_usd'] as $slug) {
                    if ($amountsToDeduct[$slug] > 0) {
                        Cash::where('company_id', $companyId)
                            ->where('code', $slug)
                            ->decrement('amount', $amountsToDeduct[$slug]);
                    }
                }

                $history->delete();

                $totalPaidList = DB::table('cash_unit_transaction_billing_history')
                    ->join('unit_transaction_billing_histories', 'unit_transaction_billing_histories.id', '=', 'cash_unit_transaction_billing_history.unit_transaction_billing_history_id')
                    ->join('cashes', 'cashes.id', '=', 'cash_unit_transaction_billing_history.cash_id')
                    ->where('unit_transaction_billing_histories.unit_transaction_billing_id', $billing->id)
                    ->whereIn('cashes.code', ['cash_idr', 'bca_idr', 'bca_usd'])
                    ->select('cashes.code', 'cash_unit_transaction_billing_history.amount', 'cash_unit_transaction_billing_history.original_amount')
                    ->get();

                $totalPaid = 0;
                foreach ($totalPaidList as $item) {
                    if ($item->code === 'bca_usd') {
                        $totalPaid += $item->original_amount;
                    } else {
                        $totalPaid += $item->amount;
                    }
                }

                $remaining = $billing->grand_total - $totalPaid;

                $billing->update([
                    'total_paid' => $totalPaid,
                    'remaining_payment' => $remaining,
                    'is_paid' => $remaining <= 0,
                ]);
            });

            return $this->responseSuccess([], 'History deleted successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error($err->getMessage());

            return $this->responseError($err->getMessage(), 'Delete failed', 500);
        }
    }
}
