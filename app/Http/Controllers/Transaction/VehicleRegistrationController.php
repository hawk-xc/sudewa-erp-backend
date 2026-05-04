<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
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
 * API for managing vehicle registrations.
 */
class VehicleRegistrationController extends Controller
{
    use ResponseTrait, VehicleTrait;

    protected $fillable;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:edit'])->only('update');

        $this->fillable = [
            'vendor_id',
            'process_date',
            'is_already_processed',
            'bpkb_number',
            'bpkb_registration_date',
            'bpkb_received_date',
            'bpkb_physical_status',
            'stnk_registration_date',
            'stnk_received_date',
            'stnk_physical_status',
            'skpd_payment_date',
            'skpd_received_date',
            'skpd_physical_status',
            'tnkb_received_date',
            'tnkb_number',
            'tnkb_physical_status',
            'stck_fee',
            'bbn_registration_fee',
            'notice_fee',
            'pmi_fee',
            'physical_check_fee',
            'nik_validation_fee',
            'garwil_fee',
            'built_up_fee',
            'acceleration_fee',
            'plate_recommendation_fee',
            'service_fee',
            'skpd_fee',
            'stamp_fee',
            'pnbp_bpkb',
        ];
    }

    /**
     * List all vehicle registrations.
     */
    public function index(Request $request)
    {
        $query = VehicleRegistration::query()->with(['vendor:id,name,code', 'vehicleData']);

        try {
            foreach ($this->fillable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $sortBy = $request->sort_by ?? 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $data = $query->orderBy($sortBy, $sortOrder)->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'Vehicle Registrations retrieved successfully');
        } catch (Exception $err) {
            Log::error('Error while retrieving Vehicle Registrations: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve Vehicle Registrations', 500);
        }
    }

    /**
     * Update vehicle registration (Edit).
     */
    public function update(Request $request, string $id)
    {
        foreach (['bpkb_physical_status', 'stnk_physical_status', 'skpd_physical_status', 'tnkb_physical_status'] as $field) {
            if ($request->has($field)) {
                $value = $request->input($field);
                if ($value === 'true') $request->merge([$field => true]);
                if ($value === 'false') $request->merge([$field => false]);
            }
        }

        // set already processed to true
        $request->merge(['is_already_processed' => true]);

        $validator = Validator::make($request->all(), [
            'customer_delivery_date' => 'sometimes|nullable|date',
            'process_date' => 'sometimes|nullable|date',
            'bpkb_number' => 'sometimes|nullable|string',
            'bpkb_registration_date' => 'sometimes|nullable|date',
            'bpkb_received_date' => 'sometimes|nullable|date',
            'bpkb_physical_status' => 'sometimes|boolean',
            'stnk_registration_date' => 'sometimes|nullable|date',
            'stnk_received_date' => 'sometimes|nullable|date',
            'stnk_physical_status' => 'sometimes|boolean',
            'skpd_payment_date' => 'sometimes|nullable|date',
            'skpd_received_date' => 'sometimes|nullable|date',
            'skpd_physical_status' => 'sometimes|boolean',
            'tnkb_received_date' => 'sometimes|nullable|date',
            'tnkb_number' => 'sometimes|nullable|string',
            'tnkb_physical_status' => 'sometimes|boolean',
            'stck_fee' => 'sometimes|numeric',
            'bbn_registration_fee' => 'sometimes|numeric',
            'notice_fee' => 'sometimes|numeric',
            'pmi_fee' => 'sometimes|numeric',
            'physical_check_fee' => 'sometimes|numeric',
            'nik_validation_fee' => 'sometimes|numeric',
            'garwil_fee' => 'sometimes|numeric',
            'built_up_fee' => 'sometimes|numeric',
            'acceleration_fee' => 'sometimes|numeric',
            'plate_recommendation_fee' => 'sometimes|numeric',
            'service_fee' => 'sometimes|numeric',
            'skpd_fee' => 'sometimes|numeric',
            'stamp_fee' => 'sometimes|numeric',
            'pnbp_bpkb' => 'sometimes|numeric',
        ]);

        if ($validator->fails()) {
            return $this->responseError($validator->errors(), 'Validation failed', 422);
        }

        try {
            $registration = DB::transaction(function () use ($request, $id) {
                $registration = VehicleRegistration::findOrFail($id);
                $registration->update($request->only($this->fillable));
                return $registration->fresh();
            });

            return $this->responseSuccess($registration, 'Vehicle Registration updated successfully');
        } catch (Exception $err) {
            Log::error('Error while updating Vehicle Registration: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to update Vehicle Registration', 500);
        }
    }

    /**
     * Get vehicle registration details.
     */
    public function show(string $id)
    {
        try {
            $registration = VehicleRegistration::with(['vendor', 'vehicleData'])->findOrFail($id);
            return $this->responseSuccess($registration, 'Vehicle Registration retrieved successfully');
        } catch (Exception $err) {
            Log::error('Error while retrieving Vehicle Registration: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve Vehicle Registration', 500);
        }
    }
}
