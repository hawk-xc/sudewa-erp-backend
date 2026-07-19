<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Cash;
use App\Models\CashFlow;
use App\Models\FinanceBilling;
use App\Models\UnitTransactionBilling;
use App\Models\UnitTypeDetailPpn;
use App\Repositories\AuthRepository;
use App\Rules\RightAccountRule;
use App\Rules\RightCashRule;
use App\Services\CurrencyService;
use App\Traits\CalculateDecimalAmount;
use App\Traits\FileTrait;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FinanceBillingController extends Controller
{
    use CalculateDecimalAmount, FileTrait, ResponseTrait;

    protected AuthRepository $authRepository;

    protected array $financeBillingTable;

    public function __construct(AuthRepository $ar)
    {
        $this->middleware(['permission:finance:list'])->only(['index', 'show']);
        $this->middleware(['permission:finance:create'])->only(['store']);
        $this->middleware(['permission:finance:edit'])->only(['update']);
        $this->middleware(['permission:finance:delete'])->only(['destroy']);

        $this->authRepository = $ar;

        $this->financeBillingTable = [
            'id',
            'uuid',
            'cash_flow_id',
            'cash_id',
            'account_id',
            'amount',
            'amount_original',
            'payment_proof',
            'payment_at',
            'note',
            'created_at',
        ];
    }

    public function index(Request $request)
    {
        try {
            $query = FinanceBilling::query()
                ->with([
                    'cashFlow.unitTransactionBilling:id,uuid,unit_transaction_id,grand_total,is_paid',
                    'cashFlow.unitTransactionBilling.unitTransaction:id,code',
                    'cash',
                ]);

            $query->select($this->financeBillingTable);

            if ($request->filled('search')) {
                $search = $request->search;
                $query->whereHas('cashFlow.unitTransactionBilling.unitTransaction', function ($q) use ($search) {
                    $q->where('code', 'like', "%$search%");
                })->orWhere('uuid', 'like', "%$search%");
            }

            if ($request->filled('company_id')) {
                $query->whereHas('cashFlow', function ($q) use ($request) {
                    $q->where('company_id', $request->company_id);
                });
            }

            if ($request->filled('cash_flow_id')) {
                $query->where('cash_flow_id', $request->cash_flow_id);
            }

            $sortBy = in_array($request->sort_by, $this->financeBillingTable) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $data = $query->paginate($request->per_page ?? 10);

            $data->getCollection()->transform(function ($item) {
                $cashFlow = $item->cashFlow;
                $unitTransactionBilling = $cashFlow?->unitTransactionBilling;

                $grandTotal = $cashFlow ? ($cashFlow->debet > 0 ? $cashFlow->debet : $cashFlow->credit) : 0;

                // Cek apakah billing ini memiliki pembayaran bca_usd
                $hasUsdPayment = $unitTransactionBilling
                    ? DB::table('cash_unit_transaction_billing_history')
                    ->join('unit_transaction_billing_histories', 'unit_transaction_billing_histories.id', '=', 'cash_unit_transaction_billing_history.unit_transaction_billing_history_id')
                    ->join('cashes', 'cashes.id', '=', 'cash_unit_transaction_billing_history.cash_id')
                    ->where('unit_transaction_billing_histories.unit_transaction_billing_id', $unitTransactionBilling->id)
                    ->where('cashes.code', 'bca_usd')
                    ->exists()
                    : false;

                if ($hasUsdPayment) {
                    $item->remaining_payment = 0;
                } else {
                    $totalPaid = $cashFlow ? FinanceBilling::where('cash_flow_id', $cashFlow->id)->sum('amount_original') : 0;
                    $item->remaining_payment = $grandTotal - $totalPaid;
                }

                return $item;
            });

            return $this->responseSuccess($data, 'Finance Billing list retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error Work While retrieved Finance Billing data : ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Finance Billing list retrieved Failed', 500);
        }
    }

    public function show(string $id)
    {
        try {
            $billing = UnitTransactionBilling::with([
                'unitTransaction',
                'unitTransactionBillingHistories',
            ])->findOrFail($id);

            $payments = FinanceBilling::with('cash')
                ->whereHas('cashFlow', function ($q) use ($id) {
                    $q->where('unit_transaction_billing_id', $id);
                })
                ->get();

            $totalPaid = $payments->sum('amount_original');
            $cashFlow = CashFlow::where('unit_transaction_billing_id', $id)->first();
            $grandTotal = $cashFlow ? ($cashFlow->debet > 0 ? $cashFlow->debet : $cashFlow->credit) : 0;

            $totalBca = $payments->filter(function ($item) {
                return $item->cash && $item->cash->code === 'bca_idr';
            })->sum('amount');

            $totalCash = $payments->filter(function ($item) {
                return $item->cash && $item->cash->code === 'cash_idr';
            })->sum('amount');

            $totalUsd = $payments->filter(function ($item) {
                return $item->cash && $item->cash->code === 'bca_usd';
            })->sum('amount');

            $totalUsdOriginal = $payments->filter(function ($item) {
                return $item->cash && $item->cash->code === 'bca_usd';
            })->sum('amount_original');

            // Cek apakah ada pembayaran bca_usd pada billing ini
            $hasUsdPayment = $payments->contains(function ($item) {
                return $item->cash && $item->cash->code === 'bca_usd';
            });

            // remaining hanya dari IDR; jika ada USD maka 0
            $totalIdrPaid = $payments->filter(function ($item) {
                return $item->cash && in_array($item->cash->code, ['bca_idr', 'cash_idr']);
            })->sum('amount_original');

            $remaining = $hasUsdPayment ? 0 : ($grandTotal - $totalIdrPaid);

            $data = new \stdClass();
            $data->id = $billing->id;
            $data->uuid = $billing->uuid;
            $data->unit_transaction_billing_id = $billing->id;
            $data->grand_total = $billing->grand_total;
            $data->is_paid = $billing->is_paid;
            $data->unit_transaction_billing = $billing;
            $data->finance_billings = $payments;
            $data->total_cash_payment = $totalCash;
            $data->total_bca_payment = $totalBca;
            $data->total_usd_payment = $totalUsd;
            $data->total_usd_payment_original = $totalUsdOriginal;
            $data->total_paid = $totalPaid;
            $data->remaining_payment = $remaining;
            $data->usd_payment_set = $hasUsdPayment;
            $data->total_payment_count = $payments->count();

            return $this->responseSuccess($data, 'Finance Billing retrieved successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));

            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            return $this->responseError($err->getMessage(), 'Finance Billing not found', 404);
        }
    }

    public function store(Request $request)
    {
        $companyId = CashFlow::findOrFail($request->cash_flow_id)->company_id;

        try {
            $validated = $request->validate([
                'cash_flow_id' => 'required|integer|exists:cash_flows,id',
                'cash_id' => ['required', 'integer', 'exists:cashes,id', new RightCashRule($companyId)],
                'account_id' => ['nullable', 'integer', 'exists:accounts,id', new RightAccountRule($companyId)],
                'amount' => 'required|numeric|min:0.01',
                'payment_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
                'payment_at' => 'nullable|date',
                'note' => 'nullable|string',
            ]);

            $cashFlow = CashFlow::findOrFail($validated['cash_flow_id']);
            $companyId = $cashFlow->company_id;

            if ($cashFlow->is_valid === true && $cashFlow->is_paid === true) {
                return $this->responseError(null, 'Cannot add payment because the cash flow is already paid and valid.', 422);
            }

            $rightCashRule = new RightCashRule($companyId);
            $validator = Validator::make($request->only('cash_id'), [
                'cash_id' => [$rightCashRule],
            ]);
            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            if ($request->hasFile('payment_proof')) {
                $validated['payment_proof'] = $this->storeFile(
                    $request->file('payment_proof'),
                    'finance_billing_proof'
                );
            }

            $cash = Cash::findOrFail($validated['cash_id']);
            $amount = (float) $validated['amount'];

            // USD diinput secara manual dalam IDR, tidak perlu konversi via API
            $amountOriginal = $this->calculateDecimalAmount($amount);
            $validated['amount_original'] = $amountOriginal;
            $validated['amount'] = $amount;
            $validated['cash_flow_id'] = $cashFlow->id;

            $remainingAllowed = $cashFlow->remaining_payment;
            if ($amountOriginal > $remainingAllowed) {
                return $this->responseError(
                    'Payment amount exceeds remaining billing balance (' . number_format($remainingAllowed) . ').',
                    'Validation failed',
                    422
                );
            }

            $item = DB::transaction(function () use ($validated, $cashFlow) {
                $item = FinanceBilling::create($validated);

                if ($cashFlow->unit_transaction_billing_id) {
                    $billing = $cashFlow->unitTransactionBilling;
                    $alreadyAllocated = FinanceBilling::whereHas('cashFlow', function ($q) use ($billing) {
                        $q->where('unit_transaction_billing_id', $billing->id);
                    })->sum('amount_original');

                    if ($alreadyAllocated >= $billing->grand_total) {
                        $unitTransaction = $billing->unitTransaction;
                        if ($unitTransaction) {
                            $unitTransaction->update([
                                'stock_state' => 'inbound_incoming_goods',
                            ]);

                            foreach ($unitTransaction->unitTransactionItems as $itemObj) {
                                $details = $unitTransaction->type === 'purchase'
                                    ? $itemObj->unitTransactionItemDetails
                                    : $itemObj->unitTypeSoldDetails;

                                foreach ($details as $detail) {
                                    $unitTransactionType = $unitTransaction->type;
                                    $type = 'ppn_' . $unitTransactionType;

                                    $exists = UnitTypeDetailPpn::where('unit_transaction_item_detail_id', $detail->id)
                                        ->where('type', $type)
                                        ->exists();

                                    if (! $exists) {
                                        UnitTypeDetailPpn::create([
                                            'unit_transaction_item_detail_id' => $detail->id,
                                            'unit_transaction_id' => $unitTransaction->id,
                                            'type' => $type,
                                        ]);
                                    }
                                }
                            }
                        }
                    }
                }

                $cashFlow->updateValidity();

                return $item;
            });

            $cashFlow->refresh();

            $cashFlow->load('financeBillings.cash');
            $hasUsdPayment = $cashFlow->financeBillings->contains(function ($fb) {
                return $fb->cash?->code === 'bca_usd';
            });

            $itemArray = $item->toArray();
            $itemArray['remaining_payment'] = $hasUsdPayment ? 0 : $cashFlow->remaining_payment;
            $itemArray['is_paid'] = $cashFlow->remaining_payment == 0;
            $itemArray['usd_payment_set'] = $hasUsdPayment;

            return $this->responseSuccess($itemArray, 'Finance Billing created successfully', 201);
        } catch (ValidationException $e) {
            return $this->responseError($e->errors(), 'Validation failed', 422);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));

            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error While storing Finance Billing data : ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Finance Billing creation failed', 500);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $item = FinanceBilling::findOrFail($id);
            $cashFlow = $item->cashFlow;

            if ($cashFlow && $cashFlow->is_valid === true && $cashFlow->is_paid === false) {
                return $this->responseError(null, 'Cannot update payment because the cash flow is already valid but unpaid.', 422);
            }

            $validated = $request->validate([
                'cash_id' => 'sometimes|integer|exists:cashes,id',
                'account_id' => 'sometimes|nullable|integer|exists:accounts,id',
                'amount' => 'sometimes|numeric|min:0',
                'payment_proof' => 'sometimes|nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
                'payment_at' => 'sometimes|nullable|date',
                'note' => 'sometimes|nullable|string',
            ]);

            if ($request->hasFile('payment_proof')) {
                if ($item->payment_proof) {
                    $this->destroyFile('finance_billing_proof/' . $item->payment_proof);
                }

                $validated['payment_proof'] = $this->storeFile(
                    $request->file('payment_proof'),
                    'finance_billing_proof'
                );
            }

            $oldCashId = $item->cash_id;
            $oldAmountOriginal = (float) $item->amount_original;
            $oldAmount = (float) $item->amount;

            $newCashId = array_key_exists('cash_id', $validated) ? $validated['cash_id'] : $oldCashId;
            $newAmount = array_key_exists('amount', $validated) ? (float) $validated['amount'] : $oldAmount;

            $cash = Cash::findOrFail($newCashId);

            if (array_key_exists('cash_id', $validated) || array_key_exists('amount', $validated)) {
                if (str_contains(strtolower($cash->code), 'usd')) {
                    $currencyService = app(CurrencyService::class);
                    $exchangeRate = (int) $currencyService->convertUsdToIdr('1');
                    $newAmountOriginal = $this->calculateDecimalAmount($newAmount * $exchangeRate);
                } else {
                    $newAmountOriginal = $this->calculateDecimalAmount($newAmount);
                }
                $validated['amount_original'] = $newAmountOriginal;
                $validated['amount'] = $newAmount;
            } else {
                $newAmountOriginal = $oldAmountOriginal;
            }

            $cashFlow = $item->cashFlow;
            if ($cashFlow && (array_key_exists('cash_id', $validated) || array_key_exists('amount', $validated))) {
                $remainingAllowed = $cashFlow->remaining_payment + $oldAmountOriginal;
                if ($newAmountOriginal > $remainingAllowed) {
                    return $this->responseError(
                        'Payment amount exceeds remaining billing balance (' . number_format($remainingAllowed) . ').',
                        'Validation failed',
                        422
                    );
                }
            }

            DB::transaction(function () use ($item, $validated) {
                $item->update($validated);

                $cashFlow = $item->cashFlow;
                if ($cashFlow) {
                    $cashFlow->update([
                        'note' => $item->note ?? $cashFlow->note,
                        'date' => $item->payment_at ?? $cashFlow->date,
                    ]);
                    $cashFlow->updateValidity();
                }
            });

            $itemFresh = $item->fresh();
            $cashFlow = $itemFresh->cashFlow;

            // Cek apakah billing memiliki pembayaran bca_usd
            if ($cashFlow) {
                $cashFlow->load('financeBillings.cash');
            }
            $hasUsdPayment = $cashFlow
                ? $cashFlow->financeBillings->contains(function ($fb) {
                    return $fb->cash?->code === 'bca_usd';
                })
                : false;

            $itemArray = $itemFresh->toArray();
            $itemArray['remaining_payment'] = $hasUsdPayment ? 0 : ($cashFlow ? $cashFlow->remaining_payment : 0);
            $itemArray['usd_payment_set'] = $hasUsdPayment;

            return $this->responseSuccess($itemArray, 'Finance Billing updated successfully', 200);
        } catch (ValidationException $e) {
            return $this->responseError($e->errors(), 'Validation failed', 422);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));

            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error While updating Finance Billing data : ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Finance Billing update failed', 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $financeBilling = FinanceBilling::findOrFail((int) $id);
            $cashFlow = $financeBilling->cashFlow;

            if ($cashFlow && $cashFlow->is_paid === true) {
                return $this->responseError(null, 'Cannot delete payment because the cash flow is already valid but unpaid.', 422);
            }

            $remainingPayment = 0;
            $cashFlowId = $cashFlow->id;
            DB::transaction(function () use ($financeBilling, $cashFlowId, &$remainingPayment) {
                $financeBilling->delete();
                $remainingPayment = CashFlow::findOrFail((int) $cashFlowId)->remaining_payment;
            });

            return $this->responseSuccess(['remaining_payment' => $remainingPayment], 'Finance Billing successfully Deleted', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));

            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            return $this->responseError($err->getMessage(), 'Finance Billing Not Found or Failed Deleted', 500);
        }
    }
}
