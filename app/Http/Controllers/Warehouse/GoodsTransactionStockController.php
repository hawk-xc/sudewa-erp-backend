<?php
 
namespace App\Http\Controllers\Warehouse;
 
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Material;
use App\Models\VehicleEquipment;
use App\Models\Warehouse;
use App\Models\WarehouseActivity;
use App\Models\WarehouseMovement;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
 
class GoodsTransactionStockController extends Controller
{
    use ResponseTrait;
 
    public function index(Request $request)
    {
        $request->validate([
            'company_id' => 'required|exists:companies,id',
        ]);
 
        try {
            $companyId = (int) $request->company_id;
            $warehouseId = Company::findOrFail((int) $companyId)->warehouse->id;
 
            if ($companyId == 4) {
                // VehicleEquipment for company 4
                $query = VehicleEquipment::select('vehicle_equipments.*')
                    ->selectSub(function ($query) use ($warehouseId) {
                        $query->from('goods_transaction_details')
                            ->join('warehouse_movements', 'warehouse_movements.goods_transaction_detail_id', '=', 'goods_transaction_details.id')
                            ->join('warehouse_activities', 'warehouse_movements.warehouse_activity_id', '=', 'warehouse_activities.id')
                            ->whereColumn('goods_transaction_details.vehicle_equipment_id', 'vehicle_equipments.id')
                            ->where('warehouse_activities.warehouse_id', $warehouseId)
                            ->where('warehouse_movements.status', 'in')
                            ->selectRaw('COALESCE(SUM(goods_transaction_details.qty), 0)');
                    }, 'stock_in')
                    ->selectSub(function ($query) use ($warehouseId) {
                        $query->from('goods_transaction_details')
                            ->join('warehouse_movements', 'warehouse_movements.goods_transaction_detail_id', '=', 'goods_transaction_details.id')
                            ->join('warehouse_activities', 'warehouse_movements.warehouse_activity_id', '=', 'warehouse_activities.id')
                            ->whereColumn('goods_transaction_details.vehicle_equipment_id', 'vehicle_equipments.id')
                            ->where('warehouse_activities.warehouse_id', $warehouseId)
                            ->where('warehouse_movements.status', 'out')
                            ->selectRaw('COALESCE(SUM(goods_transaction_details.qty), 0)');
                    }, 'stock_out');
            } elseif ($companyId == 5) {
                // Material for company 5
                $query = Material::select('materials.*')
                    ->selectSub(function ($query) use ($warehouseId) {
                        $query->from('goods_transaction_details')
                            ->join('warehouse_movements', 'warehouse_movements.goods_transaction_detail_id', '=', 'goods_transaction_details.id')
                            ->join('warehouse_activities', 'warehouse_movements.warehouse_activity_id', '=', 'warehouse_activities.id')
                            ->whereColumn('goods_transaction_details.material_id', 'materials.id')
                            ->where('warehouse_activities.warehouse_id', $warehouseId)
                            ->where('warehouse_movements.status', 'in')
                            ->selectRaw('COALESCE(SUM(goods_transaction_details.qty), 0)');
                    }, 'stock_in')
                    ->selectSub(function ($query) use ($warehouseId) {
                        $query->from('goods_transaction_details')
                            ->join('warehouse_movements', 'warehouse_movements.goods_transaction_detail_id', '=', 'goods_transaction_details.id')
                            ->join('warehouse_activities', 'warehouse_movements.warehouse_activity_id', '=', 'warehouse_activities.id')
                            ->whereColumn('goods_transaction_details.material_id', 'materials.id')
                            ->where('warehouse_activities.warehouse_id', $warehouseId)
                            ->where('warehouse_movements.status', 'out')
                            ->selectRaw('COALESCE(SUM(goods_transaction_details.qty), 0)');
                    }, 'stock_out');
            } else {
                return $this->responseError(null, 'Invalid company warehouse', 422);
            }
 
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
                });
            }
 
            if ($request->filled('code')) {
                $query->where('code', $request->code);
            }
 
            if ($request->filled('name')) {
                $query->where('name', 'like', "%{$request->name}%");
            }
 
            if ($companyId == 5 && $request->filled('type')) {
                $query->where('type', $request->type);
            }
 
            if ($request->filled('in_stock')) {
                $inStock = filter_var($request->in_stock, FILTER_VALIDATE_BOOLEAN);
                if ($inStock) {
                    $query->havingRaw('(COALESCE(stock_in, 0) - COALESCE(stock_out, 0)) > 0');
                } else {
                    $query->havingRaw('(COALESCE(stock_in, 0) - COALESCE(stock_out, 0)) <= 0');
                }
            }
 
            $data = $query->paginate($request->per_page ?? 10);
 
            $data->getCollection()->transform(function ($item) {
                $item->total_stock = (int)$item->stock_in - (int)$item->stock_out;
                $item->stock_in = (int) $item->stock_in;
                $item->stock_out = (int) $item->stock_out;
                return $item;
            });
 
            return $this->responseSuccess($data, 'Successfully fetch goods transaction stock data', 200);
        } catch (Exception $err) {
            Log::error('Error while fetch goods transaction stock data : '.$err->getMessage());
 
            return $this->responseError(null, $err->getMessage(), 500);
        }
    }
}
