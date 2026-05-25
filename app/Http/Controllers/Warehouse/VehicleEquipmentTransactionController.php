<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\VehicleEquipmentTransaction;
use App\Models\VehicleEquipmentTransactionDetail;
use App\Repositories\AuthRepository;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @group Warehouse
 *
 * API for managing vehicle equipment transactions.
 */
class VehicleEquipmentTransactionController extends Controller
{
    use ResponseTrait;

    protected AuthRepository $authRepository;

    // projection
    protected array $transactionTable;

    public function __construct(AuthRepository $ar)
    {
        $this->middleware(['permission:warehouse:list'])->only(['index', 'show']);
        $this->middleware(['permission:warehouse:create'])->only('store');
        $this->middleware(['permission:warehouse:edit'])->only('update');
        $this->middleware(['permission:warehouse:delete'])->only(['destroy']);

        $this->authRepository = $ar;

        $this->transactionTable = [
            'id',
            'uuid',
            'code',
            'person_id',
            'vehicle_fleet_id',
            'supplier_name',
            'type',
            'transaction_date',
            'purchase_amount',
            'location',
            'category',
            'description',
            'created_at'
        ];
    }

    /**
     * List all vehicle equipment transactions.
     */
    public function index(Request $request)
    {
        $query = VehicleEquipmentTransaction::query();

        $query->select($this->transactionTable);

        try {
            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $caseSensitive = $request->boolean('case_sensitive');

                $query->where(function ($q) use ($search, $caseSensitive) {
                    if ($caseSensitive) {
                        $q->where('code', 'LIKE BINARY', "%$search%")
                            ->orWhere('supplier_name', 'LIKE BINARY', "%$search%")
                            ->orWhere('location', 'LIKE BINARY', "%$search%")
                            ->orWhere('category', 'LIKE BINARY', "%$search%");
                    } else {
                        $q->where('code', 'like', "%$search%")
                            ->orWhere('supplier_name', 'like', "%$search%")
                            ->orWhere('location', 'like', "%$search%")
                            ->orWhere('category', 'like', "%$search%");
                    }
                });
            }

            foreach ($this->transactionTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $allowedSort = $this->transactionTable;
            $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $query->with(['VehicleEquipmentTransactionDetails.vehicleEquipment', 'driver:id,uuid,name', 'vehicleFleet:id,name']);

            $perPage = $request->per_page ?? 10;
            $data = $query->paginate($perPage);

            return $this->responseSuccess($data, 'Vehicle equipment transactions retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while retrieving vehicle equipment transactions : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Failed to retrieve vehicle equipment transactions', 500);
        }
    }

    /**
     * Get a specific vehicle equipment transaction.
     */
    public function show(string $id)
    {
        try {
            $transaction = VehicleEquipmentTransaction::select($this->transactionTable)
                ->with(['VehicleEquipmentTransactionDetails.vehicleEquipment', 'driver:id,uuid,name', 'vehicleFleet:id,name'])
                ->findOrFail($id);

            return $this->responseSuccess($transaction, 'Vehicle equipment transaction retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while retrieving vehicle equipment transaction : '.$err->getMessage());

            return $this->responseError('The requested resource could not be found.', 'Resource Not Found', 404);
        }
    }

    /**
     * Store a new vehicle equipment transaction.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:receipt,dispatch',
            'person_id' => 'nullable|exists:persons,id',
            'vehicle_fleet_id' => 'nullable|exists:vehicle_fleets,id',
            'supplier_name' => 'nullable|string|max:255',
            'transaction_date' => 'required|date',
            'purchase_amount' => 'nullable|numeric|min:0',
            'location' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'details' => 'required|array|min:1',
            'details.*.vehicle_equipment_id' => 'required|exists:vehicle_equipment,id',
        ]);

        try {
            $transaction = DB::transaction(function () use ($validated) {
                $validated['code'] = $this->generateTransactionCode($validated['type']);
                
                $trx = VehicleEquipmentTransaction::create($validated);

                foreach ($validated['details'] as $detail) {
                    $trx->VehicleEquipmentTransactionDetails()->create([
                        'vehicle_equipment_id' => $detail['vehicle_equipment_id'],
                    ]);
                }

                return $trx->load(['VehicleEquipmentTransactionDetails.vehicleEquipment', 'driver:id,uuid,name', 'vehicleFleet:id,name']);
            });

            return $this->responseSuccess($transaction, 'Vehicle equipment transaction created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error while creating vehicle equipment transaction : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Failed to create vehicle equipment transaction', 500);
        }
    }

    /**
     * Update an existing vehicle equipment transaction.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'person_id' => 'sometimes|nullable|exists:persons,id',
            'vehicle_fleet_id' => 'sometimes|nullable|exists:vehicle_fleets,id',
            'supplier_name' => 'sometimes|nullable|string|max:255',
            'transaction_date' => 'sometimes|required|date',
            'purchase_amount' => 'sometimes|nullable|numeric|min:0',
            'location' => 'sometimes|nullable|string|max:255',
            'category' => 'sometimes|nullable|string|max:255',
            'description' => 'sometimes|nullable|string',
            'details' => 'sometimes|array|min:1',
            'details.*.vehicle_equipment_id' => 'required|exists:vehicle_equipment,id',
        ]);

        try {
            $transaction = DB::transaction(function () use ($id, $validated) {
                $trx = VehicleEquipmentTransaction::findOrFail($id);

                // Filter out details for the main model update
                $headerData = array_filter(
                    $validated,
                    fn ($key) => $key !== 'details',
                    ARRAY_FILTER_USE_KEY
                );

                $trx->update($headerData);

                if (isset($validated['details'])) {
                    // Delete old details
                    $trx->VehicleEquipmentTransactionDetails()->delete();

                    // Recreate new details
                    foreach ($validated['details'] as $detail) {
                        $trx->VehicleEquipmentTransactionDetails()->create([
                            'vehicle_equipment_id' => $detail['vehicle_equipment_id'],
                        ]);
                    }
                }

                return $trx->fresh(['VehicleEquipmentTransactionDetails.vehicleEquipment', 'driver:id,uuid,name', 'vehicleFleet:id,name']);
            });

            return $this->responseSuccess($transaction, 'Vehicle equipment transaction updated successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while updating vehicle equipment transaction : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Failed to update vehicle equipment transaction', 500);
        }
    }

    /**
     * Delete a vehicle equipment transaction.
     */
    public function destroy(string $id)
    {
        try {
            $transaction = VehicleEquipmentTransaction::findOrFail($id);
            
            DB::transaction(function () use ($transaction) {
                $transaction->delete();
            });

            return $this->responseSuccess([], 'Vehicle equipment transaction deleted successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while deleting vehicle equipment transaction : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Failed to delete vehicle equipment transaction', 500);
        }
    }

    /**
     * Generate code for vehicle equipment transactions.
     * Format: TVE-IN-0001 or TVE-OUT-0001
     */
    private function generateTransactionCode(string $type): ?string
    {
        if (! in_array($type, ['receipt', 'dispatch'], true)) {
            return null;
        }

        $prefix = $type === 'receipt' ? 'TVE-IN' : 'TVE-OUT';

        $lastTransaction = VehicleEquipmentTransaction::where('code', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->first();

        $lastNumber = 0;

        if ($lastTransaction) {
            $lastNumber = (int) substr($lastTransaction->code, -4);
        }

        $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);

        return "{$prefix}-{$newNumber}";
    }
}
