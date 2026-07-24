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
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * @group Finance
 *
 * API for managing finance assets.
 */
class FinanceAssetController extends Controller
{
    use ResponseTrait;

    protected array $financeAssetTable;

    public function __construct()
    {
        $this->middleware(['permission:finance:list'])->only(['index', 'show']);
        $this->middleware(['permission:finance:edit'])->only('update');

        $this->financeAssetTable = [
            'id', 'uuid', 'asset_id', 'serial_number', 'purchase_date', 'price', 'economic_age',
            'description', 'created_at', 'updated_at'
        ];
    }

    /**
     * List all finance assets.
     */
    public function index(Request $request)
    {
        $query = FinanceAsset::query()->with('asset:id,company_id,code,name,type');
        $query->select($this->financeAssetTable);

        try {
            if ($request->filled('search')) {
                $search = $request->search;
                $caseSensitive = $request->boolean('case_sensitive');

                $query->where(function ($q) use ($search, $caseSensitive) {
                    if ($caseSensitive) {
                        $q->where('description', 'LIKE BINARY', "%$search%")
                            ->orWhere('serial_number', 'LIKE BINARY', "%$search%")
                            ->orWhereHas('asset', function ($query) use ($search) {
                                $query->where('code', 'LIKE BINARY', "%$search%");
                            });
                    } else {
                        $q->where('description', 'like', "%$search%")
                            ->orWhere('serial_number', 'like', "%$search%")
                            ->orWhereHas('asset', function ($query) use ($search) {
                                $query->where('code', 'like', "%$search%");
                            });
                    }
                });
            }

            if ($request->filled('company_id')) {
                $query->whereHas('asset', function ($q) use ($request) {
                    $q->where('company_id', $request->company_id);
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

            $data->getCollection()->transform(function ($item) {
                $economicAgeInMonths = ($item->economic_age ?? 0) * 12;
                $depreciationPerMonth = $economicAgeInMonths > 0 ? ($item->price ?? 0) / $economicAgeInMonths : 0;

                $purchaseDate = $item->purchase_date;
                $monthsUsed = 0;
                if ($purchaseDate) {
                    $purchaseCarbon = \Carbon\Carbon::parse($purchaseDate);
                    if ($purchaseCarbon->isPast()) {
                        $monthsUsed = $purchaseCarbon->diffInMonths(\Carbon\Carbon::now());
                    }
                }

                $difference = 48 - $depreciationPerMonth;
                $finalValue = ($item->price ?? 0) - ($depreciationPerMonth * $monthsUsed);

                $item->depreciation_per_month = round($depreciationPerMonth, 2);
                $item->months_used = $monthsUsed;
                $item->difference = round($difference, 2);
                $item->final_value = round($finalValue, 2);

                if ($item->asset) {
                    $item->asset->makeHidden(['financeAsset', 'finance_asset', 'serial_number', 'purchase_date', 'price']);
                }

                return $item;
            });

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
            $asset = FinanceAsset::with('asset:id,company_id,code,name,type')->select($this->financeAssetTable)->findOrFail($id);

            $economicAgeInMonths = ($asset->economic_age ?? 0) * 12;
            $depreciationPerMonth = $economicAgeInMonths > 0 ? ($asset->price ?? 0) / $economicAgeInMonths : 0;

            $purchaseDate = $asset->purchase_date;
            $monthsUsed = 0;
            if ($purchaseDate) {
                $purchaseCarbon = \Carbon\Carbon::parse($purchaseDate);
                if ($purchaseCarbon->isPast()) {
                    $monthsUsed = $purchaseCarbon->diffInMonths(\Carbon\Carbon::now());
                }
            }

            $difference = 48 - $depreciationPerMonth;
            $finalValue = ($asset->price ?? 0) - ($depreciationPerMonth * $monthsUsed);

            $asset->depreciation_per_month = round($depreciationPerMonth, 2);
            $asset->months_used = $monthsUsed;
            $asset->difference = round($difference, 2);
            $asset->final_value = round($finalValue, 2);

            if ($asset->asset) {
                $asset->asset->makeHidden(['financeAsset', 'finance_asset', 'serial_number', 'purchase_date', 'price']);
            }

            return $this->responseSuccess($asset, 'Finance Asset retrieved successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
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
            'economic_age' => 'sometimes|integer|min:0',
            'description' => 'sometimes|string',
            'serial_number' => 'sometimes|string|unique:finance_assets,serial_number,'.$id,
            'purchase_date' => 'nullable|date',
            'price' => 'nullable|numeric|min:0',
        ]);

        try {
            $data = array_filter($request->only(['economic_age', 'description', 'serial_number', 'purchase_date', 'price']), fn ($value) => $value !== '' && $value !== null);

            $asset = DB::transaction(function () use ($id, $data) {
                $asset = FinanceAsset::findOrFail($id);
                $asset->update($data);

                return $asset->fresh(['asset']);
            });

            return $this->responseSuccess($asset, 'Finance Asset Update Successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
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
