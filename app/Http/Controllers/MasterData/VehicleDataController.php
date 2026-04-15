<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\VehicleData;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VehicleDataController extends Controller
{
    use ResponseTrait;

    protected $vehicleDataTable;

    public function __construct()
    {
        $this->middleware(['permission:master-data:list'])->only(['index', 'show']);
        $this->middleware(['permission:master-data:create'])->only('store');
        $this->middleware(['permission:master-data:edit'])->only('update');
        $this->middleware(['permission:master-data:delete'])->only(['destroy']);

        $this->vehicleDataTable = [
            'id', 'uuid', 'dealer_id', 'region_id', 'invoice_number', 'invoice_date', 'invoice_receive_date', 'vehicle_type',
            'ktp_number', 'phone_number', 'occupation', 'stnk_name', 'stnk_address', 'village', 'district', 'sub_village', 'sub_district', 'regency', 'postal_code',
            'motorcycle_brand', 'motorcycle_type', 'motorcycle_category', 'motorcycle_model', 'manufacture_year', 'engine_capacity', 'color', 'price', 
            'chassis_number', 'engine_number', 'form_ab', 'pib', 'tpt_number', 'sut_number', 'srut_number', 'fuel_type', 'created_at', 'updated_at'
        ];
    }

    public function index(Request $request)
    {
        $query = VehicleData::query();

        $query->with(['dealer:id,name,code', 'region:id,name']);

        try {
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('invoice_number', 'like', "%$search%")
                        ->orWhere('stnk_name', 'like', "%$search%")
                        ->orWhere('chassis_number', 'like', "%$search%")
                        ->orWhere('engine_number', 'like', "%$search%");
                });
            }

            foreach ($this->vehicleDataTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $sortBy = in_array($request->sort_by, $this->vehicleDataTable) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $data = $query->orderBy($sortBy, $sortOrder)->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'Vehicle Data list retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while retrieving Vehicle Data: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Vehicle Data list retrieved Failed', 500);
        }
    }

    public function show(string $id)
    {
        try {
            $vehicleData = VehicleData::with(['dealer:id,name,code', 'region:id,name'])->find($id);

            if (!$vehicleData) {
                return $this->responseError(null, 'Vehicle Data not found', 404);
            }

            return $this->responseSuccess($vehicleData, 'Vehicle Data retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while retrieving Vehicle Data: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Vehicle Data retrieved Failed', 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'dealer_id' => 'required|integer|exists:persons,id',
            'region_id' => 'required|integer|exists:regions,id',
            'invoice_number' => 'required|string|max:249',
            'invoice_date' => 'required|date',
            'invoice_receive_date' => 'sometimes|date',
            'vehicle_type' => 'required|string|max:249',
            'ktp_number' => 'sometimes|string|max:249',
            'phone_number' => 'sometimes|string|max:249',
            'occupation' => 'sometimes|string|max:249',
            'stnk_name' => 'required|string|max:249',
            'stnk_address' => 'required|string|max:249',
            'village' => 'sometimes|string|max:249',
            'district' => 'sometimes|string|max:249',
            'sub_village' => 'sometimes|string|max:249',
            'sub_district' => 'sometimes|string|max:249',
            'regency' => 'sometimes|string|max:249',
            'postal_code' => 'sometimes|string|max:249',
            'motorcycle_brand' => 'required|string|max:249',
            'motorcycle_type' => 'required|string|max:249',
            'motorcycle_category' => 'sometimes|string|max:249',
            'motorcycle_model' => 'sometimes|string|max:249',
            'manufacture_year' => 'sometimes|integer',
            'engine_capacity' => 'sometimes|integer',
            'color' => 'sometimes|string|max:249',
            'price' => 'sometimes|integer',
            'chassis_number' => 'required|string|max:249',
            'engine_number' => 'required|string|max:249',
            'form_ab' => 'sometimes|string|max:249',
            'pib' => 'sometimes|string|max:249',
            'tpt_number' => 'sometimes|string|max:249',
            'sut_number' => 'sometimes|string|max:249',
            'srut_number' => 'sometimes|string|max:249',
            'fuel_type' => 'sometimes|string|max:249',
        ]);

        try {
            $vehicleData = DB::transaction(function () use ($validated) {
                return VehicleData::create($validated);
            });

            return $this->responseSuccess($vehicleData, 'Vehicle Data created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error while creating Vehicle Data: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Error while trying to create Vehicle Data', 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'dealer_id' => 'sometimes|integer|exists:persons,id',
            'region_id' => 'sometimes|integer|exists:regions,id',
            'invoice_number' => 'sometimes|string|max:249',
            'invoice_date' => 'sometimes|date',
            'invoice_receive_date' => 'sometimes|date',
            'vehicle_type' => 'sometimes|string|max:249',
            'ktp_number' => 'sometimes|string|max:249',
            'phone_number' => 'sometimes|string|max:249',
            'occupation' => 'sometimes|string|max:249',
            'stnk_name' => 'sometimes|string|max:249',
            'stnk_address' => 'sometimes|string|max:249',
            'village' => 'sometimes|string|max:249',
            'district' => 'sometimes|string|max:249',
            'sub_village' => 'sometimes|string|max:249',
            'sub_district' => 'sometimes|string|max:249',
            'regency' => 'sometimes|string|max:249',
            'postal_code' => 'sometimes|string|max:249',
            'motorcycle_brand' => 'sometimes|string|max:249',
            'motorcycle_type' => 'sometimes|string|max:249',
            'motorcycle_category' => 'sometimes|string|max:249',
            'motorcycle_model' => 'sometimes|string|max:249',
            'manufacture_year' => 'sometimes|integer',
            'engine_capacity' => 'sometimes|integer',
            'color' => 'sometimes|string|max:249',
            'price' => 'sometimes|integer',
            'chassis_number' => 'sometimes|string|max:249',
            'engine_number' => 'sometimes|string|max:249',
            'form_ab' => 'sometimes|string|max:249',
            'pib' => 'sometimes|string|max:249',
            'tpt_number' => 'sometimes|string|max:249',
            'sut_number' => 'sometimes|string|max:249',
            'srut_number' => 'sometimes|string|max:249',
            'fuel_type' => 'sometimes|string|max:249',
        ]);

        try {
            $vehicleData = DB::transaction(function () use ($request, $id) {
                $vehicleData = VehicleData::findOrFail($id);
                $data = $request->only($this->vehicleDataTable);
                $vehicleData->update(array_filter($data, fn($v) => !is_null($v)));
                return $vehicleData->fresh();
            });

            return $this->responseSuccess($vehicleData, 'Vehicle Data updated successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while updating Vehicle Data: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Error while trying to update Vehicle Data', 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $vehicleData = VehicleData::findOrFail($id);
            $vehicleData->delete();

            return $this->responseSuccess([], 'Vehicle Data deleted successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while deleting Vehicle Data: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Vehicle Data deletion failed', 500);
        }
    }
}
