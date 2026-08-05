<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\WarehouseSubBlock;
use App\Models\UnitTransactionItemDetail;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Imports\WarehouseSubBlockImport;
use App\Exports\WarehouseSubBlockExport;
use Maatwebsite\Excel\Facades\Excel;

class WarehouseSubBlockController extends Controller
{
    use ResponseTrait;

    protected array $warehouseSubBlockTable;

    public function __construct()
    {
        $this->middleware(['permission:master-data:list|master-data:read'])->only('index');
        $this->middleware(['permission:master-data:list|master-data:read'])->only(['show', 'export']);
        $this->middleware(['permission:master-data:create'])->only(['store', 'import']);
        $this->middleware(['permission:master-data:edit'])->only(['update', 'assignSubBlock', 'makeDefault']);
        $this->middleware(['permission:master-data:delete'])->only(['destroy']);

        $this->warehouseSubBlockTable = ['id', 'uuid', 'warehouse_block_id', 'name', 'description', 'is_active', 'is_default', 'created_at', 'updated_at'];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = WarehouseSubBlock::query();

        $query->select($this->warehouseSubBlockTable);

        $query->with([
            'warehouseBlock:id,uuid,name',
        ]);

        try {
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%")
                        ->orWhere('description', 'like', "%$search%");
                });
            }

            foreach ($this->warehouseSubBlockTable as $field) {
                if ($request->filled($field)) {

                    $value = $request->get($field);

                    if (in_array($field, ['is_active', 'is_default'])) {
                        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    }

                    $query->where($field, $value);
                }
            }

            $sortBy = in_array($request->sort_by, $this->warehouseSubBlockTable) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $data = $query->orderBy($sortBy, $sortOrder)->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'Warehouse Sub Block list retrieved successfully');
        } catch (Exception $err) {
            Log::error('Error retrieving Warehouse Sub Block: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve Warehouse Sub Block list', 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'warehouse_block_id' => 'required|exists:warehouse_blocks,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'string|in:true,false,1,0',
            'is_default' => 'string|in:true,false,1,0',
        ]);

        $validated['is_active'] = $request->is_active == 'true';
        $validated['is_default'] = $request->is_default == 'true';

        try {
            $data = DB::transaction(function () use ($validated) {
                if (!empty($validated['is_default']) && $validated['is_default']) {
                    WarehouseSubBlock::where('warehouse_block_id', $validated['warehouse_block_id'])
                        ->where('is_default', true)
                        ->update(['is_default' => false]);
                }

                return WarehouseSubBlock::create($validated);
            });

            return $this->responseSuccess($data, 'Warehouse Sub Block created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error creating Warehouse Sub Block: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Warehouse Sub Block creation failed', 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $warehouseSubBlock = WarehouseSubBlock::with(['warehouseBlock:id,uuid,name'])->findOrFail($id);
            return $this->responseSuccess($warehouseSubBlock, 'Warehouse Sub Block retrieved successfully');
        } catch (ModelNotFoundException $err) {
            return $this->responseError(null, 'Warehouse Sub Block not found', 404);
        } catch (Exception $err) {
            Log::error('Error showing Warehouse Sub Block: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve Warehouse Sub Block details', 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $warehouseSubBlock = WarehouseSubBlock::findOrFail($id);

            $validated = $request->validate([
                'warehouse_block_id' => 'sometimes|required|exists:warehouse_blocks,id',
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'is_active' => 'sometimes|string|in:true,false,1,0',
                'is_default' => 'sometimes|string|in:true,false,1,0',
            ]);

            $validated['is_active'] = $request->is_active == 'true';
            $validated['is_default'] = $request->is_default == 'true';

            $data = DB::transaction(function () use ($warehouseSubBlock, $validated) {
                $blockId = $validated['warehouse_block_id'] ?? $warehouseSubBlock->warehouse_block_id;

                if (!empty($validated['is_default']) && $validated['is_default']) {
                    WarehouseSubBlock::where('warehouse_block_id', $blockId)
                        ->where('id', '!=', $warehouseSubBlock->id)
                        ->where('is_default', true)
                        ->update(['is_default' => false]);
                }

                $warehouseSubBlock->update($validated);
                return $warehouseSubBlock->fresh();
            });

            return $this->responseSuccess($data, 'Warehouse Sub Block updated successfully');
        } catch (ModelNotFoundException $err) {
            return $this->responseError(null, 'Warehouse Sub Block not found', 404);
        } catch (Exception $err) {
            Log::error('Error updating Warehouse Sub Block: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Warehouse Sub Block update failed', 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $warehouseSubBlock = WarehouseSubBlock::findOrFail($id);

            if ($warehouseSubBlock->is_default) {
                return $this->responseError(null, 'Cannot delete default warehouse sub block', 422);
            }

            DB::transaction(function () use ($warehouseSubBlock) {
                $warehouseSubBlock->delete();
            });

            return $this->responseSuccess(null, 'Warehouse Sub Block deleted successfully');
        } catch (ModelNotFoundException $err) {
            return $this->responseError(null, 'Warehouse Sub Block not found', 404);
        } catch (Exception $err) {
            Log::error('Error deleting Warehouse Sub Block: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Warehouse Sub Block deletion failed', 500);
        }
    }

    public function makeDefault(string $id)
    {
        try {
            $warehouseSubBlock = WarehouseSubBlock::findOrFail($id);

            if ($warehouseSubBlock->is_default) {
                return $this->responseError(null, 'Warehouse Sub Block is already default', 422);
            }
            
            $warehouseSubBlock->makeDefault();
            
            return $this->responseSuccess($warehouseSubBlock->fresh(), 'Warehouse Sub Block made default successfully');
        } catch (ModelNotFoundException $err) {
            return $this->responseError(null, 'Warehouse Sub Block not found', 404);
        } catch (Exception $err) {
            Log::error('Error making default Warehouse Sub Block: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to make default Warehouse Sub Block', 500);
        }
    }

    public function assignSubBlock(Request $request, string $id)
    {
        if (is_string($request->unit_transaction_item_details_ids)) {
            $request->merge([
                'unit_transaction_item_details_ids' => json_decode($request->unit_transaction_item_details_ids, true),
            ]);
        }

        $request->validate([
            'unit_transaction_item_details_ids' => 'sometimes|nullable|array',
            'unit_transaction_item_details_ids.*' => 'required|integer|exists:unit_transaction_item_details,id',
        ]);

        try {
            $warehouseSubBlock = WarehouseSubBlock::findOrFail((int) $id);

            if (!$warehouseSubBlock->is_active) {
                return $this->responseError(null, 'Cannot assign to inactive warehouse sub block', 422);
            }

            $ids = $request->input('unit_transaction_item_details_ids', []);

            $data = DB::transaction(function () use ($warehouseSubBlock, $ids) {
                UnitTransactionItemDetail::where('warehouse_sub_block_id', $warehouseSubBlock->id)
                    ->update(['warehouse_sub_block_id' => null]);

                if (!empty($ids)) {
                    UnitTransactionItemDetail::whereIn('id', $ids)
                        ->update(['warehouse_sub_block_id' => $warehouseSubBlock->id]);
                }

                return $warehouseSubBlock->load('unitTransactionItemDetails');
            });

            return $this->responseSuccess($data, 'Unit Transaction Item Details assigned to Sub Block successfully');
        } catch (ModelNotFoundException $err) {
            return $this->responseError(null, 'Warehouse Sub Block not found', 404);
        } catch (Exception $err) {
            return $this->responseError($err->getMessage(), 'Error assign Unit Transaction Item Details data to Sub block warehouse');
        } 
    }

    /**
     * Import warehouse sub blocks from Excel.
     */
    public function import(Request $request, string $id)
    {
        if ($id == null || !is_numeric($id)) {
            return $this->responseError(null, 'Warehouse block id cannot null', 404);
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        try {
            Excel::import(new WarehouseSubBlockImport((int) $id), $request->file('file'));

            return $this->responseSuccess(null, 'Warehouse Sub Block imported successfully', 201);
        } catch (Exception $err) {
            Log::error('Warehouse Sub Block import error', [
                'message' => $err->getMessage(),
            ]);

            return $this->responseError($err->getMessage(), 'Warehouse Sub Block import error', 500);
        }
    }

    /**
     * Export warehouse sub blocks to Excel.
     */
    public function export(Request $request)
    {
        try {
            return Excel::download(
                new WarehouseSubBlockExport($request, $this->warehouseSubBlockTable),
                'warehouse_sub_block_data.xlsx'
            );
        } catch (Exception $err) {
            Log::error('Error export warehouse sub block : ' . $err->getMessage());

            return $this->responseError(
                $err->getMessage(),
                'Warehouse Sub Block export failed',
                500
            );
        }
    }
}
