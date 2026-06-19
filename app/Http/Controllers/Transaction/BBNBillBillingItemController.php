<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\BBNBillBilling;
use App\Models\BBNBillBillingItem;
use App\Models\TransactionFlow;
use App\Models\Cash;
use App\Models\FinanceBBNBilling;
use App\Rules\RightCashRule;
use App\Traits\GlobalCodeNumberTrait;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BBNBillBillingItemController extends Controller
{
    use ResponseTrait, GlobalCodeNumberTrait;

    protected array $bbnBillBillingItemTable;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only('update');
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);

        $this->bbnBillBillingItemTable = [
            'id',
            'uuid',
            'bbn_bill_billing_id',
            'paid_date',
            'cash_id',
            'amount',
            'created_at',
        ];
    }

    public function index(Request $request)
    {
        $query = BBNBillBillingItem::with(['bbnBillBilling', 'cash']);

        try {
            foreach ($this->bbnBillBillingItemTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $sortBy = in_array($request->sort_by, $this->bbnBillBillingItemTable) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $data = $query->orderBy($sortBy, $sortOrder)->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'BBN Bill Billing Item list retrieved successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error retrieving BBN Bill Billing Item: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve BBN Bill Billing Item list', 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'bbn_bill_billing_id' => 'required|exists:bbn_bill_billings,id',
            'paid_date' => 'required|date',
            'cash_id' => [
                'required',
                'exists:cashes,id',
                new RightCashRule(fn() => 3),
            ],
            'amount' => 'required|numeric|min:1',
        ]);

        $billing = BBNBillBilling::findOrFail($validated['bbn_bill_billing_id']);
        $remainingBefore = $billing->getRemainingAmount();

        if ((int) $validated['amount'] > (int) $remainingBefore) {
            return $this->responseError('Payment amount exceeds remaining balance. Remaining balance is ' . number_format($remainingBefore), 'Validation failed', 422);
        }

        try {
            $data = DB::transaction(function () use ($validated, $billing) {
                $item = BBNBillBillingItem::create($validated);
                
                $billing->refresh();
                $remainingPayment = $billing->getRemainingAmount();
                $item->remaining_payment = $remainingPayment;

                $cash = Cash::findOrFail($validated['cash_id']);

                if ($remainingPayment <= 0) {
                    $billingItems = BBNBillBillingItem::with('cash')
                        ->where('bbn_bill_billing_id', $billing->id)
                        ->get();

                    $bankUsdDebit = 0;
                    $bankIdrDebit = 0;
                    $cashIdrDebit = 0;

                    foreach ($billingItems as $bItem) {
                        $bCash = $bItem->cash;
                        if ($bCash) {
                            if ($bCash->type === 'bank') {
                                if (str_contains(strtolower($bCash->code), 'usd')) {
                                    $bankUsdDebit += $bItem->amount;
                                } else {
                                    $bankIdrDebit += $bItem->amount;
                                }
                            } else {
                                $cashIdrDebit += $bItem->amount;
                            }
                        }
                    }

                    if ($bankUsdDebit == 0 && $bankIdrDebit == 0 && $cashIdrDebit == 0) {
                        if ($cash->type === 'bank') {
                            if (str_contains(strtolower($cash->code), 'usd')) {
                                $bankUsdDebit = $item->amount;
                            } else {
                                $bankIdrDebit = $item->amount;
                            }
                        } else {
                            $cashIdrDebit = $item->amount;
                        }
                    }

                    TransactionFlow::updateOrCreate(
                        [
                            'company_id' => $cash->company_id,
                            'description' => "Pelunasan BBN Bill: " . ($billing->bbnBill?->code ?? ''),
                        ],
                        [
                            'transaction_date' => $item->paid_date,
                            'name' => $billing->bbnBill?->ditlantasProcess?->vendor?->name ?? null,
                            'bank_usd_debit' => $bankUsdDebit,
                            'bank_idr_debit' => $bankIdrDebit,
                            'cash_idr_debit' => $cashIdrDebit,
                        ]
                    );
                }

                FinanceBBNBilling::create([
                    'bbn_bill_id' => $billing->bbn_bill_id,
                    'cash_id' => null,
                    'amount' => $item->amount,
                ]);

                return $item;
            });
            
            return $this->responseSuccess($data, 'BBN Bill Billing Item created successfully', 201);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error creating BBN Bill Billing Item: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'BBN Bill Billing Item creation failed', 500);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $item = BBNBillBillingItem::findOrFail($id);

            $validated = $request->validate([
                'bbn_bill_billing_id' => 'sometimes|required|exists:bbn_bill_billings,id',
                'paid_date' => 'sometimes|required|date',
                'cash_id' => [
                    'sometimes',
                    'required',
                    'exists:cashes,id',
                    new RightCashRule(fn () => BBNBillBilling::findOrFail($request->bbn_bill_billing_id ?? $item->bbn_bill_billing_id)?->bbnBill?->dealer?->company_id),
                ],
                'amount' => 'sometimes|required|numeric',
            ]);
            $billing = $item->bbnBillBilling;

            if ($request->has('amount')) {
                // Calculate remaining balance excluding current item
                $otherPayments = $billing->bbnBillBillingItems()->where('id', '!=', $id)->sum('amount');
                $remaining = $billing->total_payment - $otherPayments;

                if ($validated['amount'] > $remaining) {
                    return $this->responseError('Updated amount exceeds the remaining balance of ' . number_format($remaining), 'Validation failed', 422);
                }
            }

            $item->update($validated);
            
            $updatedItem = $item->fresh();
            $updatedItem->remaining_payment = $billing->getRemainingAmount();

            // Sync BBNBill status: if not fully paid, clear paid_date
            if ($billing->getRemainingAmount() > 0) {
                $billing->bbnBill->update(['paid_date' => null]);
            }

            return $this->responseSuccess($updatedItem, 'BBN Bill Billing Item updated successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error updating BBN Bill Billing Item: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'BBN Bill Billing Item update failed', 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $item = BBNBillBillingItem::with('bbnBillBilling.bbnBill')->findOrFail($id);

            if ($item->bbnBillBilling->bbnBill->is_paid) {
                return $this->responseError('Cannot delete a payment item for a BBN Bill that has already been paid', 'Validation failed', 422);
            }

            $billing = $item->bbnBillBilling;
            $item->delete();

            // Sync BBNBill status: if not fully paid, clear paid_date
            if ($billing->getRemainingAmount() > 0) {
                $billing->bbnBill->update(['paid_date' => null]);
            }

            return $this->responseSuccess(null, 'BBN Bill Billing Item deleted successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error deleting BBN Bill Billing Item: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'BBN Bill Billing Item deletion failed', 500);
        }
    }
}
