<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\VehicleDocument;
use App\Models\VehicleDocumentItem;
use App\Traits\ResponseTrait;
use App\Traits\GlobalCodeNumberTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * @group Transaction
 *
 * API for managing vehicle documents (batch documents for BPKB, STNK, etc).
 */
class VehicleDocumentController extends Controller
{
    use ResponseTrait, GlobalCodeNumberTrait;

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
        $query->withCount([
            'vehicleRegistrations as processed_count' => function ($query) {
                $query->where('is_already_processed', true);
            },
            'vehicleRegistrations as unprocessed_count' => function ($query) {
                $query->where('is_already_processed', false);
            }
        ]);

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
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
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
        ], [
            'receipt_date.unique' => 'A vehicle document for this vendor on this receipt date already exists.',
        ], []);

        if ($validator->fails()) {
            return $this->responseError($validator->errors(), 'Validation failed', 422);
        }

        // Check if vendor is valid and has registrations
        $vendor = Person::findOrFail($request->vendor_id);

        if ($vendor->type !== 'vendor') {
            return $this->responseError((object) ['message' => 'The selected person is not a vendor.'], 'Validation failed', 422);
        }

        if ($vendor->vehicleRegistrations()->count() == 0) {
            return $this->responseError((object) ['message' => 'The selected vendor has no vehicle registrations data.'], 'Validation failed', 422);
        }

        // Check if there are any unprocessed vehicle registrations for this vendor
        $unprocessedExists = $vendor->vehicleRegistrations()
            ->where('is_already_processed', false)
            ->exists();

        if (!$unprocessedExists) {
            return $this->responseError((object) ['message' => 'This vendor has no unprocessed vehicle registrations.'], 'Validation failed', 422);
        }

        // Check if there is already a document for this vendor and date that still has unprocessed registrations
        $duplicateWithUnprocessedExists = VehicleDocument::where('vendor_id', $request->vendor_id)
            ->whereDate('receipt_date', $request->receipt_date)
            ->whereHas('vehicleRegistrations', function ($q) {
                $q->where('is_already_processed', false);
            })
            ->exists();

        if ($duplicateWithUnprocessedExists) {
            return $this->responseError((object) ['receipt_date' => ['A vehicle document with unprocessed registrations for this vendor on this receipt date already exists.']], 'Validation failed', 422);
        }

        try {
            $document = DB::transaction(function () use ($request) {
                $vendor = Person::find($request->vendor_id);
                $companySlug = $vendor?->company?->slug ?? '';
                $code = $this->code($companySlug, 'penerimaan_input_stnk_bpkb');

                $document = VehicleDocument::create([
                    'code' => $code,
                    'vendor_id' => $request->vendor_id,
                    'receipt_date' => $request->receipt_date,
                    'description' => $request->description,
                ]);

                return $document;
            });

            return $this->responseSuccess($document, 'Vehicle Document created successfully', 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
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
            $document = VehicleDocument::with([
                'vendor:id,uuid,code,type,name',
                'vendor.vehicleRegistrations',
                'vendor.vehicleRegistrations.vehicleData:id,uuid,dealer_id,region_id,ktp_number,stnk_name,chassis_number,machine_number',
                'vendor.vehicleRegistrations.vehicleData.dealer:id,uuid,company_id,code,name',
                'vendor.vehicleRegistrations.vehicleData.region:id,uuid,code,name',
                'vehicleDocumentItems.vehicleData',
            ])->find($id);

            if (!$document) {
                return $this->responseError(null, 'Vehicle Document not found', 404);
            }

            return $this->responseSuccess($document, 'Vehicle Document retrieved successfully', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
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

        // Check for duplicate document and unprocessed registrations
        if ($request->has('vendor_id') || $request->has('receipt_date')) {
            $document = VehicleDocument::findOrFail($id);
            $vendorId = $request->vendor_id ?? $document->vendor_id;
            $receiptDate = $request->receipt_date ?? $document->receipt_date;

            $vendor = Person::findOrFail($vendorId);
            $unprocessedExists = $vendor->vehicleRegistrations()
                ->where('is_already_processed', false)
                ->exists();

            if (!$unprocessedExists) {
                return $this->responseError((object) ['message' => 'This vendor has no unprocessed vehicle registrations.'], 'Validation failed', 422);
            }

            // Check if there is already another document for this vendor and date that still has unprocessed registrations
            $duplicateWithUnprocessedExists = VehicleDocument::where('vendor_id', $vendorId)
                ->whereDate('receipt_date', $receiptDate)
                ->where('id', '!=', $id)
                ->whereHas('vehicleRegistrations', function ($q) {
                    $q->where('is_already_processed', false);
                })
                ->exists();

            if ($duplicateWithUnprocessedExists) {
                return $this->responseError((object) ['receipt_date' => ['A vehicle document with unprocessed registrations for this vendor on this receipt date already exists.']], 'Validation failed', 422);
            }
        }

        try {
            $document = DB::transaction(function () use ($request, $id) {
                $document = VehicleDocument::findOrFail($id);
                $document->update($request->only(['vendor_id', 'receipt_date', 'description']));
                return $document->fresh();
            });

            return $this->responseSuccess($document, 'Vehicle Document updated successfully', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
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
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error('Error while deleting Vehicle Document: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to delete Vehicle Document', 500);
        }
    }
}
