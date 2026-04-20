<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\VehicleDocument;
use App\Models\VehicleDocumentItem;
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
 * API for managing vehicle documents (batch documents for BPKB, STNK, etc).
 */
class VehicleDocumentController extends Controller
{
    use ResponseTrait, VehicleTrait;

    protected $vehicleDocumentTable;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only('update');
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);

        $this->vehicleDocumentTable = [
            'id', 'uuid', 'code', 'vendor_id', 'receipt_date', 'description', 'created_at', 'updated_at'
        ];
    }

    /**
     * List all vehicle documents.
     */
    public function index(Request $request)
    {
        $query = VehicleDocument::query();

        $query->with(['vendor:id,name,code']);

        try {
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where('code', 'like', "%$search%")
                      ->orWhere('description', 'like', "%$search%");
            }

            foreach ($this->vehicleDocumentTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $sortBy = in_array($request->sort_by, $this->vehicleDocumentTable) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $data = $query->orderBy($sortBy, $sortOrder)->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'Vehicle Documents retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while retrieving Vehicle Documents: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve Vehicle Documents', 500);
        }
    }

    /**
     * Store new vehicle document with its items.
     */
    public function store(Request $request)
    {
        if (is_string($request->items)) {
            $request->merge([
                'items' => json_decode($request->items, true),
            ]);
        }

        $validator = Validator::make($request->all(), [
            'vendor_id' => 'required|integer|exists:persons,id',
            'receipt_date' => 'required|date',
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.vehicle_data_id' => 'required|integer|exists:vehicle_datas,id',
            'items.*.vendor_id' => 'required|integer|exists:persons,id',
            // Optional item fields
            'items.*.bpkb_number' => 'nullable|string',
            'items.*.tnkb_number' => 'nullable|string',
            // ... (many more fields could be validated here if needed)
        ]);

        if ($validator->fails()) {
            return $this->responseError($validator->errors(), 'Validation failed', 422);
        }

        try {
            $document = DB::transaction(function () use ($request) {
                $document = VehicleDocument::create([
                    'code' => $this->generateRegistrationCode(),
                    'vendor_id' => $request->vendor_id,
                    'receipt_date' => $request->receipt_date,
                    'description' => $request->description,
                ]);

                foreach ($request->items as $itemData) {
                    $document->vehicleDocumentItems()->create($itemData);
                }

                return $document->load('vehicleDocumentItems');
            });

            return $this->responseSuccess($document, 'Vehicle Document created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error while creating Vehicle Document: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to create Vehicle Document', 500);
        }
    }

    /**
     * Get vehicle document details.
     */
    public function show(string $id)
    {
        try {
            $document = VehicleDocument::with(['vendor', 'vehicleDocumentItems.vehicleData'])->find($id);

            if (!$document) {
                return $this->responseError(null, 'Vehicle Document not found', 404);
            }

            return $this->responseSuccess($document, 'Vehicle Document retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while retrieving Vehicle Document: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve Vehicle Document', 500);
        }
    }

    /**
     * Update vehicle document.
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'vendor_id' => 'sometimes|integer|exists:persons,id',
            'receipt_date' => 'sometimes|date',
            'description' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->responseError($validator->errors(), 'Validation failed', 422);
        }

        try {
            $document = DB::transaction(function () use ($request, $id) {
                $document = VehicleDocument::findOrFail($id);
                $document->update($request->only(['vendor_id', 'receipt_date', 'description']));
                return $document->fresh();
            });

            return $this->responseSuccess($document, 'Vehicle Document updated successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while updating Vehicle Document: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to update Vehicle Document', 500);
        }
    }

    /**
     * Delete vehicle document.
     */
    public function destroy(string $id)
    {
        try {
            $document = VehicleDocument::findOrFail($id);
            $document->delete();

            return $this->responseSuccess([], 'Vehicle Document deleted successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while deleting Vehicle Document: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to delete Vehicle Document', 500);
        }
    }
}
