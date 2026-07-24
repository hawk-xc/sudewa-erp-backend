<?php

namespace App\Http\Controllers\MasterData;

use App\Traits\GlobalCodeNumberTrait;
use App\Exports\AssetExport;
use App\Http\Controllers\Controller;
use App\Imports\AssetImport;
use App\Models\Asset;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
    use ResponseTrait, GlobalCodeNumberTrait;

    protected array $assetTable;

    public function __construct()
    {
        $this->middleware(['permission:master-data:list'])->only(['index', 'show', 'export']);
        $this->middleware(['permission:master-data:create'])->only('store', 'import');
        $this->middleware(['permission:master-data:edit'])->only('update');
        $this->middleware(['permission:master-data:delete'])->only(['destroy']);

        $this->assetTable = ['id', 'uuid', 'company_id', 'code', 'name', 'type', 'created_at', 'updated_at'];
    }

    

    /**
     * List all assets.
     */
    public function index(Request $request)
    {
        $query = Asset::query()->with('financeAsset');
        $selectColumns = array_map(fn($col) => "assets.$col", $this->assetTable);
        $query->select($selectColumns);

        try {
            if ($request->filled('search')) {
                $search = $request->search;
                $caseSensitive = $request->boolean('case_sensitive');

                $query->where(function ($q) use ($search, $caseSensitive) {
                    if ($caseSensitive) {
                        $q->where('assets.name', 'LIKE BINARY', "%$search%")
                            ->orWhere('assets.code', 'LIKE BINARY', "%$search%")
                            ->orWhere('assets.type', 'LIKE BINARY', "%$search%")
                            ->orWhereHas('financeAsset', function ($fq) use ($search) {
                                $fq->where('serial_number', 'LIKE BINARY', "%$search%");
                            });
                    } else {
                        $q->where('assets.name', 'like', "%$search%")
                            ->orWhere('assets.code', 'like', "%$search%")
                            ->orWhere('assets.type', 'like', "%$search%")
                            ->orWhereHas('financeAsset', function ($fq) use ($search) {
                                $fq->where('serial_number', 'like', "%$search%");
                            });
                    }
                });
            }

            foreach ($this->assetTable as $field) {
                if ($request->filled($field)) {
                    $query->where('assets.'.$field, $request->$field);
                }
            }

            foreach (['serial_number', 'purchase_date', 'price'] as $field) {
                if ($request->filled($field)) {
                    $query->whereHas('financeAsset', function ($fq) use ($field, $request) {
                        $fq->where($field, $request->$field);
                    });
                }
            }

            $allowedSort = array_merge($this->assetTable, ['serial_number', 'purchase_date', 'price']);

            $sortBy = in_array($request->sort_by, $allowedSort)
                ? $request->sort_by
                : 'id';

            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            if (in_array($sortBy, ['serial_number', 'purchase_date', 'price'])) {
                $query->leftJoin('finance_assets', 'assets.id', '=', 'finance_assets.asset_id')
                    ->orderBy('finance_assets.' . $sortBy, $sortOrder);
            } else {
                $query->orderBy('assets.' . $sortBy, $sortOrder);
            }

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
            $asset = Asset::with('financeAsset')->select($this->assetTable)->findOrFail($id);

            return $this->responseSuccess($asset, 'Asset retrieved successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
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
            'name' => 'required|string|max:255',
            'type' => 'required|in:inventory,vehicles,buildings,land',
        ]);

        try {
            $asset = DB::transaction(function () use ($validated) {
                $companySlug = \App\Models\Company::where('id', (int) $validated['company_id'])->value('slug') ?? '';
                $validated['code'] = $this->code($companySlug, 'asset');

                $asset = Asset::create([
                    'company_id' => $validated['company_id'],
                    'code' => $validated['code'],
                    'name' => $validated['name'],
                    'type' => $validated['type'],
                ]);

                return $asset->fresh(['financeAsset']);
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
            'type' => 'sometimes|in:inventory,vehicles,buildings,land',
        ]);

        try {
            $data = array_filter($request->only(['company_id', 'name', 'type']), fn ($value) => $value !== '' && $value !== null);

            if (empty($data)) {
                return $this->responseError(null, 'No data provided to update', 422);
            }

            $asset = DB::transaction(function () use ($id, $data) {
                $asset = Asset::findOrFail($id);
                $asset->update($data);

                return $asset->fresh(['financeAsset']);
            });

            return $this->responseSuccess($asset, 'Asset Update Successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
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
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
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
        if ($id == null || !is_numeric($id)) {
            return $this->responseError(null, 'Company id cannot null', 404);
        }

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
