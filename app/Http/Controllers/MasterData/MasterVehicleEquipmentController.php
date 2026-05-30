<?php

namespace App\Http\Controllers\MasterData;

use App\Traits\GlobalCodeNumberTrait;
use App\Http\Controllers\Controller;
use App\Models\VehicleEquipment;
use App\Repositories\AuthRepository;
use App\Traits\ResponseTrait;
use App\Exports\VehicleEquipmentExport;
use App\Imports\VehicleEquipmentImport;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @group Master Data
 *
 * API for managing vehicle equipment.
 */
class MasterVehicleEquipmentController extends Controller
{
    use ResponseTrait, GlobalCodeNumberTrait;

    protected AuthRepository $authRepository;

    // projection
    protected array $vehicleEquipmentTable;

    public function __construct(AuthRepository $ar)
    {
        $this->middleware(['permission:master-data:list'])->only(['index', 'show', 'export']);
        $this->middleware(['permission:master-data:create'])->only(['store', 'import']);
        $this->middleware(['permission:master-data:edit'])->only('update');
        $this->middleware(['permission:master-data:delete'])->only(['destroy']);

        $this->authRepository = $ar;

        $this->vehicleEquipmentTable = ['id', 'uuid', 'code', 'name', 'created_at'];
    }

    /**
     * List all vehicle equipment.
     */
    public function index(Request $request)
    {
        $warehouseId = $request->warehouse_id;
        $companyId = $request->company_id;
        if ($request->filled('company_id')) {
            $company = \App\Models\Company::with('warehouse')->find($request->company_id);
            if ($company && $company->warehouse) {
                $warehouseId = $company->warehouse->id;
            }
        }

        $query = VehicleEquipment::select(array_map(fn($col) => "vehicle_equipments.{$col}", $this->vehicleEquipmentTable))
            ->selectSub(function ($q) use ($warehouseId, $companyId) {
                $q->from('goods_transaction_details')
                    ->join('goods_transactions', 'goods_transaction_details.goods_transaction_id', '=', 'goods_transactions.id')
                    ->whereColumn('goods_transaction_details.vehicle_equipment_id', 'vehicle_equipments.id')
                    ->where('goods_transactions.type', 'receipt');
                
                if ($companyId) {
                    $q->where('goods_transactions.company_id', $companyId);
                }
                
                if ($warehouseId) {
                    $q->join('warehouse_movements', 'warehouse_movements.goods_transaction_detail_id', '=', 'goods_transaction_details.id')
                      ->join('warehouse_activities', 'warehouse_movements.warehouse_activity_id', '=', 'warehouse_activities.id')
                      ->where('warehouse_activities.warehouse_id', $warehouseId);
                }
                $q->selectRaw('COALESCE(SUM(goods_transaction_details.qty), 0)');
            }, 'stock_in')
            ->selectSub(function ($q) use ($warehouseId, $companyId) {
                $q->from('goods_transaction_details')
                    ->join('goods_transactions', 'goods_transaction_details.goods_transaction_id', '=', 'goods_transactions.id')
                    ->whereColumn('goods_transaction_details.vehicle_equipment_id', 'vehicle_equipments.id')
                    ->where('goods_transactions.type', 'issue');
                
                if ($companyId) {
                    $q->where('goods_transactions.company_id', $companyId);
                }
                
                if ($warehouseId) {
                    $q->join('warehouse_movements', 'warehouse_movements.goods_transaction_detail_id', '=', 'goods_transaction_details.id')
                      ->join('warehouse_activities', 'warehouse_movements.warehouse_activity_id', '=', 'warehouse_activities.id')
                      ->where('warehouse_activities.warehouse_id', $warehouseId);
                }
                $q->selectRaw('COALESCE(SUM(goods_transaction_details.qty), 0)');
            }, 'stock_out');

        try {
            if ($request->filled('search')) {
                $search = $request->search;
                $caseSensitive = $request->boolean('case_sensitive');

                $query->where(function ($q) use ($search, $caseSensitive) {
                    if ($caseSensitive) {
                        $q->where('vehicle_equipments.name', 'LIKE BINARY', "%$search%")
                            ->orWhere('vehicle_equipments.code', 'LIKE BINARY', "%$search%");
                    } else {
                        $q->where('vehicle_equipments.name', 'like', "%$search%")
                            ->orWhere('vehicle_equipments.code', 'like', "%$search%");
                    }
                });
            }

            foreach ($this->vehicleEquipmentTable as $field) {
                if ($request->filled($field)) {
                    $query->where("vehicle_equipments.{$field}", $request->$field);
                }
            }

            if ($request->has('is_stock')) {
                $isStock = filter_var($request->is_stock, FILTER_VALIDATE_BOOLEAN);
                if ($isStock) {
                    $query->havingRaw('(COALESCE(stock_in, 0) - COALESCE(stock_out, 0)) > 0');
                } else {
                    $query->havingRaw('(COALESCE(stock_in, 0) - COALESCE(stock_out, 0)) <= 0');
                }
            }

            $allowedSort = $this->vehicleEquipmentTable;

            $sortBy = in_array($request->sort_by, $allowedSort)
                ? $request->sort_by
                : 'id';

            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy("vehicle_equipments.{$sortBy}", $sortOrder);

            $perPage = $request->per_page ?? 10;

            $data = $query->paginate($perPage);

            $data->getCollection()->transform(function ($item) {
                $item->available_stock = (int)$item->stock_in - (int)$item->stock_out;
                unset($item->stock_in);
                unset($item->stock_out);
                return $item;
            });

            return $this->responseSuccess($data, 'Vehicle equipment list retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while retrieving Vehicle Equipment data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Vehicle equipment list retrieved Failed', 500);
        }
    }

    /**
     * Get vehicle equipment details.
     */
    public function show(Request $request, string $id)
    {
        try {
            $equipment = VehicleEquipment::where('id', $id)->select($this->vehicleEquipmentTable)->firstOrFail();

            // Calculate global stock info
            $equipment->total_purchase = (int) $equipment->goodsTransactionDetails()
                ->whereHas('goodsTransaction', fn($t) => $t->where('type', 'receipt'))
                ->sum('qty');
                
            $equipment->total_sales = (int) $equipment->goodsTransactionDetails()
                ->whereHas('goodsTransaction', fn($t) => $t->where('type', 'issue'))
                ->sum('qty');

            $equipment->available_stock = $equipment->total_purchase - $equipment->total_sales;
            $equipment->stock = $equipment->available_stock;

            $warehouseId = $request->warehouse_id;
            $companyId = $request->company_id;
            if ($request->filled('company_id')) {
                $company = \App\Models\Company::with('warehouse')->find($request->company_id);
                if ($company && $company->warehouse) {
                    $warehouseId = $company->warehouse->id;
                }
            }

            if ($warehouseId || $companyId) {
                $equipment['available_stock_warehouse'] = $equipment->getAvailableStock($warehouseId, null, $companyId);

                $detailsQuery = \App\Models\GoodsTransactionDetail::with('goodsTransaction')
                    ->where('vehicle_equipment_id', $equipment->id)
                    ->whereHas('goodsTransaction', function ($q) use ($companyId) {
                        if ($companyId) {
                            $q->where('company_id', $companyId);
                        }
                    });

                if ($warehouseId) {
                    $detailsQuery->whereHas('goodsTransaction.company.warehouse', function ($q) use ($warehouseId) {
                        $q->where('id', $warehouseId);
                    });
                }

                $sortBy = $request->get('sort_by', 'id');
                $sortDir = $request->get('sort_dir', 'desc');
                $perPage = $request->get('per_page', 10);

                $equipment['transaction_details'] = $detailsQuery->orderBy($sortBy, $sortDir)->paginate($perPage);
            }

            return $this->responseSuccess($equipment, 'Vehicle equipment retrieved successfully', 200);
        } catch (ModelNotFoundException $err) {
            return $this->responseError('The requested resource could not be found.', 'Resource Not Found', 404);
        } catch (Exception $err) {
            Log::error('Error while retrieving Vehicle Equipment data : '.$err->getMessage());

            return $this->responseError('The requested resource could not be found.', 'Resource Not Found', 404);
        }
    }

    /**
     * Store a new vehicle equipment.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:249',
        ]);

        try {
            $equipment = DB::transaction(function () use ($validated) {
                $validated['code'] = $this->code('', 'perlengkapan');
                return VehicleEquipment::create($validated);
            });

            return $this->responseSuccess($equipment, 'Vehicle equipment created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error while trying to create Vehicle Equipment Data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Error while trying to create Vehicle Equipment Data', 500);
        }
    }

    /**
     * Update a vehicle equipment.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'sometimes|string|max:249',
        ]);

        try {
            $data = array_filter($request->only(['name']), fn ($value) => ! is_null($value) && $value !== '');

            if (empty($data)) {
                return $this->responseError(null, 'No data provided to update', 422);
            }

            $equipment = DB::transaction(function () use ($id, $data) {
                $equipment = VehicleEquipment::findOrFail($id);

                $equipment->update($data);

                return $equipment->fresh();
            });

            return $this->responseSuccess($equipment, 'Vehicle equipment updated successfully', 200);
        } catch (ModelNotFoundException $err) {
            return $this->responseError(null, 'Vehicle Equipment not found', 404);
        } catch (Exception $err) {
            Log::error('Error while trying to update Vehicle Equipment data : '.$err->getMessage());
 
            return $this->responseError($err->getMessage(), 'Error while trying to update Vehicle Equipment data', 500);
        }
    }

    /**
     * Delete a vehicle equipment.
     */
    public function destroy(string $id)
    {
        try {
            $equipment = VehicleEquipment::findOrFail($id);
            $equipment->delete();

            return $this->responseSuccess([], 'Vehicle equipment deleted successfully', 200);
        } catch (ModelNotFoundException $err) {
            return $this->responseError(null, 'Vehicle Equipment not found', 404);
        } catch (Exception $err) {
            Log::error('Error while trying to delete Vehicle Equipment data : '.$err->getMessage());
 
            return $this->responseError($err->getMessage(), 'Vehicle equipment deletion failed');
        }
    }

    /**
     * Import vehicle equipment from Excel.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        try {
            Excel::import(new VehicleEquipmentImport(), $request->file('file'));

            return $this->responseSuccess(null, 'Vehicle Equipment imported successfully', 201);
        } catch (Exception $err) {
            Log::error('Vehicle Equipment import error', [
                'message' => $err->getMessage(),
            ]);

            return $this->responseError($err->getMessage(), 'Vehicle Equipment import error', 500);
        }
    }

    /**
     * Export vehicle equipment to Excel.
     */
    public function export(Request $request)
    {
        try {
            return Excel::download(
                new VehicleEquipmentExport($request, $this->vehicleEquipmentTable),
                'wajira_vehicle_equipment_data.xlsx'
            );
        } catch (Exception $err) {
            Log::error('Error export vehicle equipment : '.$err->getMessage());
    
            return $this->responseError(
                $err->getMessage(),
                'Vehicle equipment export failed',
                500
            );
        }
    }
}
