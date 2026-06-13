<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\DitlantasProcess;
use App\Models\Person;
use App\Models\VehicleData;
use App\Models\VehicleDataDitlantasProcessed;
use App\Models\VehicleRegistration;
use App\Rules\RightPersonRule;
use App\Traits\GlobalCodeNumberTrait;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DitlantasProcessController extends Controller
{
    use ResponseTrait, GlobalCodeNumberTrait;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only(['store']);
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);
    }

    public function index(Request $request)
    {
        $query = DitlantasProcess::query()->with([
            'vendor:id,uuid,type,name'
        ]);

        try {
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%$search%")
                        ->orWhere('note', 'like', "%$search%")
                        ->orWhereHas('vendor', function ($sub) use ($search) {
                            $sub->where('name', 'like', "%$search%");
                        })
                        ->orWhereHas('vehicleDatas', function ($sub) use ($search) {
                            $sub->where('chassis_number', 'like', "%$search%")
                                ->orWhere('machine_number', 'like', "%$search%");
                        });
                });
            }

            $fields = ['id', 'uuid', 'code', 'vendor_id', 'process_date'];
            foreach ($fields as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $sortBy = in_array($request->sort_by, $fields) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $data = $query->orderBy($sortBy, $sortOrder)->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'Ditlantas Process list retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while retrieving Ditlantas Process list: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve Ditlantas Process list', 500);
        }
    }

    public function show(string $id)
    {
        try {
            $process = DitlantasProcess::with([
                'vendor:id,uuid,type,name',
                'vehicleDatas:id,uuid,chassis_number,machine_number'
            ])->find($id);

            if (!$process) {
                return $this->responseError(null, 'Ditlantas Process not found', 404);
            }

            return $this->responseSuccess($process, 'Ditlantas Process retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while retrieving Ditlantas Process: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve Ditlantas Process details', 500);
        }
    }

    public function store(Request $request)
    {
        if (is_string($request->vehicle_data_ids)) {
            $request->merge([
                'vehicle_data_ids' => json_decode($request->vehicle_data_ids, true),
            ]);
        }

        $validator = Validator::make($request->all(), [
            'vendor_id' => [
                'required',
                'integer',
                new RightPersonRule('vendor')
            ],
            'process_date' => 'required|date',
            'note' => 'nullable|string|max:249',
            'vehicle_data_ids' => 'required|array|min:1',
            'vehicle_data_ids.*' => 'required|integer|exists:vehicle_datas,id',
        ]);

        $validator->after(function ($validator) use ($request) {
            $ids = $request->input('vehicle_data_ids');
            if (empty($ids) || !is_array($ids)) return;

            $alreadyProcessed = VehicleDataDitlantasProcessed::whereIn('vehicle_data_id', $ids)
                ->pluck('vehicle_data_id')
                ->toArray();

            if (!empty($alreadyProcessed)) {
                foreach ($alreadyProcessed as $id) {
                    $validator->errors()->add('vehicle_data_ids', "Vehicle with ID $id has already been processed by Ditlantas.");
                }
            }
        });

        if ($validator->fails()) {
            return $this->responseError($validator->errors(), 'Validation failed', 422);
        }

        try {
            $process = DB::transaction(function () use ($request) {
                $vendor = Person::find($request->vendor_id);
                $companySlug = $vendor?->company?->slug ?? '';

                $ditlantasProcess = DitlantasProcess::create([
                    'code' => $this->code($companySlug, 'ditlantas_input_stnk_bpkb'),
                    'vendor_id' => $request->vendor_id,
                    'process_date' => $request->process_date,
                    'note' => $request->note ?? null,
                ]);

                foreach ($request->vehicle_data_ids as $id) {
                    // vehicle data ditlantas processed
                    VehicleDataDitlantasProcessed::create([
                        'ditlantas_process_id' => $ditlantasProcess->id,
                        'vehicle_data_id' => $id,
                    ]);

                    // vehicle registration data
                    VehicleRegistration::create([
                        'ditlantas_process_id' => $ditlantasProcess->id,
                        'vehicle_data_id' => $id,
                        'process_date' => $request->process_date
                    ]);
                }

                return $ditlantasProcess->load(['vehicleDatas:id,uuid,chassis_number,machine_number', 'vendor:id,uuid,type,name']);
            });

            return $this->responseSuccess($process, 'Ditlantas process created successfully', 201);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error in Ditlantas Process: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Error while trying to process Ditlantas data', 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $ditlantasProcess = DitlantasProcess::findOrFail($id);

            if ($ditlantasProcess->vehicleDocument()->exists()) {
                return $this->responseError(null, 'Ditlantas Process cannot be deleted because it has associated vehicle documents.', 422);
            }

            DB::transaction(function () use ($ditlantasProcess) {
                // Delete pivot records
                VehicleDataDitlantasProcessed::where('ditlantas_process_id', $ditlantasProcess->id)->delete();
                
                // Delete the process
                $ditlantasProcess->delete();
            });

            return $this->responseSuccess(null, 'Ditlantas Process deleted successfully', 200);
        } catch (ModelNotFoundException $err) {
            return $this->responseError(null, 'Ditlantas Process not found', 404);
        } catch (Exception $err) {
            Log::error('Error while deleting Ditlantas Process: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to delete Ditlantas Process', 500);
        }
    }
}
