<?php

namespace App\Http\Controllers\MasterData;

use App\Exports\AssetExport;
use App\Http\Controllers\Controller;
use App\Imports\AssetImport;
use App\Models\Asset;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @group Master Data
 *
 * API for managing assets.
 */
class MasterAssetController extends Controller
{
    use ResponseTrait;

    protected $assetTable;

    public function __construct()
    {
        $this->middleware(['permission:master-data:list'])->only(['index', 'show', 'export']);
        $this->middleware(['permission:master-data:create'])->only('store', 'import');
        $this->middleware(['permission:master-data:edit'])->only('update');
        $this->middleware(['permission:master-data:delete'])->only(['destroy']);

        $this->assetTable = ['id', 'uuid', 'company_id', 'code', 'serial_number', 'purchase_date', 'name', 'type', 'price', 'created_at', 'updated_at'];
    }

    private function generateCode(): string
    {
        $prefix = 'AST';
        $lastAsset = Asset::whereNotNull('code')->orderByDesc('id')->first();
        
        if (! $lastAsset) {
            return $prefix.'-001';
        }

        $lastNumber = (int) substr($lastAsset->code, -3);
        $newNumber = $lastNumber + 1;
        $formattedNumber = str_pad($newNumber, 3, '0', STR_PAD_LEFT);

        return $prefix.'-'.$formattedNumber;
    }

    /**
     * List all assets.
     */
    public function index(Request $request)
    {
        $query = Asset::query();
        $query->select($this->assetTable);

        try {
            if ($request->filled('search')) {
                $search = $request->search;
                $caseSensitive = $request->boolean('case_sensitive');

                $query->where(function ($q) use ($search, $caseSensitive) {
                    if ($caseSensitive) {
                        $q->where('name', 'LIKE BINARY', "%$search%")
                            ->orWhere('code', 'LIKE BINARY', "%$search%")
                            ->orWhere('serial_number', 'LIKE BINARY', "%$search%")
                            ->orWhere('type', 'LIKE BINARY', "%$search%");
                    } else {
                        $q->where('name', 'like', "%$search%")
                            ->orWhere('code', 'like', "%$search%")
                            ->orWhere('serial_number', 'like', "%$search%")
                            ->orWhere('type', 'like', "%$search%");
                    }
                });
            }

            foreach ($this->assetTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $allowedSort = $this->assetTable;

            $sortBy = in_array($request->sort_by, $allowedSort)
                ? $request->sort_by
                : 'id';

            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->per_page ?? 10;
            $data = $query->paginate($perPage);

            return $this->responseSuccess($data, 'Asset list retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error While retrieved Asset data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Asset list retrieved Failed', 500);
        }
    }

    /**
     * Get asset details.
     */
    public function show(string $id)
    {
        try {
            $asset = Asset::select($this->assetTable)->findOrFail($id);

            return $this->responseSuccess($asset, 'Asset retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error While retrieved Asset data : '.$err->getMessage());

            return $this->responseError('The requested resource could not be found.', 'Resource Not Found', 404);
        }
    }

    /**
     * Store a new asset.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|integer|exists:companies,id',
            'code' => 'required|string|unique:assets,code',
            'serial_number' => 'required|string|unique:assets,serial_number',
            'name' => 'required|string|max:255',
            'purchase_date' => 'nullable|date',
            'type' => 'required|in:inventory,vehicles,buildings,land',
            'price' => 'nullable|numeric|min:0',
        ]);

        try {
            $asset = DB::transaction(function () use ($validated) {
                return Asset::create($validated);
            });

            return $this->responseSuccess($asset, 'Asset created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error while trying create Asset Data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Error while trying create Asset Data', 500);
        }
    }

    /**
     * Update an asset.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'company_id' => 'sometimes|integer|exists:companies,id',
            'name' => 'sometimes|string|max:255',
            'serial_number' => 'sometimes|string|unique:assets,serial_number,'.$id,
            'purchase_date' => 'nullable|date',
            'type' => 'sometimes|in:inventory,vehicles,buildings,land',
            'price' => 'nullable|numeric|min:0',
        ]);

        try {
            $data = array_filter($request->only(['company_id', 'name', 'serial_number', 'purchase_date', 'type', 'price']), fn ($value) => $value !== '');

            if (empty($data)) {
                return $this->responseError(null, 'No data provided to update', 422);
            }

            $asset = DB::transaction(function () use ($id, $data) {
                $asset = Asset::findOrFail($id);
                $asset->update($data);

                return $asset->fresh();
            });

            return $this->responseSuccess($asset, 'Asset Update Successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying update Asset data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Error while trying update Asset data', 500);
        }
    }

    /**
     * Delete an asset.
     */
    public function destroy(string $id)
    {
        try {
            $asset = Asset::findOrFail($id);
            $asset->delete();

            return $this->responseSuccess([], 'Asset Deleted Successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying delete Asset data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Asset Deleted Failed', 500);
        }
    }

    /**
     * Import assets from Excel.
     */
    public function import(Request $request, string $id)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        try {
            Excel::import(new AssetImport((int) $id), $request->file('file'));

            return $this->responseSuccess(null, 'Asset imported successfully', 201);
        } catch (Exception $err) {
            Log::error('Asset import error', [
                'message' => $err->getMessage(),
            ]);

            return $this->responseError($err->getMessage(), 'Asset import error', 500);
        }
    }

    /**
     * Export assets to Excel.
     */
    public function export(Request $request)
    {
        try {
            return Excel::download(
                new AssetExport($request, $this->assetTable),
                'wajira_asset_data.xlsx'
            );
        } catch (Exception $err) {
            Log::error('Error export asset : '.$err->getMessage());

            return $this->responseError(
                $err->getMessage(),
                'Asset export failed',
                500
            );
        }
    }
}
