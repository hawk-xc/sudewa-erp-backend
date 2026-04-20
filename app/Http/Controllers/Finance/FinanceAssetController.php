<?php

namespace App\Http\Controllers\Finance;

use App\Exports\FinanceAssetExport;
use App\Http\Controllers\Controller;
use App\Imports\FinanceAssetImport;
use App\Models\FinanceAsset;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @group Finance
 *
 * API for managing finance assets.
 */
class FinanceAssetController extends Controller
{
    use ResponseTrait;

    protected $financeAssetTable;

    public function __construct()
    {
        $this->middleware(['permission:finance:list'])->only(['index', 'show']);
        $this->middleware(['permission:finance:edit'])->only('update');

        $this->financeAssetTable = [
            'id', 'uuid', 'asset_id', 'serial_number', 'economic_age', 'depreciation',
            'residual_value', 'final_value', 'description', 'created_at', 'updated_at'
        ];
    }

    /**
     * List all finance assets.
     */
    public function index(Request $request)
    {
        $query = FinanceAsset::query()->with('asset:id,code,name,type,purchase_date');
        $query->select($this->financeAssetTable);

        try {
            if ($request->filled('search')) {
                $search = $request->search;
                $caseSensitive = $request->boolean('case_sensitive');

                $query->where(function ($q) use ($search, $caseSensitive) {
                    if ($caseSensitive) {
                        $q->where('serial_number', 'LIKE BINARY', "%$search%")
                            ->orWhere('description', 'LIKE BINARY', "%$search%");
                    } else {
                        $q->where('serial_number', 'like', "%$search%")
                            ->orWhere('description', 'like', "%$search%");
                    }
                });
            }

            foreach ($this->financeAssetTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $allowedSort = $this->financeAssetTable;

            $sortBy = in_array($request->sort_by, $allowedSort)
                ? $request->sort_by
                : 'id';

            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->per_page ?? 10;
            $data = $query->paginate($perPage);

            return $this->responseSuccess($data, 'Finance Asset list retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error While retrieved Finance Asset data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Finance Asset list retrieved Failed', 500);
        }
    }

    /**
     * Get finance asset details.
     */
    public function show(string $id)
    {
        try {
            $asset = FinanceAsset::with('asset')->select($this->financeAssetTable)->findOrFail($id);

            return $this->responseSuccess($asset, 'Finance Asset retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error While retrieved Finance Asset data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Finance Asset retrieved Failed', 404);
        }
    }

    /**
     * Update a finance asset.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'serial_number' => 'nullable|string',
            'economic_age' => 'nullable|integer|min:0',
            'depreciation' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        try {
            $data = array_filter($request->only(['economic_age', 'depreciation', 'description']), fn ($value) => $value !== '' && $value !== null);

            $asset = DB::transaction(function () use ($id, $data) {
                $asset = FinanceAsset::findOrFail($id);
                $asset->update($data);

                return $asset->fresh(['asset']);
            });

            return $this->responseSuccess($asset, 'Finance Asset Update Successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying update Finance Asset data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Error while trying update Finance Asset data', 500);
        }
    }



    /**
     * Import finance assets from Excel.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        try {
            Excel::import(new FinanceAssetImport(), $request->file('file'));

            return $this->responseSuccess(null, 'Finance Asset imported successfully', 201);
        } catch (Exception $err) {
            Log::error('Finance Asset import error', [
                'message' => $err->getMessage(),
            ]);

            return $this->responseError($err->getMessage(), 'Finance Asset import error', 500);
        }
    }

    /**
     * Export finance assets to Excel.
     */
    public function export(Request $request)
    {
        try {
            return Excel::download(
                new FinanceAssetExport($request, $this->financeAssetTable),
                'wajira_finance_asset_data.xlsx'
            );
        } catch (Exception $err) {
            Log::error('Error export finance asset : '.$err->getMessage());

            return $this->responseError(
                $err->getMessage(),
                'Finance Asset export failed',
                500
            );
        }
    }
}
