<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\FinanceRefund;
use App\Models\UnitTransactionRefund;
use App\Models\UnitTransaction;
use App\Models\UnitTransactionItemDetail;
use App\Models\WarehouseActivity;
use App\Models\WarehouseMovement;
use App\Traits\RefundTrait;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UnitTransactionRefundController extends Controller
{
    use ResponseTrait, RefundTrait;

    protected $refundTable;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only('update');
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);

        $this->refundTable = [
            'id',
            'uuid',
            'unit_transaction_id',
            'code',
            'refund_date',
            'refund_amount',
            'note',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * List unit transaction refunds.
     */
    public function index(Request $request)
    {
        try {
            $query = UnitTransactionRefund::query();

            $query->select($this->refundTable)
                ->with([
                    'unitTransaction:id,uuid,code,type',
                ])
                ->withSum('unitTransactionRefundPayments as total_paid', 'amount')
                ->withCount('unitTransactionItemDetails as total_qty');

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%$search%")
                        ->orWhere('note', 'like', "%$search%")
                        ->orWhereHas('unitTransaction', function ($q) use ($search) {
                            $q->where('code', 'like', "%$search%");
                        });
                });
            }

            $sortBy = in_array($request->sort_by, $this->refundTable) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
            $query->orderBy($sortBy, $sortOrder);

            $data = $query->paginate($request->per_page ?? 10)
                ->through(function ($item) {
                    $item->total_payable = (int) $item->refund_amount;
                    $item->total_paid = (int) $item->total_paid;
                    $item->remaining_payment = $item->total_payable - $item->total_paid;
                    $item->total_qty = (int) $item->total_qty;
                    return $item;
                });

            return $this->responseSuccess($data, 'Unit transaction refunds retrieved successfully', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error('Error while retrieving unit transaction refunds: ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Failed to retrieve unit transaction refunds', 500);
        }
    }

    /**
     * Store a new unit transaction refund.
     */
    public function store(Request $request)
    {
        if (is_string($request->unit_transaction_item_detail_ids)) {
            $request->merge(['unit_transaction_item_detail_ids' => json_decode($request->unit_transaction_item_detail_ids, true)]);
        }

        $request->validate([
            'unit_transaction_id' => 'required|exists:unit_transactions,id',
            'refund_date' => 'required|date',
            'refund_amount' => 'required|numeric|min:0',
            'note' => 'nullable|string',
            'unit_transaction_item_detail_ids' => [
                'nullable',
                'array',
                function ($attribute, $value, $fail) use ($request) {
                    $existingIds = DB::table('unit_transaction_refund_item_detail')
                        ->whereIn('unit_transaction_item_detail_id', $value)
                        ->pluck('unit_transaction_item_detail_id')
                        ->toArray();
                    if (!empty($existingIds)) {
                        $fail('The following item detail IDs have already been refunded: ' . implode(', ', $existingIds));
                        return;
                    }

                    // Check if owned by the selected unit transaction
                    $unitTransactionId = $request->unit_transaction_id;
                    if ($unitTransactionId) {
                        $unitTransaction = DB::table('unit_transactions')->find($unitTransactionId);
                        if ($unitTransaction) {
                            if ($unitTransaction->type === 'purchase') {
                                $validIds = DB::table('unit_transaction_item_details')
                                    ->join('unit_transaction_items', 'unit_transaction_items.id', '=', 'unit_transaction_item_details.unit_transaction_item_id')
                                    ->where('unit_transaction_items.unit_transaction_id', $unitTransactionId)
                                    ->whereIn('unit_transaction_item_details.id', $value)
                                    ->pluck('unit_transaction_item_details.id')
                                    ->toArray();
                            } else {
                                $validIds = DB::table('unit_transaction_item_sales')
                                    ->join('unit_transaction_items', 'unit_transaction_items.id', '=', 'unit_transaction_item_sales.unit_transaction_item_id')
                                    ->where('unit_transaction_items.unit_transaction_id', $unitTransactionId)
                                    ->whereIn('unit_transaction_item_sales.unit_transaction_item_detail_id', $value)
                                    ->pluck('unit_transaction_item_sales.unit_transaction_item_detail_id')
                                    ->toArray();
                            }

                            $invalidIds = array_diff($value, $validIds);
                            if (!empty($invalidIds)) {
                                $fail('The following item detail IDs do not belong to the selected unit transaction: ' . implode(', ', $invalidIds));
                                return;
                            }
                        }
                    }

                    // Check if in stock
                    $notInStockIds = UnitTransactionItemDetail::whereIn('id', $value)
                        ->where('in_stock', false)
                        ->pluck('id')
                        ->toArray();

                    if (!empty($notInStockIds)) {
                        $fail('The following item detail IDs are not in stock: ' . implode(', ', $notInStockIds));
                        return;
                    }
                }
            ],
            'unit_transaction_item_detail_ids.*' => 'exists:unit_transaction_item_details,id',
        ]);

        try {
            $refund = DB::transaction(function () use ($request) {
                // Generate refund code using RefundTrait
                $code = $this->generateRefundCode();

                $refund = UnitTransactionRefund::create([
                    'unit_transaction_id' => $request->unit_transaction_id,
                    'code' => $code,
                    'refund_date' => $request->refund_date,
                    'refund_amount' => $request->refund_amount,
                    'note' => $request->note,
                ]);

                if ($request->filled('unit_transaction_item_detail_ids')) {
                    $refund->unitTransactionItemDetails()->sync($request->unit_transaction_item_detail_ids);

                    $unitTransaction = $refund->unitTransaction;
                    $activity = WarehouseActivity::create([
                        'person_id' => $unitTransaction->person_id,
                        'warehouse_id' => $unitTransaction->warehouse_id,
                        'activity_type' => $unitTransaction->type === 'purchase' ? 'issue' : 'receipt',
                        'activity_date' => $refund->refund_date ?? now(),
                        'description' => 'Refund ' . $refund->code,
                    ]);

                    $details = UnitTransactionItemDetail::whereIn('id', $request->unit_transaction_item_detail_ids)->get();
                    foreach ($details as $detail) {
                        WarehouseMovement::create([
                            'warehouse_activity_id' => $activity->id,
                            'unit_transaction_id' => $unitTransaction->id,
                            'unit_transaction_item_detail_id' => $detail->id,
                            'status' => 'refund',
                        ]);

                        if ($unitTransaction->type === 'purchase') {
                            $detail->update([
                                'in_stock' => false,
                                'is_forecast' => false,
                                'status' => 'returned',
                            ]);
                        } else if ($unitTransaction->type === 'sales') {
                            $detail->update([
                                'in_stock' => true,
                                'is_forecast' => false,
                                'status' => 'returned',
                            ]);
                        }
                    }

                    $unitTransaction->recalculateBillingTotals();
                }

                $refund->load(['unitTransaction', 'unitTransactionItemDetails', 'unitTransactionRefundPayments']);
                $refund->total_payable = (int) $refund->refund_amount;
                $refund->total_paid = (int) $refund->unitTransactionRefundPayments->sum('amount');
                $refund->remaining_payment = $refund->total_payable - $refund->total_paid;
                $refund->total_qty = $refund->unitTransactionItemDetails->count();

                return $refund;
            });

            return $this->responseSuccess($refund, 'Unit transaction refund created successfully', 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error('Error while creating unit transaction refund: ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Failed to create unit transaction refund', 500);
        }
    }

    /**
     * Show details of a unit transaction refund.
     */
    public function show(string $id)
    {
        try {
            $refund = UnitTransactionRefund::with([
                'unitTransaction:id,uuid,code,type',
                'unitTransactionRefundPayments',
                'unitTransactionItemDetails',
            ])->findOrFail($id);

            $refund->total_payable = (int) $refund->refund_amount;
            $refund->total_paid = (int) $refund->unitTransactionRefundPayments->sum('amount');
            $refund->remaining_payment = $refund->total_payable - $refund->total_paid;
            $refund->total_qty = $refund->unitTransactionItemDetails->count();

            return $this->responseSuccess($refund, 'Unit transaction refund retrieved successfully', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error('Error while retrieving unit transaction refund: ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Failed to retrieve unit transaction refund', 404);
        }
    }

    /**
     * Update a unit transaction refund.
     */
    public function update(Request $request, string $id)
    {
        if (is_string($request->unit_transaction_item_detail_ids)) {
            $request->merge(['unit_transaction_item_detail_ids' => json_decode($request->unit_transaction_item_detail_ids, true)]);
        }
        
        $request->validate([
            'unit_transaction_id' => 'nullable|exists:unit_transactions,id',
            'refund_date' => 'nullable|date',
            'refund_amount' => 'nullable|numeric|min:0',
            'note' => 'nullable|string',
            'unit_transaction_item_detail_ids' => [
                'nullable',
                'array',
                function ($attribute, $value, $fail) use ($id) {
                    $existingIds = DB::table('unit_transaction_refund_item_detail')
                        ->where('unit_transaction_refund_id', '!=', $id)
                        ->whereIn('unit_transaction_item_detail_id', $value)
                        ->pluck('unit_transaction_item_detail_id')
                        ->toArray();
                    if (!empty($existingIds)) {
                        $fail('The following item detail IDs have already been refunded in another transaction: ' . implode(', ', $existingIds));
                    }
                }
            ],
            'unit_transaction_item_detail_ids.*' => 'exists:unit_transaction_item_details,id',
        ]);

        try {
            $refund = DB::transaction(function () use ($request, $id) {
                $refund = UnitTransactionRefund::findOrFail($id);

                // 1. Revert old item details of this refund
                $oldDetailIds = $refund->unitTransactionItemDetails()->pluck('unit_transaction_item_details.id')->toArray();
                if (!empty($oldDetailIds)) {
                    $oldDetails = UnitTransactionItemDetail::whereIn('id', $oldDetailIds)->get();
                    foreach ($oldDetails as $oldDetail) {
                        $oldTx = $oldDetail->unitTransactionItem->unitTransaction;
                        if ($oldTx) {
                            if ($oldTx->type === 'purchase') {
                                $oldDetail->update([
                                    'in_stock' => true,
                                    'is_forecast' => false,
                                    'status' => null,
                                ]);
                            } else if ($oldTx->type === 'sales') {
                                $oldDetail->update([
                                    'in_stock' => false,
                                    'is_forecast' => false,
                                    'status' => null,
                                ]);
                            }
                            $oldTx->recalculateBillingTotals();
                        }
                    }
                }

                // 2. Delete associated old WarehouseActivity (which cascade deletes movements)
                WarehouseActivity::where('description', 'Refund ' . $refund->code)->delete();

                // 3. Update the refund
                $data = array_filter($request->only([
                    'unit_transaction_id',
                    'qty',
                    'refund_date',
                    'refund_amount',
                    'note'
                ]), fn ($value) => $value !== '' && $value !== null);

                $refund->update($data);

                // 4. Sync new details
                if ($request->has('unit_transaction_item_detail_ids')) {
                    $refund->unitTransactionItemDetails()->sync($request->unit_transaction_item_detail_ids ?? []);
                }

                // 5. Apply stock updates for new details
                $newDetailIds = $refund->unitTransactionItemDetails()->pluck('unit_transaction_item_details.id')->toArray();
                if (!empty($newDetailIds)) {
                    $unitTransaction = $refund->unitTransaction;
                    $activity = WarehouseActivity::create([
                        'person_id' => $unitTransaction->person_id,
                        'warehouse_id' => $unitTransaction->warehouse_id,
                        'activity_type' => $unitTransaction->type === 'purchase' ? 'issue' : 'receipt',
                        'activity_date' => $refund->refund_date ?? now(),
                        'description' => 'Refund ' . $refund->code,
                    ]);

                    $details = UnitTransactionItemDetail::whereIn('id', $newDetailIds)->get();
                    foreach ($details as $detail) {
                        WarehouseMovement::create([
                            'warehouse_activity_id' => $activity->id,
                            'unit_transaction_id' => $unitTransaction->id,
                            'unit_transaction_item_detail_id' => $detail->id,
                            'status' => 'refund',
                        ]);

                        if ($unitTransaction->type === 'purchase') {
                            $detail->update([
                                'in_stock' => false,
                                'is_forecast' => false,
                                'status' => 'returned',
                            ]);
                        } else if ($unitTransaction->type === 'sales') {
                            $detail->update([
                                'in_stock' => true,
                                'is_forecast' => false,
                                'status' => 'returned',
                            ]);
                        }
                    }

                    $unitTransaction->recalculateBillingTotals();
                }

                $refund->load(['unitTransaction', 'unitTransactionItemDetails', 'unitTransactionRefundPayments']);
                $refund->total_payable = (int) $refund->refund_amount;
                $refund->total_paid = (int) $refund->unitTransactionRefundPayments->sum('amount');
                $refund->remaining_payment = $refund->total_payable - $refund->total_paid;
                $refund->total_qty = $refund->unitTransactionItemDetails->count();

                return $refund;
            });

            return $this->responseSuccess($refund, 'Unit transaction refund updated successfully', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error('Error while updating unit transaction refund: ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Failed to update unit transaction refund', 500);
        }
    }

    /**
     * Delete a unit transaction refund.
     */
    public function destroy(string $id)
    {
        try {
            DB::transaction(function () use ($id) {
                $refund = UnitTransactionRefund::findOrFail($id);

                // 1. Revert old item details of this refund
                $detailIds = $refund->unitTransactionItemDetails()->pluck('unit_transaction_item_details.id')->toArray();
                if (!empty($detailIds)) {
                    $details = UnitTransactionItemDetail::whereIn('id', $detailIds)->get();
                    foreach ($details as $detail) {
                        $tx = $detail->unitTransactionItem->unitTransaction;
                        if ($tx) {
                            if ($tx->type === 'purchase') {
                                $detail->update([
                                    'in_stock' => true,
                                    'is_forecast' => false,
                                    'status' => null,
                                ]);
                            } else if ($tx->type === 'sales') {
                                $detail->update([
                                    'in_stock' => false,
                                    'is_forecast' => false,
                                    'status' => null,
                                ]);
                            }
                            $tx->recalculateBillingTotals();
                        }
                    }
                }

                // 2. Delete associated WarehouseActivity
                WarehouseActivity::where('description', 'Refund ' . $refund->code)->delete();

                // 3. Detach and delete refund
                $refund->unitTransactionItemDetails()->detach();
                $refund->delete();
            });

            return $this->responseSuccess(null, 'Unit transaction refund deleted successfully', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error('Error while deleting unit transaction refund: ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Failed to delete unit transaction refund', 500);
        }
    }
}
