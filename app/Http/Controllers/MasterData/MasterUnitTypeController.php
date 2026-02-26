<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\UnitType;
use App\Repositories\AuthRepository;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MasterUnitTypeController extends Controller
{
    use ResponseTrait;

    protected $unitTypeTable;

    /**
     * @var AuthRepository
     */
    protected AuthRepository $authRepository;

    public function __construct(AuthRepository $ar)
    {
        $this->middleware(['permission:master-data:list'])->only(['index', 'show']);
        $this->middleware(['permission:master-data:create'])->only('store');
        $this->middleware(['permission:master-data:edit'])->only('update');
        $this->middleware(['permission:master-data:delete'])->only(['destroy']);

        $this->authRepository = $ar;

        $this->unitTypeTable = ['id', 'brand_id', 'name', 'capacity', 'unit_type', 'unit_model', 'price', 'netto_weight', 'bruto_weight', 'description', 'created_at'];
    }

    public function index(Request $request)
    {
        try {
            $query = UnitType::with('brand');

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

    public function show($id)
    {
        try {
            $unitType = UnitType::with('brand')->findOrFail($id);

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
            'capacity' => 'nullable|integer|max:500',
            'unit_type' => 'nullable|string|max:255',
            'unit_model' => 'nullable|string|max:255',
            'price' => 'nullable|integer',
            'netto_weight' => 'nullable|integer|max:500',
            'bruto_weight' => 'nullable|integer|max:500',
            'description' => 'nullable|string',
        ]);

        // null
        // $image = $request->file('image')->store('unit-types');

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

    public function update(Request $request, $id)
    {
        $unitType = UnitType::findOrFail($id);

        $validated = $request->validate([
            'company_id' => 'sometimes|exists:companies,id',
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
        ]);

        try {
            $unitType->update($validated);

            return $this->responseSuccess($unitType, 'Unit Type updated successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying update Unit Type : ' . $err->getMessage());

            return $this->responseError(null, 'Internal Server Error', 500);
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

            return $this->responseError(null, 'Internal Server Error', 500);
        }
    }
}
