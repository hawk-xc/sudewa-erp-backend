<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\FinanceBilling;
use App\Models\FinanceBillingItem;
use App\Models\UnitTypeDetailPpn;
use App\Models\Cash;
use App\Repositories\AuthRepository;
use App\Rules\RightCashRule;
use App\Rules\RightAccountRule;
use App\Traits\FileTrait;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Services\CurrencyService;

class FinanceBillingController extends Controller
{
    use FileTrait, ResponseTrait;

    protected AuthRepository $authRepository;

    protected array $financeBillingTable;
    protected array $financeBillingItemTable;

    public function __construct(AuthRepository $ar)
    {
        $this->middleware(['permission:finance:list'])->only(['index', 'show']);
        $this->middleware(['permission:finance:create'])->only(['addItem']);
        $this->middleware(['permission:finance:edit'])->only(['update', 'updateItem']);
        $this->middleware(['permission:finance:delete'])->only(['destroy', 'destroyItem']);

        $this->authRepository = $ar;

        $this->financeBillingTable = [
            'id',
            'uuid',
            'unit_transaction_billing_id',
            'last_payment_at',
            'is_valid',
            'created_at'
        ];

        $this->financeBillingItemTable = [
            'id',
            'finance_billing_id',
            'cash_id',
            'account_id',
            'amount',
            'amount_original',
            'payment_proof',
            'payment_at',
            'note',
            'created_at'
        ];
    }

    public function index(Request $request)
    {
        try {
            $query = FinanceBilling::query()
                ->with([
                    'unitTransactionBilling:id,uuid,unit_transaction_id,grand_total,is_paid',
                    'unitTransactionBilling.unitTransaction:id,code',
                    'financeBillingItems.cash'
                ]);

            $query->select($this->financeBillingTable);

            if ($request->filled('search')) {
                $search = $request->search;
                $query->whereHas('unitTransactionBilling.unitTransaction', function ($q) use ($search) {
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
                $items = $item->financeBillingItems;
                $totalPaid = $items->sum('amount_original');
                $remaining = ($item->unitTransactionBilling->grand_total ?? 0) - $totalPaid;

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
            $data = FinanceBilling::with([
                'unitTransactionBilling',
                'unitTransactionBilling.unitTransaction',
                'unitTransactionBilling.unitTransactionBillingHistories',
                'financeBillingItems.cash'
            ])
                ->select($this->financeBillingTable)
                ->findOrFail($id);

            $items = $data->financeBillingItems;

            $totalCash = $items->filter(fn($i) => $i->cash && $i->cash->code === 'cash_idr')->sum('amount');
            $totalBca = $items->filter(fn($i) => $i->cash && $i->cash->code === 'bca_idr')->sum('amount');
            $totalUsd = $items->filter(fn($i) => $i->cash && $i->cash->code === 'bca_usd')->sum('amount_original');
            $totalUsdOriginal = $items->filter(fn($i) => $i->cash && $i->cash->code === 'bca_usd')->sum('amount');

            $totalPaid = $items->sum('amount_original');
            $remaining = ($data->unitTransactionBilling->grand_total ?? 0) - $totalPaid;

            $currencyService = app(CurrencyService::class);
            $exchangeRate = (int) $currencyService->convertUsdToIdr('1');
            $remainingUsd = $exchangeRate > 0 ? round($remaining / $exchangeRate, 2) : 0.0;

            $data->total_cash_payment = $totalCash;
            $data->total_bca_payment = $totalBca;
            $data->total_usd_payment = $totalUsd;
            $data->total_usd_payment_original = $totalUsdOriginal;
            $data->total_paid = $totalPaid;
            $data->remaining_payment = $remaining;
            $data->remaining_payment_usd = $remainingUsd;
            $data->total_payment_count = $items->count();

            return $this->responseSuccess($data, 'Finance Billing retrieved successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            return $this->responseError($err->getMessage(), 'Finance Billing not found', 404);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $financeBilling = FinanceBilling::where('unit_transaction_billing_id', $id)->firstOrFail();

            $validated = $request->validate([
                'last_payment_at' => 'nullable|date',
            ]);

            DB::transaction(function () use ($financeBilling, $validated) {
                $financeBilling->update($validated);
            });

            return $this->responseSuccess($financeBilling->fresh(), 'Finance Billing updated successfully', 200);
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
            $financeBilling = FinanceBilling::where('unit_transaction_billing_id', $id)->firstOrFail();

            DB::transaction(function () use ($financeBilling) {
                $financeBilling->delete();
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
        $financeBilling = FinanceBilling::with([
            'financeBillingItems',
            'unitTransactionBilling.unitTransaction.unitTransactionItems.unitTransactionItemDetails',
            'unitTransactionBilling.unitTransaction.unitTransactionItems.unitTypeSoldDetails'
        ])->findOrFail($unit_transaction_billing_id);

        $companyId = $financeBilling->cashFlow->company->id;

        try {
            $validated = $request->validate([
                'cash_id' => ['required', 'integer', 'exists:cashes,id', new RightCashRule($companyId)],
                'account_id' => ['nullable', 'integer', 'exists:accounts,id', new RightAccountRule($companyId)],
                'amount' => 'required|numeric|min:0',
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
                if (!$exchangeRate) {
                    return $this->responseError([], 'Failed to convert USD to IDR via Unirate API.', 500);
                }
                $amountOriginal = (int) ($amount * $exchangeRate);
            } else {
                $amountOriginal = (int) $amount;
            }
            $validated['amount_original'] = $amountOriginal;
            $validated['amount'] = $amount;

            $newPayment = $amountOriginal;
            $alreadyAllocated = $financeBilling->financeBillingItems->sum('amount_original');
            $remainingAllowed = $financeBilling->grand_total - $alreadyAllocated;

            if ($newPayment > $remainingAllowed) {
                return $this->responseError(
                    "Payment amount exceeds remaining billing balance (" . number_format($remainingAllowed) . ").",
                    'Validation failed',
                    422
                );
            }

            $item = DB::transaction(function () use ($validated, $financeBilling, $newPayment, $alreadyAllocated, $cash, $amountOriginal) {
                // 1. Create the item
                $itemData = $validated;
                $itemData['finance_billing_id'] = $financeBilling->id;
                $item = FinanceBillingItem::create($itemData);

                if (($alreadyAllocated + $newPayment) >= $financeBilling->grand_total) {
                    $financeBilling->update(['is_valid' => true]);

                    $utBilling = $financeBilling->unitTransactionBilling;
                    if ($utBilling && $utBilling->unitTransaction) {
                        $unitTransaction = $utBilling->unitTransaction;

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

                                if (!$exists) {
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

                return $item;
            });

            $financeBillingFresh = $financeBilling->fresh('financeBillingItems');
            $totalAllocatedNow = $financeBillingFresh->financeBillingItems->sum('amount_original');
            $remainingAmount = $financeBillingFresh->grand_total - $totalAllocatedNow;

            $currencyService = app(CurrencyService::class);
            $exchangeRate = (int) $currencyService->convertUsdToIdr('1');
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
            $item = FinanceBillingItem::findOrFail($id);

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
                    if (!$exchangeRate) {
                        return $this->responseError([], 'Failed to convert USD to IDR via Unirate API.', 500);
                    }
                    $newAmountOriginal = (int) ($newAmount * $exchangeRate);
                } else {
                    $newAmountOriginal = (int) $newAmount;
                }
                $validated['amount_original'] = $newAmountOriginal;
                $validated['amount'] = $newAmount;
            } else {
                $newAmountOriginal = $oldAmountOriginal;
            }

            DB::transaction(function () use ($item, $validated) {
                $financeBilling = $item->financeBilling;
                $cashFlow = $financeBilling->cashFlow;

                $item->update($validated);

                $financeBilling = $item->financeBilling;
                $cashFlow = $financeBilling->cashFlow;

                if ($cashFlow) {
                    $newAmountVal = $item->amount_original;
                    $cashFlow->update([
                        'debet' => $cashFlow->debet > 0 ? $newAmountVal : 0,
                        'credit' => $cashFlow->credit > 0 ? $newAmountVal : 0,
                        'note' => $item->note ?? $cashFlow->note,
                        'date' => $item->payment_at ?? $cashFlow->date,
                    ]);

                    $financeBilling->update([
                        'grand_total' => $newAmountVal,
                        'last_payment_at' => $item->payment_at
                    ]);
                }

                $this->updateValidity($financeBilling);
            });

            return $this->responseSuccess($item->fresh(), 'Finance Billing Item updated successfully', 200);
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
            $item = FinanceBillingItem::findOrFail($id);

            DB::transaction(function () use ($item) {
                if ($item->payment_proof) {
                    $this->destroyFile('finance_billing_proof/' . $item->payment_proof);
                }

                $financeBilling = $item->financeBilling;
                $cashFlow = $financeBilling->cashFlow;



                $item->delete();
                $financeBilling->update(['is_valid' => false]);
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

    private function updateValidity(FinanceBilling $financeBilling)
    {
        $financeBilling->load(['unitTransactionBilling', 'financeBillingItems']);

        $totalPaid = $financeBilling->financeBillingItems->sum('amount_original');
        $grandTotal = $financeBilling->unitTransactionBilling->grand_total;

        $financeBilling->update([
            'is_valid' => $totalPaid >= $grandTotal
        ]);
    }
}
