<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\VehicleEquipment;
use App\Repositories\AuthRepository;
use App\Traits\ResponseTrait;
use App\Traits\VehicleEquipmentTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @group Master Data
 *
 * API for managing vehicle equipment.
 */
class MasterVehicleEquipmentController extends Controller
{
    use ResponseTrait, VehicleEquipmentTrait;

    protected AuthRepository $authRepository;

    // projection
    protected $vehicleEquipmentTable;

    public function __construct(AuthRepository $ar)
    {
        $this->middleware(['permission:master-data:list'])->only(['index', 'show']);
        $this->middleware(['permission:master-data:create'])->only('store');
        $this->middleware(['permission:master-data:edit'])->only('update');
        $this->middleware(['permission:master-data:delete'])->only(['destroy']);

        $this->authRepository = $ar;

        $this->vehicleEquipmentTable = ['id', 'uuid', 'code', 'name', 'created_at'];
    }

    /**
     * List all vehicle equipment.
     */
    public function index(Request $request)
    {
        $query = VehicleEquipment::query();

        $query->select($this->vehicleEquipmentTable);

        try {
            if ($request->filled('search')) {
                $search = $request->search;
                $caseSensitive = $request->boolean('case_sensitive');

                $query->where(function ($q) use ($search, $caseSensitive) {
                    if ($caseSensitive) {
                        $q->where('name', 'LIKE BINARY', "%$search%")
                            ->orWhere('code', 'LIKE BINARY', "%$search%");
                    } else {
                        $q->where('name', 'like', "%$search%")
                            ->orWhere('code', 'like', "%$search%");
                    }
                });
            }

            foreach ($this->vehicleEquipmentTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $allowedSort = $this->vehicleEquipmentTable;

            $sortBy = in_array($request->sort_by, $allowedSort)
                ? $request->sort_by
                : 'id';

            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->per_page ?? 10;

            $data = $query->paginate($perPage);

            return $this->responseSuccess($data, 'Vehicle equipment list retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while retrieving Vehicle Equipment data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Vehicle equipment list retrieved Failed', 500);
        }
    }

    /**
     * Get vehicle equipment details.
     */
    public function show(string $id)
    {
        try {
            $equipment = VehicleEquipment::where('id', $id)->select($this->vehicleEquipmentTable)->first();

            if (! $equipment) {
                return $this->responseError('The requested resource could not be found.', 'Resource Not Found', 404);
            }

            return $this->responseSuccess($equipment, 'Vehicle equipment retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while retrieving Vehicle Equipment data : '.$err->getMessage());

            return $this->responseError('The requested resource could not be found.', 'Resource Not Found', 404);
        }
    }

    /**
     * Store a new vehicle equipment.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:249',
        ]);

        try {
            $equipment = DB::transaction(function () use ($validated) {
                $validated['code'] = $this->generateEquipmentCode();
                return VehicleEquipment::create($validated);
            });

            return $this->responseSuccess($equipment, 'Vehicle equipment created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error while trying to create Vehicle Equipment Data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Error while trying to create Vehicle Equipment Data', 500);
        }
    }

    /**
     * Update a vehicle equipment.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'sometimes|string|max:249',
        ]);

        try {
            $data = array_filter($request->only(['name']), fn ($value) => ! is_null($value) && $value !== '');

            if (empty($data)) {
                return $this->responseError(null, 'No data provided to update', 422);
            }

            $equipment = DB::transaction(function () use ($id, $data) {
                $equipment = VehicleEquipment::findOrFail($id);

                $equipment->update($data);

                return $equipment->fresh();
            });

            return $this->responseSuccess($equipment, 'Vehicle equipment updated successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying to update Vehicle Equipment data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Error while trying to update Vehicle Equipment data', 500);
        }
    }

    /**
     * Delete a vehicle equipment.
     */
    public function destroy(string $id)
    {
        try {
            $equipment = VehicleEquipment::findOrFail($id);
            $equipment->delete();

            return $this->responseSuccess([], 'Vehicle equipment deleted successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying to delete Vehicle Equipment data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Vehicle equipment deletion failed');
        }
    }
}
