<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\FinanceBilling;
use App\Models\UnitTransactionBilling;
use App\Models\UnitTransactionBillingHistory;
use App\Models\UnitTypeDetailPpn;
use App\Traits\FileTrait;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UnitTransactionBillingHistoryController extends Controller
{
    use FileTrait, ResponseTrait;

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
            ]);

            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('uuid', 'like', "%$search%")
                        ->orWhere('cash_payment_amount', 'like', "%$search%")
                        ->orWhere('bca_payment_amount', 'like', "%$search%");
                });
            }

            return $this->responseSuccess(
                $query->paginate($request->per_page ?? 10),
                'Billing history retrieved successfully',
                200
            );

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
            ])->findOrFail($id);

            return $this->responseSuccess($data, 'Billing history retrieved successfully', 200);

        } catch (Exception $err) {
            return $this->responseError($err->getMessage(), 'Data not found', 404);
        }
    }

    public function store(Request $request)
    {
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
                'unitTransaction.unitTransactionItems.unitTransactionItemDetails',
            ])->findOrFail($validated['unit_transaction_billing_id']);

            $bca = $validated['bca_payment_amount'] ?? 0;
            $cash = $validated['cash_payment_amount'] ?? 0;

            $paymentTotal = $bca + $cash;

            if ($paymentTotal <= 0) {
                throw ValidationException::withMessages([
                    'payment' => 'Payment amount must be greater than 0.',
                ]);
            }

            $totalPaidBefore =
                $billing->unitTransactionBillingHistories()->sum('bca_payment_amount') +
                $billing->unitTransactionBillingHistories()->sum('cash_payment_amount');

            $newTotalPaid = $totalPaidBefore + $paymentTotal;

            if ($newTotalPaid > $billing->grand_total) {
                throw ValidationException::withMessages([
                    'payment' => 'Total payment exceeds grand total.',
                ]);
            }

            DB::transaction(function () use ($billing, $validated, $newTotalPaid) {
                UnitTransactionBillingHistory::create([
                    'unit_transaction_billing_id' => $billing->id,
                    'bca_payment_amount' => $validated['bca_payment_amount'] ?? 0,
                    'bca_payment_usd_amount' => $validated['bca_payment_usd_amount'] ?? 0,
                    'cash_payment_amount' => $validated['cash_payment_amount'] ?? 0,
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

                if ($remaining <= 0) {

                    // $billing->unitTransaction->update([
                    //     'stock_state' => 'inbound_receipt',
                    // ]);

                    // Create Finance Billing Data
                    FinanceBilling::create([
                        'unit_transaction_billing_id' => $billing->id,
                        'grand_total' => $billing->grand_total,
                        'last_payment_at' => now(),
                        'is_valid' => false,
                    ]);

                    foreach ($billing->unitTransaction->unitTransactionItems as $item) {

                        $details = $billing->unitTransaction->type === 'purchase'
                            ? $item->unitTransactionItemDetails
                            : $item->unitTypeSoldDetails;

                        foreach ($details as $detail) {

                            $unitTransactionType = $billing->unitTransaction->type;

                            $type = 'ppn_'.$unitTransactionType;

                            $exists = UnitTypeDetailPpn::where('unit_transaction_item_detail_id', $detail->id)
                                ->where('type', $type)
                                ->exists();

                            if (! $exists) {
                                UnitTypeDetailPpn::create([
                                    'unit_transaction_item_detail_id' => $detail->id,
                                    'unit_transaction_id' => $billing->unitTransaction->id,
                                    'type' => $type,
                                ]);
                            }
                        }
                    }
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
        } catch (Exception $err) {
            Log::error($err->getMessage());

            return $this->responseError($err->getMessage(), 'Create failed', 500);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $history = UnitTransactionBillingHistory::findOrFail($id);

            $validated = $request->validate([
                'note' => 'nullable|string',
                'payment_proof' => 'nullable|string',
            ]);

            $history->update($validated);

            return $this->responseSuccess($history, 'History updated successfully', 200);

        } catch (Exception $err) {
            return $this->responseError($err->getMessage(), 'Update failed', 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $history = UnitTransactionBillingHistory::findOrFail($id);

            DB::transaction(function () use ($history) {
                $history->delete();
            });

            return $this->responseSuccess([], 'History deleted successfully', 200);

        } catch (Exception $err) {
            return $this->responseError($err->getMessage(), 'Delete failed', 500);
        }
    }
}
