<?php

namespace App\Http\Controllers\Report;

use App\Exports\UnitTypeDetailReportExport;
use App\Models\UnitTransactionItemDetail;
use App\Repositories\AuthRepository;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class UnitTypeDetailReportController extends Controller
{
    use ResponseTrait;

    protected AuthRepository $authRepository;

    public function __construct(AuthRepository $ar)
    {
        $this->authRepository = $ar;

        $this->middleware(['permission:report:list'])->only(['index', 'export']);
    }

    public function index(Request $request)
    {
        try {
            $query = UnitTransactionItemDetail::query()
                ->select([
                    'id',
                    'unit_transaction_item_id',
                    'color',
                    'machine_number',
                    'chassis_number',
                    'in_stock',
                    'is_forecast',
                    'status',
                    'created_at',
                ])
                ->with([
                    'unitTransactionItem:id,unit_transaction_id,unit_type_id',
                    'unitTransactionItem.unitType:id,code,name,unit_type,unit_model',
                    'unitTransactionItem.unitTransaction:id,code,type,person_id,created_at',
                    'unitTransactionItem.unitTransaction.person:id,name',
                    'warehouseMovement:id,uuid,serial_number,warehouse_activity_id,unit_transaction_id,unit_transaction_item_detail_id,status,created_at'
                ]);

            if ($request->filled('type')) {
                $query->whereHas('unitTransactionItem.unitTransaction', function ($q) use ($request) {
                    $q->where('type', $request->type);
                });
            }

            if ($request->filled('warehouse_id')) {
                $query->whereHas('unitTransactionItem.unitTransaction', function ($q) use ($request) {
                    $q->where('warehouse_id', $request->warehouse_id);
                });
            }

            if ($request->filled('code')) {
                $query->whereHas('unitTransactionItem.unitTransaction', function ($q) use ($request) {
                    $q->where('code', 'like', "%{$request->code}%");
                });
            }

            if ($request->filled('person')) {
                $query->whereHas('unitTransactionItem.unitTransaction.person', function ($q) use ($request) {
                    $q->where('name', 'like', "%{$request->person}%");
                });
            }

            if ($request->filled('unit_type_id')) {
                $query->whereHas('unitTransactionItem', function ($q) use ($request) {
                    $q->where('unit_type_id', $request->unit_type_id);
                });
            }

            if ($request->filled('machine_number')) {
                $query->where('machine_number', 'like', "%{$request->machine_number}%");
            }

            if ($request->filled('chassis_number')) {
                $query->where('chassis_number', 'like', "%{$request->chassis_number}%");
            }

            if ($request->filled('color')) {
                $query->where('color', 'like', "%{$request->color}%");
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('in_stock')) {
                $query->where('in_stock', filter_var($request->in_stock, FILTER_VALIDATE_BOOLEAN));
            }

            if ($request->filled('is_forecast')) {
                $query->where('is_forecast', filter_var($request->is_forecast, FILTER_VALIDATE_BOOLEAN));
            }

            $query->orderBy(
                in_array($request->sort_by, ['id', 'created_at', 'color']) ? $request->sort_by : 'id',
                $request->sort_dir === 'asc' ? 'asc' : 'desc'
            );

            $data = $query->paginate($request->per_page ?? 10);

            $data->getCollection()->transform(function ($item) {
                return [
                    'id' => $item->id,
                    'created_date' => $item->created_at,
                    'receipt_date' => $item->warehouseMovement->created_at ?? null,
                    'transaction_code' => $item->unitTransactionItem->unitTransaction->code ?? null,
                    'type' => $item->unitTransactionItem->unitTransaction->type ?? null,
                    'person' => $item->unitTransactionItem->unitTransaction->person->name ?? null,
                    'unit_type' => $item->unitTransactionItem->unitType ?? null,
                    'machine_number' => $item->machine_number,
                    'chassis_number' => $item->chassis_number,
                    'color' => $item->color,
                    'in_stock' => $item->in_stock,
                    'is_forecast' => $item->is_forecast,
                    'status' => $item->status,
                    'warehouse_movement' => $item->warehouseMovement,
                ];
            });

            return $this->responseSuccess($data, 'Unit Type Detail report retrieved successfully', 200);

        } catch (Exception $err) {
            Log::error('Error retrieving Unit Type Detail report: ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Failed to retrieve data', 500);
        }
    }

    public function export(Request $request)
    {
        try {
            return Excel::download(
                new UnitTypeDetailReportExport($request),
                'unit_type_detail_report.xlsx'
            );
        } catch (Exception $err) {
            Log::error($err->getMessage());

            return $this->responseError(null, 'Export failed', 500);
        }
    }
}