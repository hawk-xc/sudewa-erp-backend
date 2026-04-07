<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\UnitTransaction;
use App\Models\UnitTransactionRefund;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UnitTransactionRefundController extends Controller
{
    use ResponseTrait;

    protected $unitTransactionRefundTable;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);

        $this->unitTransactionRefundTable = [
            'id',
            'uuid',
            'unit_transaction_id',
            'cash_id',
            'refund_total',
            'description',
            'created_at',
        ];
    }

    public function index(Request $request)
    {
        try {
            $query = UnitTransactionRefund::query();

            $query->select($this->unitTransactionRefundTable)
                ->with([
                    'unitTransaction:id,uuid,person_id,code,type,stock_state',
                    'unitTransaction.person:id,uuid,code,type,name',
                    'cash:id,uuid,code,description,type',
                ]);

            if ($request->filled('refund_type')) {
                $query->whereHas('unitTransaction', function ($q) use ($request) {
                    $q->where('type', $request->refund_type);
                });
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%$search%")
                        ->orWhereHas('unitTransaction', function ($q) use ($search) {
                            $q->where('code', 'like', "%$search%");
                        });
                });
            }

            $query->orderBy(
                in_array($request->sort_by, $this->unitTransactionRefundTable) ? $request->sort_by : 'id',
                $request->sort_order === 'asc' ? 'asc' : 'desc'
            );

            $data = $query->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'Unit Transaction Refund list retrieved successfully', 200);

        } catch (Exception $err) {
            Log::error('Error While retrieved Unit Transaction Refund data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Unit Transaction Refund list retrieved Failed', 500);
        }
    }

    public function show(string $id)
    {
        try {
            $data = UnitTransactionRefund::with([
                'unitTransaction:id,uuid,code,type,stock_state',
                'cash:id,uuid,code,description,type',
            ])
                ->select($this->unitTransactionRefundTable)
                ->findOrFail($id);

            return $this->responseSuccess($data, 'Unit Transaction Refund retrieved successfully', 200);

        } catch (Exception $err) {
            return $this->responseError($err->getMessage(), 'Unit Transaction Refund not found', 404);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'unit_transaction_id' => 'required|integer|exists:unit_transactions,id',
                'cash_id' => 'required|integer|exists:cashes,id',
                'refund_total' => 'sometimes|numeric|min:0',
                'description' => 'nullable|string',
            ]);

            $unitTransaction = UnitTransaction::findOrFail($request->unit_transaction_id);

            if ($unitTransaction->is_refunded) {
                return $this->responseError(null, 'Transaction has already been refunded.', 422);
            }

            if (!$unitTransaction->unitTransactionBilling || !$unitTransaction->unitTransactionBilling->is_paid) {
                return $this->responseError(null, 'Transaction has not been paid or has no billing data.', 422);
            }

            if (!isset($validated['refund_total'])) {
                $validated['refund_total'] = (float) $unitTransaction->getBrutoAmount();
            }

            $refund = DB::transaction(function () use ($validated, $unitTransaction) {
                $unitTransaction->update(['is_refunded' => true]);

                return UnitTransactionRefund::create($validated);
            });

            return $this->responseSuccess($refund, 'Unit Transaction Refund created successfully', 201);

        } catch (Exception $err) {
            Log::error('Error while creating Unit Transaction Refund: '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Unit Transaction Refund creation failed', 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $refund = UnitTransactionRefund::findOrFail($id);
            $unitTransaction = $refund->unitTransaction;

            DB::transaction(function () use ($refund, $unitTransaction) {
                if ($unitTransaction) {
                    $unitTransaction->update(['is_refunded' => false]);
                }
                $refund->delete();
            });

            return $this->responseSuccess((object) [], 'Unit Transaction Refund deleted successfully', 200);

        } catch (Exception $err) {
            Log::error('Error while deleting Unit Transaction Refund: '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Unit Transaction Refund deletion failed', 500);
        }
    }
}
