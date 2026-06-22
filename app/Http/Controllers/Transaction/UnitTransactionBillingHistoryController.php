<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\Cash;
use App\Models\CashFlow;
use App\Models\FinanceBilling;
use App\Models\TransactionFlow;
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

            $bca = $validated['bca_payment_amount'] ?? 0;
            $cash = $validated['cash_payment_amount'] ?? 0;
            $bcaUsd = $validated['bca_payment_usd_amount'] ?? 0;

            $usdInIdr = 0;
            $exchangeRate = 0;
            if ($bcaUsd > 0) {
                $currencyService = app(CurrencyService::class);
                $exchangeRate = (int) $currencyService->convertUsdToIdr('1');
                if (!$exchangeRate) {
                    return $this->responseError([], 'Failed to convert USD to IDR via Unirate API.', 500);
                }
                $usdInIdr = (int) ($bcaUsd * $exchangeRate);
            }

            $paymentTotal = $bca + $cash + $usdInIdr;

            if ($paymentTotal <= 0) {
                throw ValidationException::withMessages([
                    'payment' => 'Payment amount must be greater than 0.',
                ]);
            }

            $totalPaidBeforeList = DB::table('cash_unit_transaction_billing_history')
                ->join('unit_transaction_billing_histories', 'unit_transaction_billing_histories.id', '=', 'cash_unit_transaction_billing_history.unit_transaction_billing_history_id')
                ->join('cashes', 'cashes.id', '=', 'cash_unit_transaction_billing_history.cash_id')
                ->where('unit_transaction_billing_histories.unit_transaction_billing_id', $billing->id)
                ->whereIn('cashes.code', ['cash_idr', 'bca_idr', 'bca_usd'])
                ->select('cashes.code', 'cash_unit_transaction_billing_history.amount', 'cash_unit_transaction_billing_history.original_amount')
                ->get();

            $totalPaidBefore = 0;
            foreach ($totalPaidBeforeList as $item) {
                if ($item->code === 'bca_usd') {
                    $totalPaidBefore += $item->original_amount;
                } else {
                    $totalPaidBefore += $item->amount;
                }
            }

            $newTotalPaid = $totalPaidBefore + $paymentTotal;

            if ($newTotalPaid > $billing->grand_total) {
                throw ValidationException::withMessages([
                    'payment' => 'Total payment exceeds grand total.',
                ]);
            }

            DB::transaction(function () use ($billing, $validated, $newTotalPaid, $cashSlug, $usdInIdr, $exchangeRate) {
                $history = UnitTransactionBillingHistory::create([
                    'unit_transaction_billing_id' => $billing->id,
                    'payment_at' => $validated['payment_at'] ?? now(),
                    'payment_proof' => $validated['payment_proof'] ?? null,
                    'note' => $validated['note'] ?? null,
                ]);

                $remaining = $billing->grand_total - $newTotalPaid;

                $billing->update([
                    'total_paid' => $newTotalPaid,
                    'remaining_payment' => $remaining,
                    'is_paid' => $remaining <= 0,
                    'last_payment_at' => now(),
                ]);

                $companyId = $billing->unitTransaction->warehouse->company_id;

                foreach ($cashSlug as $slug) {
                    $amountToAdd = match ($slug) {
                        'cash_idr' => $validated['cash_payment_amount'] ?? 0,
                        'bca_idr' => $validated['bca_payment_amount'] ?? 0,
                        'bca_usd' => $validated['bca_payment_usd_amount'] ?? 0,
                        default => 0,
                    };

                    if ($amountToAdd && $amountToAdd > 0) {
                        $cash = Cash::where('company_id', $companyId)->where('code', $slug)->first();
                        if ($cash) {
                            $pivotData = ['amount' => $amountToAdd];
                            if ($slug === 'bca_usd') {
                                $pivotData['original_amount'] = $usdInIdr;
                                $pivotData['exchange_amount'] = $exchangeRate;
                            }
                            $history->cashes()->attach($cash->id, $pivotData);
                            $cash->increment('amount', $amountToAdd);
                        }
                    }
                }

                // Always ensure FinanceBilling exists on the first payment so finance department can process it
                $existsFinanceBilling = FinanceBilling::where('unit_transaction_billing_id', $billing->id)->exists();
                if (!$existsFinanceBilling) {
                    FinanceBilling::create([
                        'unit_transaction_billing_id' => $billing->id,
                        'cash_flow_id' => null,
                        'grand_total' => $billing->grand_total,
                        'last_payment_at' => now(),
                        'is_valid' => false,
                    ]);
                }

                if ($remaining <= 0) {
                    $billing->load(['unitTransactionBillingHistories.cashes', 'financeBilling.financeBillingItems']);
                    $financeBilling = $billing->financeBilling;

                    $totalBca = 0;
                    $totalCash = 0;
                    $totalBcaUsd = 0;

                    if ($financeBilling && $financeBilling->financeBillingItems->isNotEmpty()) {
                        $totalBca = (int) $financeBilling->financeBillingItems->sum('bca_payment_amount');
                        $totalCash = (int) $financeBilling->financeBillingItems->sum('cash_payment_amount');
                        $totalBcaUsd = (int) $financeBilling->financeBillingItems->sum('bca_payment_usd_amount');
                    }

                    if ($totalBca == 0 && $totalCash == 0 && $totalBcaUsd == 0) {
                        $totalBca = (int) $billing->getTotalBcaCashPayment();
                        $totalCash = (int) $billing->getTotalCashPayment();
                        $totalBcaUsd = (int) $billing->getTotalBcaUsdPayment();
                    }

                    $totalBcaUsdInIdr = (int) $billing->getTotalBcaUsdPaymentInIdr();

                    $createdCashFlowIds = [];
                    $createdFinanceBillingIds = [];

                    if ($totalBca > 0 || $totalCash > 0 || $totalBcaUsd > 0) {
                        $paymentMethods = [
                            'bca_idr' => $totalBca,
                            'cash_idr' => $totalCash,
                            'bca_usd' => $totalBcaUsd,
                        ];

                        foreach ($paymentMethods as $slug => $amount) {
                            if ($amount > 0) {
                                $cashObj = Cash::where('company_id', $companyId)->where('code', $slug)->first();
                                $cashId = $cashObj ? $cashObj->id : null;
                                $cashName = $cashObj ? $cashObj->name : strtoupper(str_replace('_', ' ', $slug));

                                $cf = CashFlow::updateOrCreate(
                                    [
                                        'unit_transaction_billing_id' => $billing->id,
                                        'cash_id' => $cashId,
                                    ],
                                    [
                                        'company_id' => $companyId,
                                        'code' => $billing->unitTransaction->code . '-' . $slug,
                                        'date' => $validated['payment_at'] ?? now(),
                                        'note' => "Pelunasan Total " . $billing->unitTransaction->code . " (" . $cashName . ")",
                                        'debet' => $billing->unitTransaction->type === 'sales' ? $amount : 0,
                                        'debet_original' => $billing->unitTransaction->type === 'sales' ? ($slug === 'bca_usd' ? $totalBcaUsdInIdr : $amount) : 0,
                                        'credit' => $billing->unitTransaction->type === 'purchase' ? $amount : 0,
                                        'credit_original' => $billing->unitTransaction->type === 'purchase' ? ($slug === 'bca_usd' ? $totalBcaUsdInIdr : $amount) : 0,
                                    ]
                                );
                                $createdCashFlowIds[] = $cf->id;

                                $fb = FinanceBilling::where('unit_transaction_billing_id', $billing->id)
                                    ->where(function ($q) use ($cf) {
                                        $q->where('cash_flow_id', $cf->id)
                                          ->orWhereNull('cash_flow_id');
                                    })
                                    ->first();

                                if ($fb) {
                                    $fb->update([
                                        'cash_flow_id' => $cf->id,
                                        'grand_total' => $billing->grand_total,
                                        'last_payment_at' => now(),
                                        'is_valid' => false,
                                    ]);
                                } else {
                                    $fb = FinanceBilling::create([
                                        'unit_transaction_billing_id' => $billing->id,
                                        'cash_flow_id' => $cf->id,
                                        'grand_total' => $billing->grand_total,
                                        'last_payment_at' => now(),
                                        'is_valid' => false,
                                    ]);
                                }
                                $createdFinanceBillingIds[] = $fb->id;
                            }
                        }
                    } else {
                        $cashObj = Cash::where('company_id', $companyId)->where('code', 'cash_idr')->first();
                        $cashId = $cashObj ? $cashObj->id : null;
                        $cashName = $cashObj ? $cashObj->name : 'CASH IDR';

                        $cf = CashFlow::updateOrCreate(
                            [
                                'unit_transaction_billing_id' => $billing->id,
                                'cash_id' => $cashId,
                            ],
                            [
                                'company_id' => $companyId,
                                'code' => $billing->unitTransaction->code . '-cash_idr',
                                'date' => $validated['payment_at'] ?? now(),
                                'note' => "Pelunasan Total " . $billing->unitTransaction->code . " (" . $cashName . ")",
                                'debet' => $billing->unitTransaction->type === 'sales' ? $billing->grand_total : 0,
                                'debet_original' => $billing->unitTransaction->type === 'sales' ? $billing->grand_total : 0,
                                'credit' => $billing->unitTransaction->type === 'purchase' ? $billing->grand_total : 0,
                                'credit_original' => $billing->unitTransaction->type === 'purchase' ? $billing->grand_total : 0,
                            ]
                        );
                        $createdCashFlowIds[] = $cf->id;

                        $fb = FinanceBilling::updateOrCreate(
                            [
                                'unit_transaction_billing_id' => $billing->id,
                                'cash_flow_id' => $cf->id,
                            ],
                            [
                                'grand_total' => $billing->grand_total,
                                'last_payment_at' => now(),
                                'is_valid' => false,
                            ]
                        );
                        $createdFinanceBillingIds[] = $fb->id;
                    }

                    CashFlow::where('unit_transaction_billing_id', $billing->id)
                        ->whereNotIn('id', $createdCashFlowIds)
                        ->delete();

                    FinanceBilling::where('unit_transaction_billing_id', $billing->id)
                        ->whereNotIn('id', $createdFinanceBillingIds)
                        ->delete();

                    $unitTransaction = $billing->unitTransaction;
                    $itemDetails = $unitTransaction->unitTransactionItems->map(function ($item) {
                        $name = $item->unitType?->name ?? $item->sparepart?->name ?? 'Unknown';
                        return "{$item->qty_total} {$name}";
                    })->implode(', ');

                    $itemCount = $unitTransaction->unitTransactionItems->count();
                    $transactionTypeLabel = $unitTransaction->type === 'sales' ? 'Penjualan' : 'Pembelian';
                    $prefixLabel = $unitTransaction->type === 'sales' ? 'diterima' : 'dibayar';

                    TransactionFlow::updateOrCreate(
                        ['unit_transaction_id' => $unitTransaction->id],
                        [
                            'company_id' => $companyId,
                            'code' => $unitTransaction->code,
                            'transaction_date' => now(),
                            'unit_transaction_id' => $unitTransaction->id,
                            'name' => $unitTransaction->person->name ?? null,
                            'description' => "{$transactionTypeLabel} {$prefixLabel} dimuka ke-{$itemCount} unit spm: {$itemDetails}",
                            'bank_usd_debit' => $unitTransaction->type === 'sales' ? $totalBcaUsd : 0,
                            'bank_usd_debit_original' => $unitTransaction->type === 'sales' ? $totalBcaUsdInIdr : 0,
                            'bank_idr_debit' => $unitTransaction->type === 'sales' ? $totalBca : 0,
                            'cash_idr_debit' => $unitTransaction->type === 'sales' ? $totalCash : 0,
                            'bank_idr_credit' => $unitTransaction->type === 'purchase' ? $totalBca : 0,
                            'bank_usd_credit' => $unitTransaction->type === 'purchase' ? $totalBcaUsd : 0,
                            'bank_usd_credit_original' => $unitTransaction->type === 'purchase' ? $totalBcaUsdInIdr : 0,
                            'cash_idr_credit' => $unitTransaction->type === 'purchase' ? $totalCash : 0,
                        ]
                    );
                }
            });

            $billingFresh = $billing->fresh('unitTransactionBillingHistories');

            $billingFresh->remaining_payment = $billingFresh->getRemainingPayment();

            return $this->responseSuccess(
                $billingFresh,
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
                $historyBcaUsdOriginalAmount = $bcaUsdPivot ? $bcaUsdPivot->pivot->original_amount : 0;
                $historyBcaUsdExchangeAmount = $bcaUsdPivot ? $bcaUsdPivot->pivot->exchange_amount : 0;

                $diffs = [
                    'cash_idr' => (array_key_exists('cash_payment_amount', $validated) ? $validated['cash_payment_amount'] : $historyCashPaymentAmount) - $historyCashPaymentAmount,
                    'bca_idr' => (array_key_exists('bca_payment_amount', $validated) ? $validated['bca_payment_amount'] : $historyBcaPaymentAmount) - $historyBcaPaymentAmount,
                    'bca_usd' => (array_key_exists('bca_payment_usd_amount', $validated) ? $validated['bca_payment_usd_amount'] : $historyBcaUsdPaymentAmount) - $historyBcaUsdPaymentAmount,
                ];

                $usdInIdr = $historyBcaUsdOriginalAmount;
                $exchangeRate = $historyBcaUsdExchangeAmount;
                if (array_key_exists('bca_payment_usd_amount', $validated) && $validated['bca_payment_usd_amount'] != $historyBcaUsdPaymentAmount) {
                    $newUsdVal = $validated['bca_payment_usd_amount'];
                    if ($newUsdVal > 0) {
                        $currencyService = app(CurrencyService::class);
                        $exchangeRate = (int) $currencyService->convertUsdToIdr('1');
                        if (!$exchangeRate) {
                            throw new \Exception('Failed to convert USD to IDR via Unirate API.');
                        }
                        $usdInIdr = (int) ($newUsdVal * $exchangeRate);
                    } else {
                        $usdInIdr = 0;
                        $exchangeRate = 0;
                    }
                }

                foreach (['cash_idr', 'bca_idr', 'bca_usd'] as $slug) {
                    $diff = $diffs[$slug];
                    $newVal = match ($slug) {
                        'cash_idr' => array_key_exists('cash_payment_amount', $validated) ? $validated['cash_payment_amount'] : $historyCashPaymentAmount,
                        'bca_idr' => array_key_exists('bca_payment_amount', $validated) ? $validated['bca_payment_amount'] : $historyBcaPaymentAmount,
                        'bca_usd' => array_key_exists('bca_payment_usd_amount', $validated) ? $validated['bca_payment_usd_amount'] : $historyBcaUsdPaymentAmount,
                    };

                    $cash = Cash::where('company_id', $companyId)->where('code', $slug)->first();
                    if ($cash) {
                        if ($diff != 0) {
                            $cash->increment('amount', $diff);
                        }

                        if ($newVal > 0) {
                            $existingPivot = $history->cashes->where('id', $cash->id)->first();
                            $pivotData = ['amount' => $newVal];
                            if ($slug === 'bca_usd') {
                                $pivotData['original_amount'] = $usdInIdr;
                                $pivotData['exchange_amount'] = $exchangeRate;
                            }
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
