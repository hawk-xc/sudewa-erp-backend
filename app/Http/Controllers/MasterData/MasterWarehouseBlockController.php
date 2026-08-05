<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\WarehouseBlock;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Imports\WarehouseBlockImport;
use App\Exports\WarehouseBlockExport;
use Maatwebsite\Excel\Facades\Excel;

class MasterWarehouseBlockController extends Controller
{
    use ResponseTrait;

    protected array $warehouseBlockTable;

    public function __construct()
    {
        $this->middleware(['permission:master-data:list|master-data:read'])->only('index');
        $this->middleware(['permission:master-data:list|master-data:read'])->only(['show', 'export']);
        $this->middleware(['permission:master-data:create'])->only(['store', 'import']);
        $this->middleware(['permission:master-data:edit'])->only('update');
        $this->middleware(['permission:master-data:delete'])->only(['destroy']);

        $this->warehouseBlockTable = ['id', 'uuid', 'warehouse_id', 'name', 'description', 'created_at', 'updated_at'];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = WarehouseBlock::query();

        $query->select($this->warehouseBlockTable);

        $query->with([
            'warehouse:id,uuid,name',
        ]);

        try {
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%")
                        ->orWhere('description', 'like', "%$search%");
                });
            }

            foreach ($this->warehouseBlockTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $sortBy = in_array($request->sort_by, $this->warehouseBlockTable) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $data = $query->orderBy($sortBy, $sortOrder)->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'Warehouse Block list retrieved successfully');
        } catch (Exception $err) {
            Log::error('Error retrieving Warehouse Block: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve Warehouse Block list', 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        try {
            $data = DB::transaction(function () use ($validated) {
                return WarehouseBlock::create($validated);
            });

            return $this->responseSuccess($data, 'Warehouse Block created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error creating Warehouse Block: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Warehouse Block creation failed', 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $warehouseBlock = WarehouseBlock::with(['warehouse:id,uuid,name', 'warehouseSubBlocks'])->findOrFail($id);
            return $this->responseSuccess($warehouseBlock, 'Warehouse Block retrieved successfully');
        } catch (ModelNotFoundException $err) {
            return $this->responseError(null, 'Warehouse Block not found', 404);
        } catch (Exception $err) {
            Log::error('Error showing Warehouse Block: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve Warehouse Block details', 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $warehouseBlock = WarehouseBlock::findOrFail($id);

            $validated = $request->validate([
                'warehouse_id' => 'sometimes|required|exists:warehouses,id',
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
            ]);

            $data = DB::transaction(function () use ($warehouseBlock, $validated) {
                $warehouseBlock->update($validated);
                return $warehouseBlock->fresh();
            });

            return $this->responseSuccess($data, 'Warehouse Block updated successfully');
        } catch (ModelNotFoundException $err) {
            return $this->responseError(null, 'Warehouse Block not found', 404);
        } catch (Exception $err) {
            Log::error('Error updating Warehouse Block: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Warehouse Block update failed', 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $warehouseBlock = WarehouseBlock::findOrFail($id);

            DB::transaction(function () use ($warehouseBlock) {
                $warehouseBlock->warehouseSubBlocks()->delete();
                $warehouseBlock->delete();
            });

            return $this->responseSuccess(null, 'Warehouse Block deleted successfully');
        } catch (ModelNotFoundException $err) {
            return $this->responseError(null, 'Warehouse Block not found', 404);
        } catch (Exception $err) {
            Log::error('Error deleting Warehouse Block: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Warehouse Block deletion failed', 500);
        }
    }

    /**
     * Import warehouse blocks from Excel.
     */
    public function import(Request $request, string $id)
    {
        if ($id == null || !is_numeric($id)) {
            return $this->responseError(null, 'Warehouse id cannot null', 404);
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        try {
            Excel::import(new WarehouseBlockImport((int) $id), $request->file('file'));

            return $this->responseSuccess(null, 'Warehouse Block imported successfully', 201);
        } catch (Exception $err) {
            Log::error('Warehouse Block import error', [
                'message' => $err->getMessage(),
            ]);

            return $this->responseError($err->getMessage(), 'Warehouse Block import error', 500);
        }
    }

    /**
     * Export warehouse blocks to Excel.
     */
    public function export(Request $request)
    {
        try {
            return Excel::download(
                new WarehouseBlockExport($request, $this->warehouseBlockTable),
                'warehouse_block_data.xlsx'
            );
        } catch (Exception $err) {
            Log::error('Error export warehouse block : ' . $err->getMessage());

            return $this->responseError(
                $err->getMessage(),
                'Warehouse Block export failed',
                500
            );
        }
    }
}
