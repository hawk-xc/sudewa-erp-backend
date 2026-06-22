<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\UnitTransaction;
use App\Models\UnitTransactionBilling;
use App\Models\FinanceBilling;
use App\Traits\GlobalCodeNumberTrait;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UnitTransactionBillingController extends Controller
{
    use ResponseTrait, GlobalCodeNumberTrait;

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
                $item->total_paid = $item->getTotalPaid();
                $item->remaining_payment = $item->getRemainingPayment();
                $item->remaining_payment_usd = $item->getRemainingPaymentUsd();

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

            $totalPaid = $totalCash + $totalBca + $data->getTotalBcaUsdPaymentInIdr();

            $remaining = $data->grand_total - $totalPaid;

            $totalPaymentCount = $histories->count();

            $data->total_cash_payment = $totalCash;
            $data->total_bca_payment = $totalBca;

            $data->total_paid = $totalPaid;
            $data->remaining_payment = $remaining;
            $data->remaining_payment_usd = $data->getRemainingPaymentUsd();

            $data->total_usd_payment = $totalUsd;

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

            // unit type detail checker
            // foreach ($unitTransaction->unitTransactionItems as $item) {
            //     $actualQty = $unitTransaction->type === 'purchase'
            //         ? $item->unitTransactionItemDetails->count()
            //         : $item->unitTransactionItemSales->count();

            //     if ((int) $item->qty_total !== (int) $actualQty) {
            //         throw ValidationException::withMessages([
            //             "message" => 'The unit transaction items are invalid. Please ensure all items have the correct amount of details/sales records.',
            //             "hint" => "unit transaction item detail count not filled correct with unit transcation item qty total"
            //         ]);
            //     }
            // }

            // get bruto total
            // if ($unitTransaction->type === 'purchase') {
            //     $grandTotal = $unitTransaction->getBrutoAmountActual();
            // } else {
            $grandTotal = $unitTransaction->getBrutoAmount();
            // }

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
            $billing = UnitTransactionBilling::with('unitTransaction')->findOrFail($id);

            $validated = $request->validate([
                'amount' => 'required|numeric|min:1',
                'payment_method' => 'required|in:cash,bca',
                'payment_at' => 'nullable|date',
            ]);

            $amount = $validated['amount'];

            $totalPaid = $billing->unitTransactionBillingHistories()->sum('amount');

            $newTotalPaid = $totalPaid + $amount;

            if ($newTotalPaid > $billing->grand_total) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment exceeds remaining amount.',
                ]);
            }

            DB::transaction(function () use ($billing, $validated, $newTotalPaid) {
                $billing->unitTransactionBillingHistories()->create([
                    'amount' => $validated['amount'],
                    'payment_method' => $validated['payment_method'],
                    'payment_at' => $validated['payment_at'] ?? now(),
                ]);

                $remaining = $billing->grand_total - $newTotalPaid;

                $billing->update([
                    'total_paid' => $newTotalPaid,
                    'remaining_payment' => $remaining,
                    'is_paid' => $remaining <= 0,
                    'last_payment_at' => now(),
                ]);

                if ($remaining <= 0) {
                    $billing->unitTransaction->update([
                        'stock_state' => 'inbound_purcase_order',
                    ]);
                }
            });

            $billingFresh = $billing->fresh('unitTransactionBillingHistories');
            $billingFresh->remaining_payment = $billingFresh->getRemainingPayment();
            $billingFresh->remaining_payment_usd = $billingFresh->getRemainingPaymentUsd();

            return $this->responseSuccess(
                $billingFresh,
                'Payment added successfully',
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
            $billing = UnitTransactionBilling::findOrFail($id);

            DB::transaction(fn () => $billing->delete());

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
