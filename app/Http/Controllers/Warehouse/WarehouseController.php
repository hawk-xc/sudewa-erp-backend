<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\UnitTransactionItem;
use App\Models\UnitTransactionItemDetail;
use App\Models\Warehouse;
use App\Models\WarehouseMovement;
use App\Repositories\AuthRepository;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WarehouseController extends Controller
{
    use ResponseTrait;

    protected AuthRepository $authRepository;
    protected $warehouseTable;

    public function __construct(AuthRepository $ar)
    {
        $this->middleware(['permission:warehouse:list'])->only(['index', 'show']);
        $this->middleware(['permission:warehouse:create'])->only('store');
        $this->middleware(['permission:warehouse:edit'])->only('update');
        $this->middleware(['permission:warehouse:delete'])->only(['destroy']);

        $this->authRepository = $ar;

        $this->warehouseTable = [
            'id',
            'uuid',
            'company_id',
            'name',
            'capacity',
            'description',
            'created_at',
        ];
    }

    public function index(Request $request)
    {
        try {
            $query = Warehouse::with('company');

            $query->select($this->warehouseTable);

            if ($request->company_id) {
                $query->where('company_id', $request->company_id);
            }

            if ($request->search) {
                $query->where('name', 'like', '%'.$request->search.'%');
            }

            $data = $query->latest()->paginate($request->per_page ?? 10);

            return response()->json([
                'success' => true,
                'message' => 'Warehouses retrieved successfully',
                'data' => $data,
            ], 200);
        } catch (Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'capacity' => 'required|numeric',
            'description' => 'nullable|string',
        ]);

        try {
            $data = DB::transaction(function () use ($validated) {
                return Warehouse::create($validated);
            });

            return response()->json([
                'success' => true,
                'message' => 'Warehouse created successfully',
                'data' => $data,
            ], 201);
        } catch (Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $data = Warehouse::with([
                'company:id,uuid,code,slug,name',

                'unitTransactions:id,uuid,warehouse_id,person_id,code,type,stock_state',

                'unitTransactions.person:id,uuid,code,type,name',

                'unitTransactions.unitTransactionItems:id,uuid,unit_transaction_id,unit_type_id,sparepart_id,qty_total',

                'unitTransactions.unitTransactionItems.unitType:id,uuid,code,name,unit_type,unit_model',
                'unitTransactions.unitTransactionItems.sparepart:id,uuid,code,name',

                'unitTransactions.unitTransactionItems.unitTransactionItemDetails:id,uuid,unit_transaction_item_id,color,machine_number,chassis_number,in_stock',

                'unitTransactions.unitTransactionItems.unitTransactionItemDetails.warehouseMovement:id,uuid,unit_transaction_item_detail_id,status',
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Warehouse retrieved successfully',
                'data' => $data,
            ], 200);

        } catch (Exception $err) {
            Log::error('Error while processing warehouse data : '.$err->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Warehouse not found',
            ], 404);
        }
    }

    public function getUnitTransaction(Request $request, string $id)
    {
        $type = $request->type;
        $query = Warehouse::find((int) $id)->unitTransactions();

        try {
            switch ($type) {
                case 'purchase':
                    $query = $query->where('type', 'purchase')->get();

                    return $this->responseSuccess($query, 'Unit transaction data successfully fetched', 200);
                case 'sales':
                    $query = $query->where('type', 'sales')->get();

                    return $this->responseSuccess($query, 'Unit transaction data successfully fetched', 200);
                default:
                    $query = $query->get();

                    return $this->responseSuccess($query, 'Unit transaction data successfully fetched', 200);
            }
        } catch (Exception $err) {
            Log::error('Fetch Warehouse Unit Transaction : '.$err->getMessage());

            return $this->responseError(null, $err->getMessage(), 500);
        }
    }

    public function getUnitTransactionItem(Request $request, string $id)
    {
        $data = UnitTransactionItem::findOrFail($id)->with('unitTransactionItemDetails');

        try {
            return $this->responseSuccess($data, 'Successfully get unit transaction item data', 200);
        } catch (Exception $err) {
            return $this->responseError(null, 'Error while get unit transaction item data', 500);
        }

    }

    public function getStock(Request $request, string $id)
    {
        try {

            $warehouse = Warehouse::findOrFail($id);

            $stockInHand = WarehouseMovement::where('warehouse_id', $warehouse->id)
                ->where('status', 'in')
                ->whereHas('unitTransactionItemDetail', function ($q) {
                    $q->where('in_stock', true);
                })
                ->count();

            $stockForecast = UnitTransactionItemDetail::where('in_stock', false)
                ->whereHas('unitTransactionItem.unitTransaction', function ($q) use ($warehouse) {
                    $q->where('warehouse_id', $warehouse->id)
                    ->where('type', 'purchase');
                })
                ->count();

            return $this->responseSuccess((object) [
                'stock_in_hand' => $stockInHand,
                'stock_forecast' => $stockForecast,
            ], 'Successfully fetch warehouse stock data', 200);

        } catch (Exception $err) {

            Log::error('Error while fetch warehouse stock data : ' . $err->getMessage());

            return $this->responseError(null, $err->getMessage(), 500);
        }
    }

    public function getWarehouseStock(Request $request, string $id)
    {
        try {

            $warehouse = Warehouse::findOrFail($id);

            $stocks = WarehouseMovement::where('warehouse_id', $warehouse->id)
                ->where('status', 'in')
                ->whereHas('unitTransactionItemDetail', function ($q) {
                    $q->where('in_stock', true);
                })
                ->with([
                    'unitTransactionItemDetail.unitTransactionItem.unitType:id,uuid,code,name,unit_type,unit_model',
                ])
                ->get();

            $available = $stocks
                ->groupBy(function ($item) {
                    return $item->unitTransactionItemDetail
                        ->unitTransactionItem
                        ->unitType
                        ->id;
                })
                ->map(function ($items) {

                    $unitType = $items->first()
                        ->unitTransactionItemDetail
                        ->unitTransactionItem
                        ->unitType;

                    return [
                        'unit_type' => $unitType,
                        'stock_available' => $items->count(),
                        'stock_forecast' => 0,
                    ];
                });

            $forecast = UnitTransactionItemDetail::where('in_stock', false)
                ->whereHas('unitTransactionItem.unitTransaction', function ($q) use ($warehouse) {
                    $q->where('warehouse_id', $warehouse->id)
                    ->where('type', 'purchase');
                })
                ->with([
                    'unitTransactionItem.unitType:id,uuid,code,name,unit_type,unit_model'
                ])
                ->get()
                ->groupBy(function ($item) {
                    return $item->unitTransactionItem
                        ->unitType
                        ->id;
                });

            $result = $available->map(function ($row, $unitTypeId) use ($forecast) {

                if (isset($forecast[$unitTypeId])) {
                    $row['stock_forecast'] = $forecast[$unitTypeId]->count();
                }

                return $row;
            })->values();

            return $this->responseSuccess(
                $result,
                'Successfully fetch warehouse stock data',
                200
            );

        } catch (Exception $err) {
            return $this->responseError(null, $err->getMessage(), 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $warehouse = Warehouse::findOrFail($id);

        $validated = $request->validate([
            'company_id' => 'sometimes|exists:companies,id', $id,
            'name' => 'sometimes|string|max:255',
            'capacity' => 'sometimes|numeric',
            'description' => 'sometimes|string',
        ]);

        try {
            DB::transaction(function () use ($validated, $warehouse) {
                $warehouse->update($validated);
            });

            return response()->json([
                'success' => true,
                'message' => 'Warehouse updated successfully',
                'data' => $warehouse->fresh(),
            ], 200);
        } catch (Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
            ], 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $data = Warehouse::findOrFail($id);

            DB::transaction(function () use ($data) {
                $data->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Warehouse deleted successfully',
            ], 200);
        } catch (Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
            ], 500);
        }
    }
}
