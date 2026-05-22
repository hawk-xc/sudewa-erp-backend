<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\FinanceRefund;
use App\Models\UnitTransactionRefund;
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
        } catch (Exception $err) {
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
                function ($attribute, $value, $fail) {
                    $existingIds = DB::table('unit_transaction_refund_item_detail')
                        ->whereIn('unit_transaction_item_detail_id', $value)
                        ->pluck('unit_transaction_item_detail_id')
                        ->toArray();
                    if (!empty($existingIds)) {
                        $fail('The following item detail IDs have already been refunded: ' . implode(', ', $existingIds));
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
                }

                $refund->load(['unitTransaction', 'unitTransactionItemDetails', 'unitTransactionRefundPayments']);
                $refund->total_payable = (int) $refund->refund_amount;
                $refund->total_paid = (int) $refund->unitTransactionRefundPayments->sum('amount');
                $refund->remaining_payment = $refund->total_payable - $refund->total_paid;
                $refund->total_qty = $refund->unitTransactionItemDetails->count();

                return $refund;
            });

            return $this->responseSuccess($refund, 'Unit transaction refund created successfully', 201);
        } catch (Exception $err) {
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
        } catch (Exception $err) {
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

                $data = array_filter($request->only([
                    'unit_transaction_id',
                    'qty',
                    'refund_date',
                    'refund_amount',
                    'note'
                ]), fn ($value) => $value !== '' && $value !== null);

                $refund->update($data);

                if ($request->has('unit_transaction_item_detail_ids')) {
                    $refund->unitTransactionItemDetails()->sync($request->unit_transaction_item_detail_ids ?? []);
                }

                $refund->load(['unitTransaction', 'unitTransactionItemDetails', 'unitTransactionRefundPayments']);
                $refund->total_payable = (int) $refund->refund_amount;
                $refund->total_paid = (int) $refund->unitTransactionRefundPayments->sum('amount');
                $refund->remaining_payment = $refund->total_payable - $refund->total_paid;
                $refund->total_qty = $refund->unitTransactionItemDetails->count();

                return $refund;
            });

            return $this->responseSuccess($refund, 'Unit transaction refund updated successfully', 200);
        } catch (Exception $err) {
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
                $refund->unitTransactionItemDetails()->detach();
                $refund->delete();
            });

            return $this->responseSuccess(null, 'Unit transaction refund deleted successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while deleting unit transaction refund: ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Failed to delete unit transaction refund', 500);
        }
    }
}
