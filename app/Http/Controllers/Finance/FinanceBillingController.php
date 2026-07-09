<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Cash;
use App\Models\CashFlow;
use App\Models\FinanceBilling;
use App\Models\GoodsTransactionBilling;
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

            $currencyService = app(CurrencyService::class);
            $exchangeRate = (int) $currencyService->convertUsdToIdr('1');

            $data->getCollection()->transform(function ($item) use ($exchangeRate) {
                $cashFlow = $item->cashFlow;

                $grandTotal = $cashFlow ? ($cashFlow->debet > 0 ? $cashFlow->debet : $cashFlow->credit) : 0;
                $totalPaid = $cashFlow ? FinanceBilling::where('cash_flow_id', $cashFlow->id)->sum('amount_original') : 0;
                $remaining = $grandTotal - $totalPaid;

                $item->remaining_payment = $remaining;
                $item->remaining_payment_usd = $exchangeRate > 0 ? round($remaining / $exchangeRate, 2) : 0.0;

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

            $currencyService = app(CurrencyService::class);
            $exchangeRate = (int) $currencyService->convertUsdToIdr('1');

            $totalPaid = $payments->sum('amount_original');
            $cashFlow = CashFlow::where('unit_transaction_billing_id', $id)->first();
            $grandTotal = $cashFlow ? ($cashFlow->debet > 0 ? $cashFlow->debet : $cashFlow->credit) : 0;
            $remaining = $grandTotal - $totalPaid;

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
            $data->remaining_payment_usd = $exchangeRate > 0 ? round($remaining / $exchangeRate, 2) : 0.0;
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

            if (str_contains(strtolower($cash->code), 'usd')) {
                $currencyService = app(CurrencyService::class);
                $exchangeRate = (int) $currencyService->convertUsdToIdr('1');
                $amountOriginal = $this->calculateDecimalAmount($amount * $exchangeRate);
            } else {
                $amountOriginal = $this->calculateDecimalAmount($amount);
            }
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

            $currencyService = app(CurrencyService::class);
            $exchangeRate = (int) $currencyService->convertUsdToIdr('1');

            $itemArray = $item->toArray();
            $itemArray['remaining_payment'] = $cashFlow->remaining_payment;
            $itemArray['remaining_payment_usd'] = $exchangeRate > 0 ? round($cashFlow->remaining_payment / $exchangeRate, 2) : 0.0;

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
        return $this->updateItem($request, $id);
    }

    public function destroy(string $id)
    {
        try {
            DB::transaction(function () use ($id) {
                $payments = FinanceBilling::whereHas('cashFlow', function ($q) use ($id) {
                    $q->where('unit_transaction_billing_id', $id);
                })->get();
                foreach ($payments as $payment) {
                    if ($payment->payment_proof) {
                        $this->destroyFile('finance_billing_proof/' . $payment->payment_proof);
                    }
                    $payment->delete();
                }
            });

            return $this->responseSuccess([], 'Finance Billing successfully Deleted', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));

            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            return $this->responseError($err->getMessage(), 'Finance Billing Not Found or Failed Deleted', 500);
        }
    }

    public function addItem(Request $request, string $unit_transaction_billing_id)
    {
        try {
            $billing = UnitTransactionBilling::with('unitTransaction.warehouse')
                ->findOrFail($unit_transaction_billing_id);

            $companyId = $billing->unitTransaction->warehouse->company_id;

            $validated = $request->validate([
                'cash_id' => ['required', 'integer', 'exists:cashes,id', new RightCashRule($companyId)],
                'account_id' => ['nullable', 'integer', 'exists:accounts,id', new RightAccountRule()],
                'amount' => 'required|numeric|min:0.01',
                'payment_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
                'payment_at' => 'nullable|date',
                'note' => 'nullable|string',
            ]);

            if ($request->hasFile('payment_proof')) {
                $validated['payment_proof'] = $this->storeFile(
                    $request->file('payment_proof'),
                    'finance_billing_proof'
                );
            }

            $cash = Cash::findOrFail($validated['cash_id']);
            $amount = (float) $validated['amount'];

            if (str_contains(strtolower($cash->code), 'usd')) {
                $currencyService = app(CurrencyService::class);
                $exchangeRate = (int) $currencyService->convertUsdToIdr('1');
                $amountOriginal = $this->calculateDecimalAmount($amount * $exchangeRate);
            } else {
                $amountOriginal = $this->calculateDecimalAmount($amount);
            }
            $validated['amount_original'] = $amountOriginal;
            $validated['amount'] = $amount;

            $newPayment = $amountOriginal;
            $alreadyAllocated = FinanceBilling::whereHas('cashFlow', function ($q) use ($unit_transaction_billing_id) {
                $q->where('unit_transaction_billing_id', $unit_transaction_billing_id);
            })->sum('amount_original');
            
            $cashFlow = $billing->cashFlow;
            $remainingAllowed = $cashFlow ? $cashFlow->remaining_payment : $billing->grand_total;

            if ($newPayment > $remainingAllowed) {
                return $this->responseError(
                    'Payment amount exceeds remaining billing balance (' . number_format($remainingAllowed) . ').',
                    'Validation failed',
                    422
                );
            }

            $item = DB::transaction(function () use ($validated, $billing, $newPayment, $alreadyAllocated, $unit_transaction_billing_id, $companyId) {
                $itemData = $validated;

                // Ensure a CashFlow exists for this billing
                $cashFlow = $billing->cashFlow;
                if (!$cashFlow) {
                    $cashFlow = CashFlow::create([
                        'company_id' => $companyId,
                        'unit_transaction_billing_id' => $billing->id,
                        'code' => $billing->unitTransaction->code . '-payment',
                        'date' => $validated['payment_at'] ?? now(),
                        'note' => "Pelunasan Total " . $billing->unitTransaction->code,
                        'debet' => $billing->unitTransaction->type === 'sales' ? $billing->grand_total : 0,
                        'debet_original' => $billing->unitTransaction->type === 'sales' ? $billing->grand_total : 0,
                        'credit' => $billing->unitTransaction->type === 'purchase' ? $billing->grand_total : 0,
                        'credit_original' => $billing->unitTransaction->type === 'purchase' ? $billing->grand_total : 0,
                    ]);
                }

                $itemData['cash_flow_id'] = $cashFlow->id;

                $item = FinanceBilling::create($itemData);

                if (($alreadyAllocated + $newPayment) >= $billing->grand_total) {
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

                if ($cashFlow) {
                    $cashFlow->updateValidity();
                }

                return $item;
            });

            $currencyService = app(CurrencyService::class);
            $exchangeRate = (int) $currencyService->convertUsdToIdr('1');

            $cashFlow = $billing->cashFlow;
            $remainingAmount = $cashFlow ? $cashFlow->remaining_payment : 0;
            $remainingAmountUsd = $exchangeRate > 0 ? round($remainingAmount / $exchangeRate, 2) : 0.0;

            $itemArray = $item->toArray();
            $itemArray['remaining_amount'] = $remainingAmount;
            $itemArray['remaining_amount_usd'] = $remainingAmountUsd;

            return $this->responseSuccess($itemArray, 'Finance Billing Item created successfully', 201);
        } catch (ValidationException $e) {
            return $this->responseError($e->errors(), 'Validation failed', 422);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));

            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error While storing Finance Billing Item data : ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Finance Billing Item creation failed', 500);
        }
    }

    public function updateItem(Request $request, string $id)
    {
        try {
            $item = FinanceBilling::findOrFail($id);

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
            $remainingPayment = 0;
            $remainingPaymentUsd = 0.0;
 
            if ($cashFlow) {
                $currencyService = app(CurrencyService::class);
                $exchangeRate = (int) $currencyService->convertUsdToIdr('1');
 
                $grandTotal = $cashFlow->debet > 0 ? $cashFlow->debet : $cashFlow->credit;
                $totalPaid = FinanceBilling::where('cash_flow_id', $cashFlow->id)->sum('amount_original');
                $remainingPayment = $grandTotal - $totalPaid;
                $remainingPaymentUsd = $exchangeRate > 0 ? round($remainingPayment / $exchangeRate, 2) : 0.0;
            }

            $itemArray = $itemFresh->toArray();
            $itemArray['remaining_payment'] = $remainingPayment;
            $itemArray['remaining_payment_usd'] = $remainingPaymentUsd;

            return $this->responseSuccess($itemArray, 'Finance Billing Item updated successfully', 200);
        } catch (ValidationException $e) {
            return $this->responseError($e->errors(), 'Validation failed', 422);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));

            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error While updating Finance Billing Item data : ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Finance Billing Item update failed', 500);
        }
    }

    public function destroyItem(string $id)
    {
        try {
            $item = FinanceBilling::findOrFail($id);

            DB::transaction(function () use ($item) {
                if ($item->payment_proof) {
                    $this->destroyFile('finance_billing_proof/' . $item->payment_proof);
                }

                $cashFlow = $item->cashFlow;
                $item->delete();

                if ($cashFlow) {
                    $cashFlow->updateValidity();
                }
            });

            return $this->responseSuccess([], 'Finance Billing Item successfully Deleted', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));

            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            return $this->responseError($err->getMessage(), 'Finance Billing Item Not Found or Failed Deleted', 500);
        }
    }
}
