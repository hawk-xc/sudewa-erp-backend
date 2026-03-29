<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\UnitTransaction;
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

        $this->warehouseTable = ['id', 'uuid', 'company_id', 'name', 'capacity', 'description', 'created_at'];
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
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            $data = $query->latest()->paginate($request->per_page ?? 10);

            return response()->json(
                [
                    'success' => true,
                    'message' => 'Warehouses retrieved successfully',
                    'data' => $data,
                ],
                200,
            );
        } catch (Exception $e) {
            Log::error($e->getMessage());

            return response()->json(
                [
                    'success' => false,
                    'message' => 'Internal Server Error',
                ],
                500,
            );
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

            return response()->json(
                [
                    'success' => true,
                    'message' => 'Warehouse created successfully',
                    'data' => $data,
                ],
                201,
            );
        } catch (Exception $e) {
            Log::error($e->getMessage());

            return response()->json(
                [
                    'success' => false,
                    'message' => 'Internal Server Error',
                ],
                500,
            );
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $warehouse = Warehouse::with(['company:id,uuid,code,slug,name'])->findOrFail($id);

            $transactionQuery = UnitTransaction::query()
                ->with(['person:id,uuid,code,type,name'])
                ->where('warehouse_id', $warehouse->id);

            if ($request->filled('type')) {
                $transactionQuery->where('type', $request->type);
            }

            if ($request->filled('stock_state')) {
                $transactionQuery->where('stock_state', $request->stock_state);
            }

            if ($request->filled('person_id')) {
                $transactionQuery->where('person_id', $request->person_id);
            }

            if ($request->filled('search')) {
                $search = $request->search;

                $transactionQuery->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")->orWhereHas('person', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    });
                });
            }

            $sortBy = $request->get('sort_by', 'id');
            $sortDir = $request->get('sort_dir', 'desc');

            $allowedSort = ['id', 'code', 'type', 'stock_state', 'created_at'];

            if (!in_array($sortBy, $allowedSort)) {
                $sortBy = 'id';
            }

            $transactionQuery->orderBy($sortBy, $sortDir);

            $perPage = $request->get('per_page', 10);

            $warehouse->unit_transactions = $transactionQuery->paginate($perPage);

            return response()->json(
                [
                    'success' => true,
                    'message' => 'Warehouse retrieved successfully',
                    'data' => $warehouse,
                ],
                200,
            );
        } catch (Exception $err) {
            Log::error('Error while processing warehouse data : ' . $err->getMessage());

            return response()->json(
                [
                    'success' => false,
                    'message' => 'Warehouse not found',
                ],
                404,
            );
        }
    }

    public function getStock(Request $request, string $id)
    {
        try {
            $warehouse = Warehouse::findOrFail($id);

            $stockInHand = WarehouseMovement::whereHas('warehouseActivity', function ($q) use ($warehouse) {
                $q->where('warehouse_id', $warehouse->id);
            })
                ->where('status', 'in')
                ->whereHas('unitTransactionItemDetail', function ($q) {
                    $q->where('in_stock', true)->where('is_forecast', false);
                })
                ->count();

            $stockForecast = UnitTransactionItemDetail::where('in_stock', false)
                ->whereHas('unitTransactionItem.unitTransaction', function ($q) use ($warehouse) {
                    $q->where('warehouse_id', $warehouse->id)->where('is_forecast', true)->where('type', 'purchase');
                })
                ->count();

            return $this->responseSuccess(
                (object) [
                    'stock_in_hand' => $stockInHand,
                    'stock_forecast' => $stockForecast,
                ],
                'Successfully fetch warehouse stock data',
                200,
            );
        } catch (Exception $err) {
            Log::error('Error while fetch warehouse stock data : ' . $err->getMessage());

            return $this->responseError(null, $err->getMessage(), 500);
        }
    }

    public function getUnitTransaction(Request $request, string $id)
    {
        try {
            $warehouse = Warehouse::findOrFail((int) $id);

            $query = $warehouse->unitTransactions()->with(['person:id,uuid,code,type,name', 'unitTransactionItems:id,unit_transaction_id,unit_type_id,qty_total,price', 'unitTransactionItems.unitType:id,uuid,code,name,unit_type,unit_model', 'unitTransactionBilling:id,unit_transaction_id,is_paid,payment_at']);

            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }

            if ($request->filled('code')) {
                $query->where('code', 'like', '%' . $request->code . '%');
            }

            if ($request->filled('person_name')) {
                $query->whereHas('person', function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->person_name . '%');
                });
            }

            $data = $query->latest()->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'Unit transaction data successfully fetched', 200);
        } catch (Exception $err) {
            Log::error('Fetch Warehouse Unit Transaction : ' . $err->getMessage());

            return $this->responseError(null, $err->getMessage(), 500);
        }
    }

    public function getUnitTransactionItem(Request $request, string $id)
    {
        try {
            $query = UnitTransactionItem::query()
                ->where('id', $id)
                ->with(['unitType:id,uuid,code,name,unit_type,unit_model', 'unitTransactionItemDetails']);

            if ($request->filled('color')) {
                $query->whereHas('unitTransactionItemDetails', function ($q) use ($request) {
                    $q->where('color', 'like', '%' . strtoupper($request->color) . '%');
                });
            }

            if ($request->filled('machine_number')) {
                $query->whereHas('unitTransactionItemDetails', function ($q) use ($request) {
                    $q->where('machine_number', 'like', '%' . $request->machine_number . '%');
                });
            }

            if ($request->filled('chassis_number')) {
                $query->whereHas('unitTransactionItemDetails', function ($q) use ($request) {
                    $q->where('chassis_number', 'like', '%' . $request->chassis_number . '%');
                });
            }

            $data = $query->firstOrFail();

            return $this->responseSuccess($data, 'Successfully get unit transaction item data', 200);
        } catch (Exception $err) {
            return $this->responseError(null, 'Error while get unit transaction item data', 500);
        }
    }

    public function getWarehouseStock(Request $request, string $id)
    {
        try {
            $warehouse = Warehouse::findOrFail($id);

            $stocks = WarehouseMovement::query()
                ->whereHas('warehouseActivity', function ($q) use ($warehouse) {
                    $q->where('warehouse_id', $warehouse->id);
                })
                ->where('status', 'in')
                ->with(['unitTransactionItemDetail.unitTransactionItem.unitType:id,uuid,code,name,unit_type,unit_model']);

            if ($request->status != 'unprocessed') {
                $stocks->whereHas('unitTransactionItemDetail', function ($q) {
                    $q->where('in_stock', true);
                });
            }

            $stocks = $stocks->get();

            $available = $stocks
                ->groupBy(function ($item) {
                    return $item->unitTransactionItemDetail->unitTransactionItem->unitType->id;
                })
                ->map(function ($items) {
                    $unitType = $items->first()->unitTransactionItemDetail->unitTransactionItem->unitType;

                    return [
                        'unit_type' => $unitType,
                        'stock_available' => $items->count(),
                        'stock_forecast' => 0,
                    ];
                });

            $forecast = UnitTransactionItemDetail::where('in_stock', false)
                ->whereHas('unitTransactionItem.unitTransaction', function ($q) use ($warehouse) {
                    $q->where('warehouse_id', $warehouse->id)->where('type', 'purchase');
                })
                ->with(['unitTransactionItem.unitType:id,uuid,code,name,unit_type,unit_model'])
                ->get()
                ->groupBy(function ($item) {
                    return $item->unitTransactionItem->unitType->id;
                });

            $result = $available
                ->map(function ($row, $unitTypeId) use ($forecast) {
                    if (isset($forecast[$unitTypeId])) {
                        $row['stock_forecast'] = $forecast[$unitTypeId]->count();
                    }

                    return $row;
                })
                ->values();

            return $this->responseSuccess($result, 'Successfully fetch warehouse stock data', 200);
        } catch (Exception $err) {
            return $this->responseError(null, $err->getMessage(), 500);
        }
    }

    public function getWarehouseUnitTransactionsDetails(Request $request, string $id)
    {
        try {
            $warehouse = Warehouse::findOrFail($id);

            $query = UnitTransactionItemDetail::query()
                ->whereHas('unitTransactionItem.unitTransaction', function ($q) use ($warehouse) {
                    $q->where('warehouse_id', $warehouse->id)->where('stock_state', 'inbound_incoming_goods');
                })
                ->with(['unitTransactionItem:id,uuid,unit_transaction_id,unit_type_id', 'unitTransactionItem.unitType:id,uuid,code,name,unit_type,unit_model', 'unitTransactionItem.unitTransaction:id,uuid,code,warehouse_id,stock_state']);

            if ($request->has('in_stock')) {
                $query->where('in_stock', filter_var($request->in_stock, FILTER_VALIDATE_BOOLEAN));
            }

            if ($request->filled('unit_transaction_item_id')) {
                $query->where('unit_transaction_item_id', $request->unit_transaction_item_id);
            }

            if ($request->filled('machine_number')) {
                $query->where('machine_number', 'like', '%' . $request->machine_number . '%');
            }

            if ($request->filled('chassis_number')) {
                $query->where('chassis_number', 'like', '%' . $request->chassis_number . '%');
            }

            if ($request->filled('color')) {
                $query->where('color', 'like', '%' . strtoupper($request->color) . '%');
            }

            if ($request->filled('unit_transaction_id')) {
                $query->whereHas('unitTransactionItem.unitTransaction' , function ($q) use ($request) {
                    $q->where('id', $request->unit_transaction_id);
                });
            }

            if ($request->filled('sort_by')) {
                $query->orderBy($request->sort_by, $request->sort_dir ?? 'desc');
            } else {
                $query->latest();
            }

            $data = $query->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'Warehouse unit transaction details retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Fetch Warehouse Unit Transaction Details : ' . $err->getMessage());

            return $this->responseError(null, 'Error while fetching warehouse unit transaction details', 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $warehouse = Warehouse::findOrFail($id);

        $validated = $request->validate([
            'company_id' => 'sometimes|exists:companies,id',
            $id,
            'name' => 'sometimes|string|max:255',
            'capacity' => 'sometimes|numeric',
            'description' => 'sometimes|string',
        ]);

        try {
            DB::transaction(function () use ($validated, $warehouse) {
                $warehouse->update($validated);
            });

            return response()->json(
                [
                    'success' => true,
                    'message' => 'Warehouse updated successfully',
                    'data' => $warehouse->fresh(),
                ],
                200,
            );
        } catch (Exception $e) {
            Log::error($e->getMessage());

            return response()->json(
                [
                    'success' => false,
                    'message' => 'Internal Server Error',
                ],
                500,
            );
        }
    }

    public function destroy(string $id)
    {
        try {
            $data = Warehouse::findOrFail($id);

            DB::transaction(function () use ($data) {
                $data->delete();
            });

            return response()->json(
                [
                    'success' => true,
                    'message' => 'Warehouse deleted successfully',
                ],
                200,
            );
        } catch (Exception $e) {
            Log::error($e->getMessage());

            return response()->json(
                [
                    'success' => false,
                    'message' => 'Internal Server Error',
                ],
                500,
            );
        }
    }
}
