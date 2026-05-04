<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\VehicleData;
use App\Models\VehicleRegistration;
use App\Traits\ResponseTrait;
use App\Traits\VehicleTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * @group Transaction
 *
 * API for managing vehicle data (customer & vehicle details).
 */
class VehicleDataController extends Controller
{
    use ResponseTrait, VehicleTrait;


    protected $vehicleDataTable;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only('update');
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);

        $this->vehicleDataTable = [
            'id', 'uuid', 'dealer_id', 'region_id', 'invoice_number', 'invoice_date', 'invoice_receive_date', 'vehicle_type',
            'ktp_number', 'phone_number', 'occupation', 'stnk_name', 'stnk_address', 'village', 'district', 'sub_village', 'sub_district', 'regency', 'postal_code',
            'motorcycle_brand', 'motorcycle_type', 'motorcycle_category', 'motorcycle_model', 'manufacture_year', 'engine_capacity', 'color', 'price', 
            'chassis_number', 'engine_number', 'form_ab', 'pib', 'tpt_number', 'sut_number', 'srut_number', 'fuel_type', 'created_at', 'updated_at'
        ];
    }

    /**
     * List all vehicle data.
     */
    public function index(Request $request)
    {
        $query = VehicleData::query();

        $query->with(['dealer:id,name,code', 'region:id,name', 'vehicleRegistration:id,vendor_id,vehicle_data_id,process_date,is_already_processed']);

        if ($request->filled('is_already_processed') && $request->is_already_processed === 'true') {
            $query->where('is_already_processed', false);
        } else {
            $query->where('is_already_processed', true);
        }

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

    /**
     * Get vehicle data details.
     */
    public function show(string $id)
    {
        try {
            $vehicleData = VehicleData::with(['dealer', 'region', 'vehicleRegistration'])->find($id);

            if (!$vehicleData) {
                return $this->responseError(null, 'Vehicle Data not found', 404);
            }

            return $this->responseSuccess($vehicleData, 'Vehicle Data retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while retrieving Vehicle Data: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Vehicle Data retrieved Failed', 500);
        }
    }

    /**
     * Store new vehicle data.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'dealer_id' => 'required|integer|exists:persons,id',
            'region_id' => 'required|integer|exists:regions,id',
            'invoice_number' => 'required|string|max:249',
            'invoice_date' => 'required|date',
            'invoice_receive_date' => 'required|date',
            'vehicle_type' => 'required|in:r2,r3,r4',
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
            'chassis_number' => 'required|string|max:249|unique:vehicle_datas,chassis_number',
            'machine_number' => 'required|string|max:249|unique:vehicle_datas,machine_number',
            'form_ab' => 'sometimes|string|max:249',
            'pib' => 'sometimes|string|max:249',
            'tpt_number' => 'sometimes|string|max:249',
            'sut_number' => 'sometimes|string|max:249',
            'srut_number' => 'sometimes|string|max:249',
            'fuel_type' => 'sometimes|string|max:249',
        ]);

        if ($validator->fails()) {
            return $this->responseError($validator->errors(), 'Validation failed', 422);
        }

        $validated = $validator->validated();

        $dealer = Person::findOrFail($validated['dealer_id']);
        if ($dealer->type !== 'dealer') {
            return $this->responseError('Selected person is not a dealer.', 'Invalid Person Type', 422);
        }

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

    /**
     * Assign vehicle data to registration.
     */
    public function assignRegistration(Request $request)
    {
        if (is_string($request->vehicle_data_ids)) {
            $request->merge([
                'vehicle_data_ids' => json_decode($request->vehicle_data_ids, true),
            ]);
        }

        $validator = Validator::make($request->all(), [
            'vendor_id' => 'required|integer|exists:persons,id',
            'process_date' => 'required|date',
            'vehicle_data_ids' => 'required|array|min:1',
            'vehicle_data_ids.*' => 'required|integer|exists:vehicle_datas,id',
        ]);

        $validator->after(function ($validator) use ($request) {
            $ids = $request->input('vehicle_data_ids');
            if (empty($ids) || !is_array($ids)) return;

            $alreadyProcessed = VehicleRegistration::whereIn('vehicle_data_id', $ids)
                ->where(function($q) {
                    $q->where('is_already_processed', true)
                      ->orWhere('is_already_processed', 1)
                      ->orWhere('is_already_processed', '1');
                })
                ->pluck('vehicle_data_id')
                ->toArray();

            if (!empty($alreadyProcessed)) {
                foreach ($alreadyProcessed as $id) {
                    $validator->errors()->add('vehicle_data_ids', "Vehicle with ID $id has already been processed and cannot be re-assigned.");
                }
            }
        });

        if ($validator->fails()) {
            return $this->responseError($validator->errors(), 'Validation failed', 422);
        }

        try {
            $registrations = DB::transaction(function () use ($request) {
                $results = [];
                foreach ($request->vehicle_data_ids as $id) {
                    $existing = VehicleRegistration::where('vehicle_data_id', $id)->first();
                    
                    if ($existing) {
                        if ($existing->is_already_processed) {
                            continue;
                        }
                        continue;
                    }

                    $results[] = VehicleRegistration::create([
                        'vendor_id' => $request->vendor_id,
                        'vehicle_data_id' => $id,
                        'process_date' => $request->process_date,
                        'is_already_processed' => true,
                    ]);
                }
                return $results;
            });

            return $this->responseSuccess($registrations, 'Vehicle registrations assigned successfully', 201);
        } catch (Exception $err) {
            Log::error('Error while assigning Vehicle Registration: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Error while trying to assign Vehicle Registration', 500);
        }
    }


    /**
     * Update vehicle data.
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
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
            'chassis_number' => 'required|string|max:249|unique:vehicle_datas,chassis_number,'.$id,
            'machine_number' => 'required|string|max:249|unique:vehicle_datas,machine_number,'.$id,
            'form_ab' => 'sometimes|string|max:249',
            'pib' => 'sometimes|string|max:249',
            'tpt_number' => 'sometimes|string|max:249',
            'sut_number' => 'sometimes|string|max:249',
            'srut_number' => 'sometimes|string|max:249',
            'fuel_type' => 'sometimes|string|max:249',
        ]);

        if ($validator->fails()) {
            return $this->responseError($validator->errors(), 'Validation failed', 422);
        }

        if ($request->filled('dealer_id')) {
            $dealer = Person::findOrFail($request->dealer_id);
            if ($dealer->type !== 'dealer') {
                return $this->responseError('Selected person is not a dealer.', 'Invalid Person Type', 422);
            }
        }

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


    /**
     * Delete vehicle data.
     */
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
