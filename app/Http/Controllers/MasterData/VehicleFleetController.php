<?php

namespace App\Http\Controllers\MasterData;

use App\Exports\VehicleFleetExport;
use App\Imports\VehicleFleetImport;
use App\Http\Controllers\Controller;
use App\Models\VehicleFleet;
use App\Models\VehicleFleetEquipment;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @group Master Data
 *
 * API for managing vehicle fleets.
 */
class VehicleFleetController extends Controller
{
    use ResponseTrait;

    protected $vehicleFleetTable;
    protected $equipmentFields;

    public function __construct()
    {
        $this->middleware(['permission:master-data:list'])->only(['index', 'show', 'export']);
        $this->middleware(['permission:master-data:create'])->only('store', 'import');
        $this->middleware(['permission:master-data:edit'])->only('update');
        $this->middleware(['permission:master-data:delete'])->only(['destroy']);

        $this->vehicleFleetTable = [
            'id', 'uuid', 'registration_number', 'type', 'machine_number', 'chassis_number', 'stnk_age', 'kir_age', 'stnk_number', 'kir_book', 'created_at', 'updated_at'
        ];

        $this->equipmentFields = [
            'radio_tape', 'jack', 'spare_tire', 'toolkit', 'jack_handle', 'pressure_pipe_1', 'first_aid_kit', 'cigarette_lighter', 'pressure_pipe_2',
            'seat_saddle', 'handlebar_hose', 'fire_extinguisher', 'large_tie_down_strap', 'rearview_mirror', 'ati_foam', 'small_tie_down_strap', 'toolbox_lock', 'service_book'
        ];
    }

    /**
     * List all vehicle fleets.
     */
    public function index(Request $request)
    {
        $request->validate([
            'type' => 'nullable|string|in:fuso,towing,cdd',
        ]);

        $query = VehicleFleet::query();

        try {
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('registration_number', 'like', "%$search%")
                        ->orWhere('type', 'like', "%$search%")
                        ->orWhere('machine_number', 'like', "%$search%")
                        ->orWhere('chassis_number', 'like', "%$search%");
                });
            }

            foreach ($this->vehicleFleetTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $sortBy = in_array($request->sort_by, $this->vehicleFleetTable) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $data = $query->orderBy($sortBy, $sortOrder)->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'Vehicle Fleet list retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while retrieving Vehicle Fleet data: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Vehicle Fleet list retrieved Failed', 500);
        }
    }

    /**
     * Get vehicle fleet details.
     */
    public function show(string $id)
    {
        try {
            $fleet = VehicleFleet::with('vehicleFleetEquipment')->find($id);

            if (!$fleet) {
                return $this->responseError('The requested resource could not be found.', 'Resource Not Found', 404);
            }

            return $this->responseSuccess($fleet, 'Vehicle Fleet retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while retrieving Vehicle Fleet data: ' . $err->getMessage());
            return $this->responseError('The requested resource could not be found.', 'Resource Not Found', 404);
        }
    }

    /**
     * Store a new vehicle fleet.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'registration_number' => 'required|string|max:249',
            'type' => 'required|string|in:fuso,towing,cdd',
            'machine_number' => 'required|string|max:249|unique:vehicle_fleets,machine_number',
            'chassis_number' => 'required|string|max:249|unique:vehicle_fleets,chassis_number',
            'stnk_age' => 'nullable|date',
            'kir_age' => 'nullable|date',
            'stnk_number' => 'nullable|string|max:249',
            'kir_book' => 'nullable|string|max:249',
            'equipment' => 'sometimes|array',
        ]);

        try {
            $fleet = DB::transaction(function () use ($request, $validated) {
                $fleet = VehicleFleet::create($validated);

                $equipmentData = $request->input('equipment', []);
                $filteredEquipment = array_intersect_key($equipmentData, array_flip($this->equipmentFields));
                
                $fleet->vehicleFleetEquipment()->create($filteredEquipment);

                return $fleet->load('vehicleFleetEquipment');
            });

            return $this->responseSuccess($fleet, 'Vehicle Fleet created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error while creating Vehicle Fleet: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Error while trying to create Vehicle Fleet data', 500);
        }
    }

    /**
     * Update a vehicle fleet.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'registration_number' => 'sometimes|string|max:249',
            'type' => 'sometimes|string|in:fuso,towing,cdd',
            'machine_number' => 'sometimes|string|max:249|unique:vehicle_fleets,machine_number,'.$id,
            'chassis_number' => 'sometimes|string|max:249|unique:vehicle_fleets,chassis_number,'.$id,
            'stnk_age' => 'nullable|date',
            'kir_age' => 'nullable|date',
            'stnk_number' => 'nullable|string|max:249',
            'kir_book' => 'nullable|string|max:249',
            
            // Equipment data
            'vehicle_fleet_id' => 'sometimes|integer',
            'radio_tape' => 'sometimes|integer',
            'jack' => 'sometimes|integer',
            'spare_tire' => 'sometimes|integer',
            'toolkit' => 'sometimes|integer',
            'jack_handle' => 'sometimes|integer',
            'pressure_pipe_1' => 'sometimes|integer',
            'first_aid_kit' => 'sometimes|integer',
            'cigarette_lighter' => 'sometimes|integer',
            'pressure_pipe_2' => 'sometimes|integer',
            'seat_saddle' => 'sometimes|integer',
            'handlebar_hose' => 'sometimes|integer',
            'fire_extinguisher' => 'sometimes|integer',
            'large_tie_down_strap' => 'sometimes|integer',
            'rearview_mirror' => 'sometimes|integer',
            'ati_foam' => 'sometimes|integer',
            'small_tie_down_strap' => 'sometimes|integer',
            'toolbox_lock' => 'sometimes|integer',
            'service_book' => 'sometimes|integer',
        ]);

        try {
            $fleet = DB::transaction(function () use ($request, $validated, $id) {
                $fleet = VehicleFleet::findOrFail($id);
                
                $fleet->update($validated);

                $equipmentData = $request->only($this->equipmentFields);
                if ($request->hasAny($this->equipmentFields)) {
                    $fleet->vehicleFleetEquipment()->updateOrCreate(
                        ['vehicle_fleet_id' => $fleet->id],
                        $equipmentData
                    );
                }

                return $fleet->fresh()->load('vehicleFleetEquipment');
            });

            return $this->responseSuccess($fleet, 'Vehicle Fleet updated successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while updating Vehicle Fleet: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Error while trying to update Vehicle Fleet data', 500);
        }
    }

    /**
     * Delete a vehicle fleet.
     */
    public function destroy(string $id)
    {
        try {
            $fleet = VehicleFleet::findOrFail($id);
            $fleet->delete();

            return $this->responseSuccess([], 'Vehicle Fleet deleted successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while deleting Vehicle Fleet: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Vehicle Fleet deletion failed', 500);
        }
    }

    /**
     * Import vehicle fleets from Excel.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        try {
            Excel::import(new VehicleFleetImport(), $request->file('file'));

            return $this->responseSuccess(null, 'Vehicle Fleet imported successfully', 201);
        } catch (Exception $err) {
            Log::error('Vehicle Fleet import error: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Vehicle Fleet import error', 500);
        }
    }

    /**
     * Export vehicle fleets to Excel.
     */
    public function export(Request $request)
    {
        $request->validate([
            'type' => 'nullable|string|in:fuso,towing,cdd',
        ]);

        try {
            return Excel::download(
                new VehicleFleetExport($request, $this->vehicleFleetTable),
                'wajira_vehicle_fleet_data.xlsx'
            );
        } catch (Exception $err) {
            Log::error('Error export Vehicle Fleet: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Vehicle Fleet export failed', 500);
        }
    }
}
