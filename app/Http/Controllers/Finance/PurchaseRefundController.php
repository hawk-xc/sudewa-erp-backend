<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\UnitTransactionAdjustment;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PurchaseRefundController extends Controller
{
    use ResponseTrait;

    protected $purchaseRefundTable;

    public function __construct()
    {
        // $this->middleware(['permission:transaction:list'])->only(['index']);

        $this->purchaseRefundTable = [
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

            $query->select($this->purchaseRefundTable)
                ->with([
                    'unitTransaction:id,uuid,person_id,code,type,stock_state',
                    'unitTransaction.person:id,uuid,code,type,name',
                    'cash:id,uuid,code,description,type',
                    'unitTransactionAdjustmentItems.unitTransactionItem.unitType:id,code,name,unit_type',
                    'unitTransactionAdjustmentItems.unitTransactionItemDetail:id,uuid,color,machine_number,chassis_number,status',
                ]);

            if ($request->filled('type')) {
                $type = $request->type;
                if (in_array($type, ['purchase', 'sales'])) {
                    $query->whereHas('unitTransaction', function ($q) use ($type) {
                        $q->where('type', $type);
                    });
                }
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

            $sortBy = in_array($request->sort_by, $this->purchaseRefundTable) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
            $query->orderBy($sortBy, $sortOrder);

            $data = $query->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'Refunded and returned transactions retrieved successfully', 200);

        } catch (Exception $err) {
            Log::error('Error while retrieving refunded data in PurchaseRefundController: ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Failed to retrieve refunded data', 500);
        }
    }
}
