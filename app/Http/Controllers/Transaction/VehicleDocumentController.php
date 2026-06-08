<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\DitlantasProcess;
use App\Models\Person;
use App\Models\VehicleDocument;
use App\Rules\RightPersonRule;
use App\Traits\GlobalCodeNumberTrait;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
    use ResponseTrait, GlobalCodeNumberTrait;

    protected array $vehicleDocumentTable;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only('update');
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);

        $this->vehicleDocumentTable = [
            'id', 'uuid', 'code', 'ditlantas_process_id', 'receipt_date', 'description', 'created_at', 'updated_at'
        ];
    }

    /**
     * List all vehicle documents.
     */
    public function index(Request $request)
    {
        $query = VehicleDocument::query();

        $query->with(['ditlantasProcess:id,code,vendor_id', 'ditlantasProcess.vendor:id,name,code']);
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
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
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
        $validator = Validator::make($request->all(), [
            'ditlantas_process_id' => [
                'required',
                'integer',
                'exists:ditlantas_processed,id'
            ],
            'receipt_date' => 'required|date',
            'description' => 'nullable|string',
        ]);
 
        if ($validator->fails()) {
            return $this->responseError($validator->errors(), 'Validation failed', 422);
        }
 
        // Check if ditlantasProcess is valid and has registrations
        $ditlantasProcess = DitlantasProcess::findOrFail($request->ditlantas_process_id);

        if ($ditlantasProcess->vehicleRegistrations()->count() == 0) {
            return $this->responseError((object) ['message' => 'The selected Ditlantas Process has no vehicle registrations data.'], 'Validation failed', 422);
        }

        // Check if there are any unprocessed vehicle registrations for this ditlantas process
        $unprocessedExists = $ditlantasProcess->vehicleRegistrations()
            ->where('is_already_processed', false)
            ->exists();

        if (!$unprocessedExists) {
            return $this->responseError((object) ['message' => 'This Ditlantas Process has no unprocessed vehicle registrations.'], 'Validation failed', 422);
        }

        // Check if there is already a document for this ditlantas process and date that still has unprocessed registrations
        $duplicateWithUnprocessedExists = VehicleDocument::where('ditlantas_process_id', $request->ditlantas_process_id)
            ->whereDate('receipt_date', $request->receipt_date)
            ->whereHas('vehicleRegistrations', function ($q) {
                $q->where('is_already_processed', false);
            })
            ->exists();

        if ($duplicateWithUnprocessedExists) {
            return $this->responseError((object) ['receipt_date' => ['A vehicle document with unprocessed registrations for this Ditlantas Process on this receipt date already exists.']], 'Validation failed', 422);
        }

        try {
            $document = DB::transaction(function () use ($request, $ditlantasProcess) {
                $vendor = $ditlantasProcess->vendor;
                $companySlug = $vendor?->company?->slug ?? '';
                $code = $this->code($companySlug, 'penerimaan_input_stnk_bpkb');

                $document = VehicleDocument::create([
                    'code' => $code,
                    'ditlantas_process_id' => $request->ditlantas_process_id,
                    'receipt_date' => $request->receipt_date,
                    'description' => $request->description,
                ]);

                return $document;
            });

            return $this->responseSuccess($document, 'Vehicle Document created successfully', 201);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
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
            $document = VehicleDocument::with([
                'ditlantasProcess',
                'ditlantasProcess.vehicleRegistrations',
            ])->withCount([
                'vehicleRegistrations as processed_count' => function ($query) {
                    $query->where('is_already_processed', true);
                },
                'vehicleRegistrations as unprocessed_count' => function ($query) {
                    $query->where('is_already_processed', false);
                }
            ])->find($id);

            if (!$document) {
                return $this->responseError(null, 'Vehicle Document not found', 404);
            }

            return $this->responseSuccess($document, 'Vehicle Document retrieved successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
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
            'ditlantas_process_id' => [
                'sometimes',
                'integer',
                'exists:ditlantas_processed,id'
            ],
            'receipt_date' => 'sometimes|date',
            'description' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->responseError($validator->errors(), 'Validation failed', 422);
        }

        if ($request->has('ditlantas_process_id') || $request->has('receipt_date')) {
            $document = VehicleDocument::findOrFail($id);
            $ditlantasProcessId = $request->ditlantas_process_id ?? $document->ditlantas_process_id;
            $receiptDate = $request->receipt_date ?? $document->receipt_date;

            $ditlantasProcess = DitlantasProcess::findOrFail($ditlantasProcessId);
            $unprocessedExists = $ditlantasProcess->vehicleRegistrations()
                ->where('is_already_processed', false)
                ->exists();

            if (!$unprocessedExists) {
                return $this->responseError((object) ['message' => 'This Ditlantas Process has no unprocessed vehicle registrations.'], 'Validation failed', 422);
            }

            $duplicateWithUnprocessedExists = VehicleDocument::where('ditlantas_process_id', $ditlantasProcessId)
                ->whereDate('receipt_date', $receiptDate)
                ->where('id', '!=', $id)
                ->whereHas('vehicleRegistrations', function ($q) {
                    $q->where('is_already_processed', false);
                })
                ->exists();

            if ($duplicateWithUnprocessedExists) {
                return $this->responseError((object) ['receipt_date' => ['A vehicle document with unprocessed registrations for this Ditlantas Process on this receipt date already exists.']], 'Validation failed', 422);
            }
        }

        try {
            $document = DB::transaction(function () use ($request, $id) {
                $document = VehicleDocument::findOrFail($id);
                $document->update($request->only(['ditlantas_process_id', 'receipt_date', 'description']));
                return $document->fresh();
            });

            return $this->responseSuccess($document, 'Vehicle Document updated successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
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
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while deleting Vehicle Document: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to delete Vehicle Document', 500);
        }
    }
}
