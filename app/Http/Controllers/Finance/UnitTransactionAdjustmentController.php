<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\UnitTransaction;
use App\Models\UnitTransactionAdjustment;
use App\Models\Cash;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UnitTransactionAdjustmentController extends Controller
{
    use ResponseTrait;

    protected $unitTransactionAdjustmentTable;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);

        $this->unitTransactionAdjustmentTable = [
            'id',
            'uuid',
            'unit_transaction_id',
            'cash_id',
            'amount',
            'description',
            'type',
            'created_at',
        ];
    }

    public function index(Request $request)
    {
        try {
            $query = UnitTransactionAdjustment::query();

            $query->select($this->unitTransactionAdjustmentTable)
                ->with([
                    'unitTransaction:id,uuid,person_id,code,type',
                    'unitTransaction.person:id,uuid,code,type,name',
                    'cash:id,uuid,code,description,type',
                ]);

            if ($request->filled('adjustment_type')) {
                $query->where('type', $request->adjustment_type);
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
                in_array($request->sort_by, $this->unitTransactionAdjustmentTable) ? $request->sort_by : 'id',
                $request->sort_order === 'asc' ? 'asc' : 'desc'
            );

            $data = $query->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'Unit Transaction Adjustment list retrieved successfully', 200);

        } catch (Exception $err) {
            Log::error('Error While retrieved Unit Transaction Adjustment data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Unit Transaction Adjustment list retrieved Failed', 500);
        }
    }

    public function show(string $id)
    {
        try {
            $data = UnitTransactionAdjustment::with([
                'unitTransaction:id,uuid,code,type',
                'cash:id,uuid,code,description,type',
            ])
                ->select($this->unitTransactionAdjustmentTable)
                ->findOrFail($id);

            return $this->responseSuccess($data, 'Unit Transaction Adjustment retrieved successfully', 200);

        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            return $this->responseError($err->getMessage(), 'Unit Transaction Adjustment not found', 404);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'unit_transaction_id' => 'required|integer|exists:unit_transactions,id',
                'cash_id' => 'required|integer|exists:cashes,id',
                'amount' => 'required|integer|min:0',
                'description' => 'nullable|string',
                'type' => 'required|string|in:refund,return',
            ]);

            $unitTransaction = UnitTransaction::findOrFail($request->unit_transaction_id);

            if ($unitTransaction->is_refunded) {
                return $this->responseError(null, 'Transaction has already been adjusted/refunded.', 422);
            }

            if (!$unitTransaction->unitTransactionBilling || !$unitTransaction->unitTransactionBilling->is_paid) {
                return $this->responseError(null, 'Transaction has not been paid or has no billing data.', 422);
            }

            if (!isset($validated['amount'])) {
                $validated['amount'] = (int) $unitTransaction->getBrutoAmount();
            }

            $adjustment = DB::transaction(function () use ($validated, $unitTransaction) {
                $unitTransaction->update(['is_refunded' => true]);

                $adj = UnitTransactionAdjustment::create($validated);

                $cash = Cash::find($validated['cash_id']);
                if ($cash) {
                    $trxType = $unitTransaction->type;
                    $cash->adjustAmount((float) $validated['amount'], 'refund_' . $trxType);
                }

                return $adj;
            });

            return $this->responseSuccess($adjustment, 'Unit Transaction Adjustment created successfully', 201);

        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while creating Unit Transaction Adjustment: '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Unit Transaction Adjustment creation failed', 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $adjustment = UnitTransactionAdjustment::findOrFail($id);
            $unitTransaction = $adjustment->unitTransaction;

            DB::transaction(function () use ($adjustment, $unitTransaction) {
                if ($unitTransaction) {
                    $unitTransaction->update(['is_refunded' => false]);
                }

                $cash = Cash::find($adjustment->cash_id);
                if ($cash && $unitTransaction) {
                    $trxType = $unitTransaction->type;
                    $cash->adjustAmount(-(float) $adjustment->amount, 'refund_' . $trxType);
                }

                $adjustment->delete();
            });

            return $this->responseSuccess((object) [], 'Unit Transaction Adjustment deleted successfully', 200);

        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while deleting Unit Transaction Adjustment: '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Unit Transaction Adjustment deletion failed', 500);
        }
    }
}
