<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\CashFlow;
use App\Models\FinanceBilling;
use App\Models\TransactionFlow;
use App\Models\UnitTransaction;
use App\Models\UnitTransactionBilling;
use App\Traits\FileTrait;
use App\Traits\GlobalCodeNumberTrait;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UnitTransactionBillingController extends Controller
{
    use FileTrait, ResponseTrait, GlobalCodeNumberTrait;

    protected array $unitTransactionBillingTable;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only('update');
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);

        $this->unitTransactionBillingTable = [
            'id',
            'uuid',
            'unit_transaction_id',
            'grand_total',
            'last_payment_at',
            'is_paid',
            'created_at',
        ];
    }

    public function index(Request $request)
    {
        try {
            $query = UnitTransactionBilling::query();

            $query->select($this->unitTransactionBillingTable)
                ->with([
                    'unitTransaction:id,code',
                ]);

            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('uuid', 'like', "%$search%")
                        ->orWhere('grand_total', 'like', "%$search%");
                });
            }

            $query->orderBy(
                in_array($request->sort_by, $this->unitTransactionBillingTable) ? $request->sort_by : 'id',
                $request->sort_order === 'asc' ? 'asc' : 'desc'
            );

            $data = $query->paginate($request->per_page ?? 10);

            $data->getCollection()->transform(function ($item) {

                $item->total_cash_payment = $item->getTotalCashPayment();
                $item->total_bca_cash_payment = $item->getTotalBcaCashPayment();
                $item->total_usd_payment = $item->getTotalBcaUsdPayment();
                $item->total_paid = $item->getTotalPaid();

                // Cek apakah ada pembayaran bca_usd
                $hasUsdPayment = \DB::table('cash_unit_transaction_billing_history')
                    ->join('unit_transaction_billing_histories', 'unit_transaction_billing_histories.id', '=', 'cash_unit_transaction_billing_history.unit_transaction_billing_history_id')
                    ->join('cashes', 'cashes.id', '=', 'cash_unit_transaction_billing_history.cash_id')
                    ->where('unit_transaction_billing_histories.unit_transaction_billing_id', $item->id)
                    ->where('cashes.code', 'bca_usd')
                    ->exists();

                $item->remaining_payment = $hasUsdPayment ? 0 : $item->getRemainingPayment();
                $item->usd_payment_set = $hasUsdPayment;

                return $item;
            });

            return $this->responseSuccess(
                $data,
                'Unit Transaction Billing list retrieved successfully',
                200
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error($err->getMessage());

            return $this->responseError(null, 'Failed to retrieve data', 500);
        }
    }

    public function show(string $id)
    {
        try {
            $data = UnitTransactionBilling::with([
                'unitTransaction:id,code',
                'unitTransactionBillingHistories',
            ])
                ->select($this->unitTransactionBillingTable)
                ->findOrFail($id);

            $histories = $data->unitTransactionBillingHistories;

            $totalCash = $data->getTotalCashPayment();
            $totalBca = $data->getTotalBcaCashPayment();
            $totalUsd = $data->getTotalBcaUsdPayment();
            $totalIdrPaid = $totalCash + $totalBca;
            $totalPaid = $totalIdrPaid + $totalUsd;

            // Cek apakah ada pembayaran bca_usd
            $hasUsdPayment = \DB::table('cash_unit_transaction_billing_history')
                ->join('unit_transaction_billing_histories', 'unit_transaction_billing_histories.id', '=', 'cash_unit_transaction_billing_history.unit_transaction_billing_history_id')
                ->join('cashes', 'cashes.id', '=', 'cash_unit_transaction_billing_history.cash_id')
                ->where('unit_transaction_billing_histories.unit_transaction_billing_id', $data->id)
                ->where('cashes.code', 'bca_usd')
                ->exists();

            $remaining = $hasUsdPayment ? 0 : ($data->grand_total - $totalIdrPaid);
            $totalPaymentCount = $histories->count();

            $data->total_cash_payment = $totalCash;
            $data->total_bca_payment = $totalBca;
            $data->total_usd_payment = $totalUsd;
            $data->total_paid = $totalPaid;
            $data->remaining_payment = $remaining;
            $data->usd_payment_set = $hasUsdPayment;
            $data->total_payment_count = $totalPaymentCount;

            return $this->responseSuccess($data, 'Billing retrieved successfully', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            return $this->responseError($err->getMessage(), 'Billing not found', 404);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'unit_transaction_id' => 'required|integer|exists:unit_transactions,id|unique:unit_transaction_billings,unit_transaction_id',
            ]);

            $unitTransaction = UnitTransaction::with([
                'unitTransactionItems.unitTransactionItemDetails',
                'unitTransactionItems.unitTransactionItemSales'
            ])
                ->findOrFail($validated['unit_transaction_id']);

            $grandTotal = $unitTransaction->getBrutoAmount();

            if ($grandTotal <= 0) {
                throw ValidationException::withMessages([
                    'unit_transaction_id' => 'Grand total must be greater than 0.',
                ]);
            }

            $billing = DB::transaction(function () use ($validated, $grandTotal) {
                $billing = UnitTransactionBilling::create([
                    'unit_transaction_id' => $validated['unit_transaction_id'],
                    'grand_total' => $grandTotal,
                    'is_paid' => false,
                ]);

                return $billing;
            });

            return $this->responseSuccess($billing, 'Billing created successfully', 201);
        } catch (ValidationException $err) {
            return $this->responseError($err->errors(), 'Validation failed', 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error($err->getMessage());

            return $this->responseError($err->getMessage(), 'Create failed', 500);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $billing = UnitTransactionBilling::with([
                'unitTransaction.warehouse',
                'unitTransaction.unitTransactionItems',
                'financeBillings.cash',
                'unitTransactionBillingHistories.cashes',
            ])->findOrFail($id);

            $validated = $request->validate([
                'grand_total'     => 'sometimes|numeric|min:1',
                'last_payment_at' => 'sometimes|nullable|date',
                'is_paid'         => 'sometimes|string',
            ]);

            $isPaidTrue = $request->filled('is_paid')
                && ($validated['is_paid'] == 'true' || $validated['is_paid'] == '1');

            if ($isPaidTrue) {
                $validated['is_paid'] = true;

                $hasUsdHistory = DB::table('cash_unit_transaction_billing_history')
                    ->join('unit_transaction_billing_histories', 'unit_transaction_billing_histories.id', '=', 'cash_unit_transaction_billing_history.unit_transaction_billing_history_id')
                    ->join('cashes', 'cashes.id', '=', 'cash_unit_transaction_billing_history.cash_id')
                    ->where('unit_transaction_billing_histories.unit_transaction_billing_id', $billing->id)
                    ->where('cashes.code', 'bca_usd')
                    ->exists();

                if (!$hasUsdHistory) {
                    $totalIdrPaid = DB::table('cash_unit_transaction_billing_history')
                        ->join('unit_transaction_billing_histories', 'unit_transaction_billing_histories.id', '=', 'cash_unit_transaction_billing_history.unit_transaction_billing_history_id')
                        ->join('cashes', 'cashes.id', '=', 'cash_unit_transaction_billing_history.cash_id')
                        ->where('unit_transaction_billing_histories.unit_transaction_billing_id', $billing->id)
                        ->whereIn('cashes.code', ['cash_idr', 'bca_idr'])
                        ->sum('cash_unit_transaction_billing_history.amount');

                    if ($totalIdrPaid < $billing->grand_total) {
                        throw ValidationException::withMessages([
                            'is_paid' => 'Cannot mark as paid: payment is incomplete. Total IDR payment (' . number_format($totalIdrPaid) . ') is less than grand total (' . number_format($billing->grand_total) . ').',
                        ]);
                    }
                }
            }

            DB::transaction(function () use ($billing, $validated, $isPaidTrue) {
                $billing->update($validated);

                if ($isPaidTrue) {
                    $unitTransaction = $billing->unitTransaction;
                    $companyId       = $unitTransaction->warehouse->company_id;
                    $cashFlowType    = $unitTransaction->type === 'purchase' ? 'credit' : 'debet';

                    $financeBillings = $billing->financeBillings;

                    $totalBca    = 0;
                    $totalCash   = 0;
                    $totalBcaUsd = 0;

                    if ($financeBillings->isNotEmpty()) {
                        $totalBca    = (int) $financeBillings->filter(fn($i) => $i->cash && $i->cash->code === 'bca_idr')->sum('amount');
                        $totalCash   = (int) $financeBillings->filter(fn($i) => $i->cash && $i->cash->code === 'cash_idr')->sum('amount');
                        $totalBcaUsd = (int) $financeBillings->filter(fn($i) => $i->cash && $i->cash->code === 'bca_usd')->sum('amount_original');
                    }

                    if ($totalBca == 0 && $totalCash == 0 && $totalBcaUsd == 0) {
                        $totalBca    = (int) $billing->getTotalBcaCashPayment();
                        $totalCash   = (int) $billing->getTotalCashPayment();
                        $totalBcaUsd = (int) $billing->getTotalBcaUsdPayment();
                    }

                    $totalBcaUsdInIdr = (int) $billing->getTotalBcaUsdPaymentInIdr();

                    $createdCashFlowIds = [];

                    $cf = CashFlow::updateOrCreate(
                        ['unit_transaction_billing_id' => $billing->id],
                        [
                            'company_id'      => $companyId,
                            'code'            => $unitTransaction->code . '-payment',
                            'date'            => $billing->last_payment_at ?? now(),
                            'cash_flow_type'  => $cashFlowType,
                            'note'            => 'Pelunasan Total ' . $unitTransaction->code,
                            'debet'           => $unitTransaction->type === 'sales' ? $billing->grand_total : 0,
                            'debet_original'  => $unitTransaction->type === 'sales' ? $billing->grand_total : 0,
                            'credit'          => $unitTransaction->type === 'purchase' ? $billing->grand_total : 0,
                            'credit_original' => $unitTransaction->type === 'purchase' ? $billing->grand_total : 0,
                        ]
                    );
                    $createdCashFlowIds[] = $cf->id;

                    $cf->updateValidity();

                    CashFlow::where('unit_transaction_billing_id', $billing->id)
                        ->whereNotIn('id', $createdCashFlowIds)
                        ->delete();

                    $itemDetails = $unitTransaction->unitTransactionItems->map(function ($item) {
                        $name = $item->unitType?->name ?? $item->sparepart?->name ?? 'Unknown';
                        return "{$item->qty_total} {$name}";
                    })->implode(', ');

                    $itemCount            = $unitTransaction->unitTransactionItems->count();
                    $transactionTypeLabel = $unitTransaction->type === 'sales' ? 'Penjualan' : 'Pembelian';
                    $prefixLabel          = $unitTransaction->type === 'sales' ? 'diterima' : 'dibayar';

                    TransactionFlow::updateOrCreate(
                        ['unit_transaction_id' => $unitTransaction->id],
                        [
                            'company_id'               => $companyId,
                            'code'                     => $unitTransaction->code,
                            'transaction_date'         => now(),
                            'unit_transaction_id'      => $unitTransaction->id,
                            'name'                     => $unitTransaction->person->name ?? null,
                            'description'              => "{$transactionTypeLabel} {$prefixLabel} dimuka ke-{$itemCount} unit spm: {$itemDetails}",
                            'bank_usd_debit'           => $unitTransaction->type === 'sales' ? $totalBcaUsd : 0,
                            'bank_usd_debit_original'  => $unitTransaction->type === 'sales' ? $totalBcaUsdInIdr : 0,
                            'bank_idr_debit'           => $unitTransaction->type === 'sales' ? $totalBca : 0,
                            'cash_idr_debit'           => $unitTransaction->type === 'sales' ? $totalCash : 0,
                            'bank_idr_credit'          => $unitTransaction->type === 'purchase' ? $totalBca : 0,
                            'bank_usd_credit'          => $unitTransaction->type === 'purchase' ? $totalBcaUsd : 0,
                            'bank_usd_credit_original' => $unitTransaction->type === 'purchase' ? $totalBcaUsdInIdr : 0,
                            'cash_idr_credit'          => $unitTransaction->type === 'purchase' ? $totalCash : 0,
                        ]
                    );
                }
            });

            return $this->responseSuccess(
                $billing->fresh(),
                'Billing updated successfully',
                200
            );
        } catch (ValidationException $err) {
            return $this->responseError($err->errors(), 'Validation failed', 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error($err->getMessage());

            return $this->responseError($err->getMessage(), 'Update failed', 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $billing = UnitTransactionBilling::with([
                'cashFlow.financeBillings.cash',
            ])->findOrFail($id);

            DB::transaction(function () use ($billing) {
                $cashFlow = $billing->cashFlow;

                // Hapus CashFlow terkait dan adjust balik nominal kas
                if ($cashFlow) {
                    if ($cashFlow->is_paid) {
                        $type        = $cashFlow->cash_flow_type;
                        $reverseType = $type === 'debet' ? 'credit' : 'debet';

                        $financeBillings = $cashFlow->financeBillings;
                        if ($financeBillings->isNotEmpty()) {
                            $companyId = $cashFlow->company_id;
                            foreach ($financeBillings as $item) {
                                $cash   = $item->cash;
                                $amount = (float) $item->amount;

                                if ($cash && $cash->company_id === $companyId && $amount > 0) {
                                    $cash->adjustAmount($amount, $reverseType);
                                }
                            }
                        }
                    }

                    if ($cashFlow->payment_proof) {
                        $this->destroyFile('cash_flow_proof/' . $cashFlow->payment_proof);
                    }

                    $cashFlow->delete();
                }

                $billing->delete();
            });

            return $this->responseSuccess($billing, 'Billing deleted successfully', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error($err->getMessage());

            return $this->responseError(null, 'Delete failed', 500);
        }
    }

    public function checkRightAmount(Request $request)
    {
        try {
            $validated = $request->validate([
                'company_id' => 'required|integer|exists:companies,id',
                'unit_transaction_id' => 'required|integer|exists:unit_transactions,id',
            ]);

            $unitTransaction = UnitTransaction::with([
                'unitTransactionItems.unitTransactionItemDetails',
                'unitTransactionItems.unitTransactionItemSales'
            ])
                ->findOrFail($validated['unit_transaction_id']);

            if ((int) $unitTransaction->warehouse->company_id !== (int) $validated['company_id']) {
                throw ValidationException::withMessages([
                    'company_id' => 'Company does not own this unit transaction.',
                ]);
            }

            $invalidItems = [];
            $summary = [];

            foreach ($unitTransaction->unitTransactionItems as $item) {

                if ($unitTransaction->type === 'purchase') {
                    $actualQty = $item->unitTransactionItemDetails->count();
                } else {
                    $actualQty = $item->unitTransactionItemSales->count();
                }

                $summary[] = [
                    'item_id' => $item->id,
                    'qty_input' => (int) $item->qty_total,
                    'qty_actual' => (int) $actualQty,
                    'is_valid' => (int) $item->qty_total === (int) $actualQty,
                ];

                if ($item->qty_total != $actualQty) {
                    $invalidItems[] = [
                        'item_id' => $item->id,
                        'difference' => $item->qty_total - $actualQty,
                    ];
                }
            }

            if (! empty($invalidItems)) {
                return $this->responseError((object) [
                    'is_valid' => false,
                    'invalid_items' => $invalidItems,
                    'summary' => $summary,
                ], 'Mismatch detected', 422);
            }

            return $this->responseSuccess((object) [
                'is_valid' => true,
                'summary' => $summary,
            ], 'Valid', 200);
        } catch (ValidationException $err) {
            return $this->responseError($err->errors(), 'Validation failed', 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            return $this->responseError($err->getMessage(), 'Check failed', 500);
        }
    }
}
