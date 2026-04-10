<?php

namespace App\Http\Controllers\MasterData;

use App\Exports\UnitTypeExport;
use App\Http\Controllers\Controller;
use App\Imports\UnitTypeImport;
use App\Models\Company;
use App\Models\UnitTransactionItemDetail;
use App\Models\UnitType;
use App\Repositories\AuthRepository;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class MasterUnitTypeController extends Controller
{
    use ResponseTrait;

    protected $unitTypeTable;

    protected AuthRepository $authRepository;

    public function __construct(AuthRepository $ar)
    {
        $this->middleware(['permission:master-data:list'])->only(['index', 'show']);
        $this->middleware(['permission:master-data:create'])->only('store');
        $this->middleware(['permission:master-data:edit'])->only('update');
        $this->middleware(['permission:master-data:delete'])->only(['destroy']);

        $this->authRepository = $ar;

        $this->unitTypeTable = ['id', 'brand_id', 'name', 'capacity', 'unit_type', 'unit_model', 'price', 'netto_weight', 'bruto_weight', 'description', 'buy_price', 'sell_price', 'created_at'];
    }

    public function index(Request $request)
    {
        try {
            $query = UnitType::with('brand:id,name');

            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%");
                });
            }

            if ($request->filled('brand_id')) {
                $query->where('brand_id', $request->brand_id);
            }

            if ($request->filled('unit_type')) {
                $query->where('unit_type', $request->unit_type);
            }

            if ($request->filled('model_type')) {
                $query->where('model_type', $request->model_type);
            }

            if ($request->filled('min_netto_weight')) {
                $query->where('netto_weight', '>=', $request->min_netto_weight);
            }

            if ($request->filled('max_netto_weight')) {
                $query->where('netto_weight', '<=', $request->max_netto_weight);
            }

            if ($request->filled('min_bruto_weight')) {
                $query->where('bruto_weight', '>=', $request->min_bruto_weight);
            }

            if ($request->filled('max_bruto_weight')) {
                $query->where('bruto_weight', '<=', $request->max_bruto_weight);
            }

            $unitTypes = $request->filled('per_page') ? $query->paginate($request->per_page) : $query->get();

            return $this->responseSuccess($unitTypes, 'Unit Types retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying get Unit Types : ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function show(Request $request, int $id)
    {
        try {
            $unitType = UnitType::with('brand')->findOrFail($id);

            if ($request->filled('company_id')) {
                $company = Company::findOrFail($request->company_id);
                $warehouseId = $company->warehouse->id;

                $unitType['available_stock'] = $unitType->getRealStock($warehouseId);
                $unitType['forecasted_stock'] = $unitType->getForecastStock($warehouseId);

                $detailsQuery = UnitTransactionItemDetail::query()
                    ->where('in_stock', true)
                    ->whereHas('unitTransactionItem', function ($q) use ($unitType) {
                        $q->where('unit_type_id', $unitType->id);
                    })
                    ->whereHas('unitTransactionItem.unitTransaction', function ($q) use ($warehouseId) {
                        $q->where('warehouse_id', $warehouseId);
                    });

                if ($request->filled('color')) {
                    $detailsQuery->where('color', 'like', '%' . $request->color . '%');
                }

                if ($request->filled('machine_number')) {
                    $detailsQuery->where('machine_number', 'like', '%' . $request->machine_number . '%');
                }

                if ($request->filled('chassis_number')) {
                    $detailsQuery->where('chassis_number', 'like', '%' . $request->chassis_number . '%');
                }

                $sortBy = $request->get('sort_by', 'id');
                $sortDir = $request->get('sort_dir', 'desc');

                $allowedSort = ['id', 'color', 'machine_number', 'chassis_number', 'created_at'];

                if (!in_array($sortBy, $allowedSort)) {
                    $sortBy = 'id';
                }

                $detailsQuery->orderBy($sortBy, $sortDir);

                $perPage = $request->get('per_page', 10);

                $unitType['unit_item_details'] = $detailsQuery->paginate($perPage);
            }

            return $this->responseSuccess($unitType, 'Unit Type retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying get Unit Type : ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:unit_types,code',
            'brand_id' => 'required|exists:brands,id',
            'name' => 'required|string|max:255',
            'capacity' => 'nullable|decimal:0,2|max:100',
            'unit_type' => 'nullable|string|max:255',
            'unit_model' => 'nullable|string|max:255',
            'netto_weight' => 'nullable|integer|max:500',
            // 'bruto_weight' => 'nullable|integer|max:500',
            'buy_price' => 'nullable|integer',
            'sell_price' => 'nullable|integer',
            'description' => 'nullable|string',
        ]);

        // bruto formula
        $validated['bruto_weight'] = $validated['netto_weight'] + 3;

        try {
            $unitType = DB::transaction(function () use ($validated) {
                $unitType = UnitType::create($validated);

                return $unitType;
            });

            return $this->responseSuccess($unitType->load('brand'), 'Unit Type created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error while trying create Unit Type : ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Failed to create Unit Type', 500);
        }
    }

    public function update(Request $request, int $id)
    {
        $unitType = UnitType::findOrFail($id);

        $validated = $request->validate([
            'brand_id' => 'sometimes|exists:brands,id',
            'name' => 'sometimes|string|max:255',
            'capacity' => 'nullable|decimal:0,2|max:100',
            'unit_type' => 'nullable|string|max:255',
            'unit_model' => 'nullable|string|max:255',
            'price' => 'nullable|integer',
            'netto_weight' => 'nullable|integer|max:500',
            // 'bruto_weight' => 'nullable|integer|max:500',
            'buy_price' => 'nullable|integer',
            'sell_price' => 'nullable|integer',
            'description' => 'nullable|string',
        ]);

        // bruto formula in Kg
        $validated['bruto_weight'] = $validated['netto_weight'] + 3;

        try {
            DB::transaction(function () use ($validated, $unitType) {
                $unitType->update($validated);
            });

            return $this->responseSuccess($unitType->fresh(), 'Unit Type updated successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying update Unit Type : ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $unitType = UnitType::findOrFail($id);
            $unitType->delete();

            return $this->responseSuccess(null, 'Unit Type deleted successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying delete Unit Type : ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        try {
            Excel::import(new UnitTypeImport(), $request->file('file'));

            return $this->responseSuccess(null, 'Unit Type imported successfully', 201);
        } catch (Exception $err) {
            Log::error('Unit Type import error', [
                'message' => $err->getMessage(),
            ]);

            return $this->responseError($err->getMessage(), 'Unit Type import error', 500);
        }
    }

    public function export(Request $request)
    {
        try {
            return Excel::download(
                new UnitTypeExport($request, $this->unitTypeTable),
                'wajira_unit_type_data.xlsx'
            );  
        } catch (Exception $err) {
            Log::error('Error export unit type : ' . $err->getMessage());

            return $this->responseError(
                $err->getMessage(),
                'Unit Type export failed',
                500
            );
        }
    }
}
