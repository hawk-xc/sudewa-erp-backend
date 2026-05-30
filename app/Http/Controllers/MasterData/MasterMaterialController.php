<?php

namespace App\Http\Controllers\MasterData;

use App\Traits\GlobalCodeNumberTrait;
use App\Http\Controllers\Controller;
use App\Imports\MaterialImport;
use App\Models\Company;
use App\Models\Material;
use App\Models\GoodsTransactionDetail;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @group Master Data
 *
 * API for managing materials.
 */
class MasterMaterialController extends Controller
{
    use ResponseTrait, GlobalCodeNumberTrait;

    // projection
    protected $materialTable;

    public function __construct()
    {
        $this->middleware(['permission:master-data:list'])->only(['index', 'show', 'export']);
        $this->middleware(['permission:master-data:create'])->only('store', 'import');
        $this->middleware(['permission:master-data:edit'])->only('update');
        $this->middleware(['permission:master-data:delete'])->only(['destroy']);

        $this->materialTable = [
            'id',
            'uuid',
            'code',
            'name',
            'price',
            'type',
            'created_at',
        ];
    }

    /**
     * List all materials.
     */
    public function index(Request $request)
    {
        $warehouseId = $request->warehouse_id;
        $companyId = $request->company_id;
        if ($request->filled('company_id')) {
            $company = Company::with('warehouse')->find($request->company_id);
            if ($company && $company->warehouse) {
                $warehouseId = $company->warehouse->id;
            }
        }

        $query = Material::select(array_map(fn($col) => "materials.{$col}", $this->materialTable))
            ->selectSub(function ($q) use ($warehouseId, $companyId) {
                $q->from('goods_transaction_details')
                    ->join('goods_transactions', 'goods_transaction_details.goods_transaction_id', '=', 'goods_transactions.id')
                    ->whereColumn('goods_transaction_details.material_id', 'materials.id')
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
                    ->whereColumn('goods_transaction_details.material_id', 'materials.id')
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
            }, 'stock_out')
            ->selectSub(function ($q) use ($companyId) {
                $q->from('goods_transaction_details')
                    ->join('goods_transactions', 'goods_transaction_details.goods_transaction_id', '=', 'goods_transactions.id')
                    ->whereColumn('goods_transaction_details.material_id', 'materials.id')
                    ->where('goods_transactions.type', 'receipt');
                
                if ($companyId) {
                    $q->where('goods_transactions.company_id', $companyId);
                }
                
                $q->selectRaw('COALESCE(AVG(goods_transaction_details.price), 0)');
            }, 'average_price');

        try {
            if ($request->filled('search')) {
                $search = $request->search;
                $caseSensitive = $request->boolean('case_sensitive');

                $query->where(function ($q) use ($search, $caseSensitive) {
                    if ($caseSensitive) {
                        $q->where('materials.name', 'LIKE BINARY', "%$search%")
                            ->orWhere('materials.code', 'LIKE BINARY', "%$search%");
                    } else {
                        $q->where('materials.name', 'like', "%$search%")
                            ->orWhere('materials.code', 'like', "%$search%");
                    }
                });
            }

            if ($request->boolean('has_transaction')) {
                $query->whereHas('goodsTransactionDetails');
            }

            foreach ($this->materialTable as $field) {
                if ($request->filled($field)) {
                    $query->where("materials.{$field}", $request->$field);
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

            $allowedSort = $this->materialTable;

            $sortBy = in_array($request->sort_by, $allowedSort)
                ? $request->sort_by
                : 'id';

            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy("materials.{$sortBy}", $sortOrder);

            $perPage = $request->per_page ?? 10;

            $data = $query->paginate($perPage);

            $data->getCollection()->transform(function ($item) {
                $item->available_stock = (int) $item->stock_in - (int) $item->stock_out;
                $item->stock = $item->available_stock;
                $item->total_purchased = (int) $item->stock_in;
                $item->total_sold = (int) $item->stock_out;
                $item->average_price = (float) $item->average_price;

                unset($item->stock_in, $item->stock_out);
                
                return $item;
            });

            return $this->responseSuccess($data, 'Material list retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error While retrieved Material data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Material list retrieved Failed', 500);
        }
    }

    /**
     * Get material details.
     */
    public function show(Request $request, string $id)
    {
        try {
            $material = Material::findOrFail($id);

            // Global stock info
            $material->total_purchase = (int) $material->goodsTransactionDetails()
                ->whereHas('goodsTransaction', fn($t) => $t->where('type', 'receipt'))
                ->sum('qty');
                
            $material->total_sales = (int) $material->goodsTransactionDetails()
                ->whereHas('goodsTransaction', fn($t) => $t->where('type', 'issue'))
                ->sum('qty');

            $material->available_stock = $material->total_purchase - $material->total_sales;
            $material->stock = $material->available_stock;

            $material->average_price = (float) $material->goodsTransactionDetails()
                ->whereHas('goodsTransaction', fn($t) => $t->where('type', 'receipt'))
                ->avg('price');

            $warehouseId = $request->warehouse_id;
            $companyId = $request->company_id;
            if ($request->filled('company_id')) {
                $company = Company::with('warehouse')->findOrFail($request->company_id);
                $warehouseId = $company->warehouse->id;
            }

            if ($warehouseId || $companyId) {
                $material['available_stock_warehouse'] = $material->getAvailableStock($warehouseId, null, $companyId);

                $detailsQuery = GoodsTransactionDetail::with('goodsTransaction')
                    ->where('material_id', $material->id)
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

                $material['transaction_details'] = $detailsQuery->orderBy($sortBy, $sortDir)->paginate($perPage);
            }

            return $this->responseSuccess($material, 'Material retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error While retrieved Material data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Resource Not Found', 404);
        }
    }

    /**
     * Store a new material.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:50|unique:materials,code',
            'name' => 'required|string|max:255',
            'price' => 'required|numeric',
            'type' => 'required|string|max:100|in:pcs,set,box',
        ]);

        try {
            $material = DB::transaction(function () use ($validated) {

                // AUTO GENERATE CODE jika kosong
                if (empty($validated['code'])) {
                    $validated['code'] = $this->code('', 'material');
                }

                return Material::create($validated);
            });

            return $this->responseSuccess($material, 'Material created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error while trying create Material Data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Error while trying create Material Data', 500);
        }
    }

    /**
     * Update a material.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'code' => 'sometimes|string|max:50|unique:materials,code,'.$id,
            'name' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric',
            'type' => 'sometimes|string|max:100|in:pcs,set,box',
        ]);

        try {
            $data = array_filter(
                $request->only(['code', 'name', 'price', 'type']),
                fn ($val) => ! is_null($val) && $val !== ''
            );

            if (empty($data)) {
                return $this->responseError(null, 'No data provided to update', 422);
            }

            $material = DB::transaction(function () use ($id, $data) {
                $material = Material::findOrFail($id);
                $material->update($data);

                return $material->fresh();
            });

            return $this->responseSuccess($material, 'Material Updated Successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying update Material data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Error while trying update Material data', 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $material = Material::withCount('goodsTransactionDetails')->findOrFail($id);

            if ($material->goods_transaction_details_count > 0) {
                return $this->responseError('Cannot delete material because it has associated transactions.', 'Deletion Restricted', 422);
            }

            $material->delete();

            return $this->responseSuccess([], 'Material Deleted Successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying delete Material data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Material Deleted Failed', 500);
        }
    }

    

    /**
     * Import materials from Excel.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        try {
            Excel::import(new MaterialImport, $request->file('file'));

            return $this->responseSuccess(null, 'Material imported successfully', 201);
        } catch (Exception $err) {
            Log::error('Material import error', [
                'message' => $err->getMessage(),
            ]);

            return $this->responseError($err->getMessage(), 'Material import error', 500);
        }
    }
}
