<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Imports\MaterialImport;
use App\Models\Material;
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
    use ResponseTrait;

    // projection
    protected $materialTable;

    public function __construct()
    {
        $this->middleware(['permission:master-data:list'])->only(['index', 'show']);
        $this->middleware(['permission:master-data:create'])->only('store');
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
        $query = Material::query();

        $query->select($this->materialTable);

        // Actual Stock (Finalized)
        $query->withSum(['materialTransactionDetails as total_purchase' => function ($q) {
            $q->where('in_stock', true)->whereHas('materialTransaction', fn($t) => $t->where('type', 'purchase'));
        }], 'qty');

        $query->withSum(['materialTransactionDetails as total_sales' => function ($q) {
            $q->where('in_stock', true)->whereHas('materialTransaction', fn($t) => $t->where('type', 'sales'));
        }], 'qty');

        // Forecast Stock (Not yet finalized)
        $query->withSum(['materialTransactionDetails as total_purchase_forecast' => function ($q) {
            $q->where('is_forecast', true)->whereHas('materialTransaction', fn($t) => $t->where('type', 'purchase'));
        }], 'qty');

        $query->withSum(['materialTransactionDetails as total_sales_forecast' => function ($q) {
            $q->where('is_forecast', true)->whereHas('materialTransaction', fn($t) => $t->where('type', 'sales'));
        }], 'qty');

        // Average Purchase Price
        $query->withAvg(['materialTransactionDetails as average_price' => function ($q) {
            $q->whereHas('materialTransaction', fn($t) => $t->where('type', 'purchase'));
        }], 'price');

        try {
            if ($request->filled('search')) {
                $search = $request->search;
                $caseSensitive = $request->boolean('case_sensitive');

                $query->where(function ($q) use ($search, $caseSensitive) {
                    if ($caseSensitive) {
                        $q->where('name', 'LIKE BINARY', "%$search%")
                            ->orWhere('code', 'LIKE BINARY', "%$search%");
                    } else {
                        $q->where('name', 'like', "%$search%")
                            ->orWhere('code', 'like', "%$search%");
                    }
                });
            }

            if ($request->boolean('has_transaction')) {
                $query->whereHas('materialTransactionDetails');
            }

            foreach ($this->materialTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $allowedSort = $this->materialTable;

            $sortBy = in_array($request->sort_by, $allowedSort)
                ? $request->sort_by
                : 'id';

            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->per_page ?? 10;

            $data = $query->paginate($perPage)
                ->through(function ($item) {
                    $item->stock = (int) $item->total_purchase - (int) $item->total_sales;
                    $item->forecast_stock = (int) $item->total_purchase_forecast - (int) $item->total_sales_forecast;
                    
                    $item->total_purchased = (int) $item->total_purchase;
                    $item->total_sold = (int) $item->total_sales;
                    $item->average_price = (float) $item->average_price;

                    unset($item->total_purchase, $item->total_sales, $item->total_purchase_forecast, $item->total_sales_forecast);
                    
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
    public function show(string $id)
    {
        try {
            $query = Material::query();
            
            $query->withSum(['materialTransactionDetails as total_purchase' => function ($q) {
                $q->where('in_stock', true)->whereHas('materialTransaction', fn($t) => $t->where('type', 'purchase'));
            }], 'qty');

            $query->withSum(['materialTransactionDetails as total_sales' => function ($q) {
                $q->where('in_stock', true)->whereHas('materialTransaction', fn($t) => $t->where('type', 'sales'));
            }], 'qty');

            $query->withSum(['materialTransactionDetails as total_purchase_forecast' => function ($q) {
                $q->where('is_forecast', true)->whereHas('materialTransaction', fn($t) => $t->where('type', 'purchase'));
            }], 'qty');

            $query->withSum(['materialTransactionDetails as total_sales_forecast' => function ($q) {
                $q->where('is_forecast', true)->whereHas('materialTransaction', fn($t) => $t->where('type', 'sales'));
            }], 'qty');

            // Average Purchase Price
            $query->withAvg(['materialTransactionDetails as average_price' => function ($q) {
                $q->whereHas('materialTransaction', fn($t) => $t->where('type', 'purchase'));
            }], 'price');

            $material = $query->with('materialTransactionDetails.materialTransaction')->findOrFail($id);

            $material->stock = (int) $material->total_purchase - (int) $material->total_sales;
            $material->forecast_stock = (int) $material->total_purchase_forecast - (int) $material->total_sales_forecast;
            
            $material->total_purchased = (int) $material->total_purchase;
            $material->total_sold = (int) $material->total_sales;
            $material->average_price = (float) $material->average_price;

            unset($material->total_purchase, $material->total_sales, $material->total_purchase_forecast, $material->total_sales_forecast);

            return $this->responseSuccess($material, 'Material retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error While retrieved Material data : '.$err->getMessage());

            return $this->responseError('The requested resource could not be found.', 'Resource Not Found', 404);
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
                    $validated['code'] = $this->generateCode();
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
            $material = Material::withCount('materialTransactionDetails')->findOrFail($id);

            if ($material->material_transaction_details_count > 0) {
                return $this->responseError('Cannot delete material because it has associated transactions.', 'Deletion Restricted', 422);
            }

            $material->delete();

            return $this->responseSuccess([], 'Material Deleted Successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying delete Material data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Material Deleted Failed', 500);
        }
    }

    private function generateCode()
    {
        $last = Material::where('code', 'like', 'TM-%')
            ->orderByDesc('id')
            ->first();

        if (! $last) {
            return 'TM-001';
        }

        $lastNumber = (int) substr($last->code, 3);
        $nextNumber = $lastNumber + 1;

        return 'TM-'.str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
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
