<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\GoodsTransaction;
use App\Models\GoodsTransactionBilling;
use App\Traits\ResponseTrait;
use App\Traits\GlobalCodeNumberTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GoodsTransactionBillingController extends Controller
{
    use ResponseTrait, GlobalCodeNumberTrait;

    protected array $goodsTransactionBillingTable;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);

        $this->goodsTransactionBillingTable = [
            'id',
            'uuid',
            'goods_transaction_id',
            'is_paid',
            'grand_total',
            'created_at',
        ];
    }

    /**
     * List all goods transaction billings.
     */
    public function index(Request $request)
    {
        $query = GoodsTransactionBilling::query();

        $query->select($this->goodsTransactionBillingTable);

        try {
            foreach ($this->goodsTransactionBillingTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $allowedSort = $this->goodsTransactionBillingTable;

            $sortBy = in_array($request->sort_by, $allowedSort)
                ? $request->sort_by
                : 'id';

            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->per_page ?? 10;

            $data = $query->paginate($perPage);

            return $this->responseSuccess($data, 'Goods Transaction Billing list retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error While retrieved Goods Transaction Billing data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Goods Transaction Billing list retrieved Failed', 500);
        }
    }

    /**
     * Store a new goods transaction billing.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'goods_transaction_id' => 'required|exists:goods_transactions,id'
        ]);

        try {
            $transaction = GoodsTransaction::findOrFail($validated['goods_transaction_id']);

            if ($transaction->goodsTransactionBillings()->exists()) {
                return $this->responseError('Goods Transaction Billing already exists', 'Goods Transaction Billing already exists', 400);
            }

            $grandTotal = $transaction->getTotalAmount();

            $data = DB::transaction(function () use ($transaction, $grandTotal) {
                return GoodsTransactionBilling::create([
                    'goods_transaction_id' => $transaction->id,
                    'grand_total' => $grandTotal,
                ]);
            });

            return $this->responseSuccess($data, 'Goods Transaction Billing created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error while trying create Goods Transaction Billing Data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Goods Transaction Billing creation failed', 500);
        }
    }

    /**
     * Get goods transaction billing detail.
     */
    public function show(string $id)
    {
        try {
            $data = GoodsTransactionBilling::with(['goodsTransaction', 'payments.cash'])
                ->select($this->goodsTransactionBillingTable)
                ->findOrFail($id);

            if ($data->goodsTransaction) {
                $data->goodsTransaction->makeHidden(['goodsTransactionDetails']);
            }

            return $this->responseSuccess($data, 'Goods Transaction Billing detail retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error While retrieved Goods Transaction Billing data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Goods Transaction Billing not found', 404);
        }
    }

    /**
     * Delete a goods transaction billing.
     */
    public function destroy(string $id)
    {
        try {
            $data = GoodsTransactionBilling::findOrFail($id);
            
            DB::transaction(function () use ($data) {
                $data->delete();
            });

            return $this->responseSuccess(null, 'Goods Transaction Billing deleted successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying delete Goods Transaction Billing data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Goods Transaction Billing deletion failed', 500);
        }
    }
}
