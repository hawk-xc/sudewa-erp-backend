<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\FinanceBilling;
use App\Models\FinanceBillingItem;
use App\Models\UnitTransactionBilling;
use App\Models\UnitTypeDetailPpn;
use App\Repositories\AuthRepository;
use App\Traits\FileTrait;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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
            'bca_payment_amount',
            'bca_payment_usd_amount',
            'cash_payment_amount',
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
                    'financeBillingItems'
                ]);

            $query->select($this->financeBillingTable);

            if ($request->filled('search')) {
                $search = $request->search;
                $query->whereHas('unitTransactionBilling.unitTransaction', function ($q) use ($search) {
                    $q->where('code', 'like', "%$search%");
                })->orWhere('uuid', 'like', "%$search%");
            }

            $sortBy = in_array($request->sort_by, $this->financeBillingTable) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $data = $query->paginate($request->per_page ?? 10);

            $data->getCollection()->transform(function ($item) {
                $items = $item->financeBillingItems;
                $totalCash = $items->sum('cash_payment_amount');
                $totalBca = $items->sum('bca_payment_amount');
                $totalPaid = $totalCash + $totalBca;
                $remaining = ($item->unitTransactionBilling->grand_total ?? 0) - $totalPaid;

                $item->remaining_payment = $remaining;

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
                'financeBillingItems'
            ])
            ->select($this->financeBillingTable)
            ->findOrFail($id);

            $items = $data->financeBillingItems;

            $totalCash = $items->sum('cash_payment_amount');
            $totalBca = $items->sum('bca_payment_amount');
            $totalUsd = $items->sum('bca_payment_usd_amount');

            $totalPaid = $totalCash + $totalBca;
            $remaining = ($data->unitTransactionBilling->grand_total ?? 0) - $totalPaid;

            $data->total_cash_payment = $totalCash;
            $data->total_bca_payment = $totalBca;
            $data->total_usd_payment = $totalUsd;
            $data->total_paid = $totalPaid;
            $data->remaining_payment = $remaining;
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
        try {
            $validated = $request->validate([
                'bca_payment_amount' => 'nullable|integer|min:0|required_without:cash_payment_amount',
                'bca_payment_usd_amount' => 'nullable|integer|min:0',
                'cash_payment_amount' => 'nullable|integer|min:0|required_without:bca_payment_amount',
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

            $financeBilling = FinanceBilling::with([
                'financeBillingItems',
                'unitTransactionBilling.unitTransaction.unitTransactionItems.unitTransactionItemDetails',
                'unitTransactionBilling.unitTransaction.unitTransactionItems.unitTypeSoldDetails'
            ])->findOrFail($unit_transaction_billing_id);
            $newPayment = ($validated['cash_payment_amount'] ?? 0) + ($validated['bca_payment_amount'] ?? 0);
            
            $alreadyAllocated = $financeBilling->financeBillingItems->sum(function ($item) {
                return ($item->cash_payment_amount ?? 0) + ($item->bca_payment_amount ?? 0);
            });
            
            $remainingAllowed = $financeBilling->grand_total - $alreadyAllocated;

            if ($newPayment > $remainingAllowed) {
                return $this->responseError(
                    "Payment amount exceeds remaining billing balance (" . number_format($remainingAllowed) . ").",
                    'Validation failed',
                    422
                );
            }

            $item = DB::transaction(function () use ($validated, $financeBilling, $newPayment, $alreadyAllocated) {
                // 1. Create the item
                $itemData = $validated;
                $itemData['finance_billing_id'] = $financeBilling->id;
                $item = FinanceBillingItem::create($itemData);

                // 2. Check if fully allocated
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
            $totalAllocatedNow = $financeBillingFresh->financeBillingItems->sum(function ($item) {
                return ($item->cash_payment_amount ?? 0) + ($item->bca_payment_amount ?? 0);
            });
            $remainingAmount = $financeBillingFresh->grand_total - $totalAllocatedNow;

            $itemArray = $item->toArray();
            $itemArray['remaining_amount'] = $remainingAmount;

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
                'bca_payment_amount' => 'sometimes|nullable|integer|min:0',
                'bca_payment_usd_amount' => 'sometimes|nullable|integer|min:0',
                'cash_payment_amount' => 'sometimes|nullable|integer|min:0',
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

                DB::transaction(function () use ($item, $validated) {
                $item->update($validated);
                
                $financeBilling = $item->financeBilling;
                $cashFlow = $financeBilling->cashFlow;

                if ($cashFlow) {
                    $newAmount = ($item->cash_payment_amount ?? 0) + ($item->bca_payment_amount ?? 0);
                    $cashFlow->update([
                        'debet' => $cashFlow->debet > 0 ? $newAmount : 0,
                        'credit' => $cashFlow->credit > 0 ? $newAmount : 0,
                        'note' => $item->note ?? $cashFlow->note,
                        'date' => $item->payment_at ?? $cashFlow->date,
                    ]);
                    
                    $financeBilling->update([
                        'grand_total' => $newAmount,
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
                $item->delete();
                $this->updateValidity($financeBilling);
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
        
        $totalPaid = $financeBilling->financeBillingItems->sum(function($item) {
            return $item->bca_payment_amount + $item->cash_payment_amount;
        });

        $grandTotal = $financeBilling->unitTransactionBilling->grand_total;

        $financeBilling->update([
            'is_valid' => $totalPaid >= $grandTotal
        ]);
    }
}

