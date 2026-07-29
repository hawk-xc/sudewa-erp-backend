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

            $allowedSort = ['id', 'code', 'type', 'created_at'];

            if (! in_array($sortBy, $allowedSort)) {
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
                    $q->where('in_stock', true)->where('is_forecast', false)->where('status', 'normal');
                })
                ->count();

            $stockForecast = UnitTransactionItemDetail::where('in_stock', false)
                ->where('is_forecast', true)
                ->where('status', 'normal')
                ->whereHas('unitTransactionItem.unitTransaction', function ($q) use ($warehouse) {
                    $q->where('warehouse_id', $warehouse->id)->where('type', 'purchase');
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

            $query = $warehouse->unitTransactions()->with(['person:id,uuid,code,type,name', 'unitTransactionItems:id,unit_transaction_id,unit_type_id,qty_total,price', 'unitTransactionItems.unitType:id,uuid,code,name,unit_type,unit_model', 'unitTransactionBilling:id,unit_transaction_id,is_paid,last_payment_at', 'unitTransactionBilling.unitTransactionBillingHistories:id,unit_transaction_billing_id,payment_at']);

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

            $perPage = (int) ($request->per_page ?? 10);

            $availableQuery = WarehouseMovement::query()
                ->selectRaw('unit_transaction_items.unit_type_id, COUNT(*) as stock_available')
                ->join('unit_transaction_item_details', 'warehouse_movements.unit_transaction_item_detail_id', '=', 'unit_transaction_item_details.id')
                ->join('unit_transaction_items', 'unit_transaction_item_details.unit_transaction_item_id', '=', 'unit_transaction_items.id')
                ->join('warehouse_activities', 'warehouse_movements.warehouse_activity_id', '=', 'warehouse_activities.id')
                ->where('warehouse_activities.warehouse_id', $warehouse->id)
                ->where('warehouse_movements.status', 'in')
                ->where('unit_transaction_item_details.status', 'normal')
                ->where('unit_transaction_item_details.is_forecast', false);

            if ($request->status != 'unprocessed') {
                $availableQuery->where('unit_transaction_item_details.in_stock', true);
            }

            $availableQuery = $availableQuery->groupBy('unit_transaction_items.unit_type_id');

            $forecastQuery = UnitTransactionItemDetail::query()
                ->selectRaw('unit_transaction_items.unit_type_id, COUNT(*) as stock_forecast')
                ->join('unit_transaction_items', 'unit_transaction_item_details.unit_transaction_item_id', '=', 'unit_transaction_items.id')
                ->join('unit_transactions', 'unit_transaction_items.unit_transaction_id', '=', 'unit_transactions.id')
                ->where('unit_transaction_item_details.in_stock', false)
                ->where('unit_transaction_item_details.is_forecast', true)
                ->where('unit_transaction_item_details.status', 'normal')
                ->where('unit_transactions.warehouse_id', $warehouse->id)
                ->where('unit_transactions.type', 'purchase')
                ->groupBy('unit_transaction_items.unit_type_id');

            $available = $availableQuery->get()->keyBy('unit_type_id');
            $forecast = $forecastQuery->get()->keyBy('unit_type_id');

            $unitTypeIds = collect($available->keys())->merge($forecast->keys())->unique()->values();

            $unitTypes = \App\Models\UnitType::whereIn('id', $unitTypeIds)->get()->keyBy('id');

            $result = $unitTypeIds->map(function ($id) use ($available, $forecast, $unitTypes) {
                return [
                    'unit_type' => $unitTypes[$id] ?? null,
                    'stock_available' => $available[$id]->stock_available ?? 0,
                    'stock_forecast' => $forecast[$id]->stock_forecast ?? 0,
                ];
            });

            $paginated = new \Illuminate\Pagination\LengthAwarePaginator($result->forPage(request()->page ?? 1, $perPage)->values(), $result->count(), $perPage, request()->page ?? 1, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);

            return $this->responseSuccess($paginated, 'Successfully fetch warehouse stock data', 200);
        } catch (Exception $err) {
            return $this->responseError(null, $err->getMessage(), 500);
        }
    }

    public function getWarehouseUnitTransactionsDetails(Request $request, string $id)
    {
        try {
            $warehouse = Warehouse::findOrFail($id);

            $query = UnitTransactionItemDetail::query()
                ->select([
                    'id',
                    'unit_transaction_item_id',
                    'color',
                    'machine_number',
                    'chassis_number',
                    'in_stock',
                    'status',
                    'created_at',
                ])
                ->whereHas('unitTransactionItem.unitTransaction', function ($q) use ($warehouse, $request) {
                    $q->where('warehouse_id', $warehouse->id);

                    if ($request->filled('unit_transaction_id')) {
                        $q->where('id', $request->unit_transaction_id);
                    }
                })
                ->with([
                    'unitTransactionItem:id,unit_transaction_id,unit_type_id,price,qty_total',
                    'unitTransactionItem.unitType:id,code,brand_id,name,unit_type,unit_model',
                    'unitTransactionItem.unitType.brand:id,uuid,name',
                    'unitTransactionItem.unitTransaction:id,code',
                ]);

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

            $allowedSort = ['id', 'color', 'machine_number', 'chassis_number', 'created_at'];
            $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'id';
            $sortDir = $request->sort_dir === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortDir);

            $data = $query->paginate($request->per_page ?? 10);

            $data->getCollection()->transform(function ($item) {
                $unitItem = $item->unitTransactionItem;

                return [
                    'id' => $item->id,
                    'unit_type' => $unitItem->unitType ?? null,
                    'color' => $item->color,
                    'machine_number' => $item->machine_number,
                    'chassis_number' => $item->chassis_number,
                    'stock_available' => ($item->in_stock && $item->status === 'normal' || $item->status === 'returned') ? 1 : 0,
                    'stock_forecast' => (!$item->in_stock && $item->status === 'normal') ? 1 : 0,
                    'purchase_price' => (int) $item->unitTransactionItem->price / $item->unitTransactionItem->qty_total,
                    'status' => $item->status,

                ];
            });

            return $this->responseSuccess($data, 'Warehouse unit transaction details retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Fetch Warehouse Unit Transaction Details : ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Error while fetching warehouse unit transaction details', 500);
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

    public function getUnitTransactionOutstanding(Request $request, string $id)
    {
        try {
            $warehouse = Warehouse::findOrFail($id);

            $query = UnitTransactionItem::query()
                ->whereHas('unitTransaction', function ($q) use ($warehouse, $request) {
                    $q->where('warehouse_id', $warehouse->id);

                    if ($request->filled('type')) {
                        $q->where('type', $request->type);
                    } else {
                        $q->whereIn('type', ['purchase', 'sales']);
                    }

                    if ($request->filled('code')) {
                        $q->where('code', 'like', '%' . $request->code . '%');
                    }
                })
                ->with([
                    'unitTransaction:id,code,created_at,type,person_id',
                    'unitTransaction.person:id,name',
                    'unitType:id,name',
                ])
                ->withCount([
                    'unitTransactionItemDetails as qty_terima' => function ($q) {
                        $q->where('in_stock', true)->where('is_forecast', false)->where('status', 'normal');
                    },
                    'unitTransactionItemDetails as qty_kurang' => function ($q) {
                        $q->where('is_forecast', true)->where('in_stock', false)->where('status', 'normal');
                    }
                ]);

            if ($request->has('qty_outstanding')) {
                $isOutstanding = filter_var($request->qty_outstanding, FILTER_VALIDATE_BOOLEAN);

                if ($isOutstanding) {
                    $query->whereHas('unitTransactionItemDetails', function ($q) {
                        $q->where('is_forecast', true)->where('in_stock', false)->where('status', 'normal');
                    });
                } else {
                    $query->whereDoesntHave('unitTransactionItemDetails', function ($q) {
                        $q->where('is_forecast', true)->where('in_stock', false)->where('status', 'normal');
                    });
                }
            }

            $allowedSort = ['id', 'created_at', 'qty_total'];
            $sortBy = in_array($request->order_by, $allowedSort) ? $request->order_by : 'id';
            $sortDir = $request->order_sort === 'asc' ? 'asc' : 'desc';

            $data = $query->orderBy($sortBy, $sortDir)->paginate($request->per_page ?? 10);

            $data->getCollection()->transform(function ($item) {
                return [
                    'transaction_code' => $item->unitTransaction->code ?? null,
                    'transaction_date' => $item->unitTransaction->created_at ?? null,
                    'transaction_type' => $item->unitTransaction->type ?? null,
                    'person_name' => $item->unitTransaction->person->name ?? null,
                    'unit_type_name' => $item->unitType->name ?? null,
                    'qty_total' => $item->qty_total,
                    'qty_received' => $item->qty_terima,
                    'qty_outstanding' => $item->qty_kurang,
                ];
            });

            return $this->responseSuccess($data, 'Warehouse unit transaction outstanding retrieved successfully');
        } catch (Exception $err) {
            Log::error('Fetch Warehouse Unit Transaction Outstanding : ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Error while fetching warehouse unit transaction outstanding', 500);
        }
    }
}
