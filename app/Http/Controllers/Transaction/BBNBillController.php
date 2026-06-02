<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\BBNBill;
use App\Models\Person;
use App\Models\VehicleData;
use App\Rules\RightPersonRule;
use App\Traits\GlobalCodeNumberTrait;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
 
class BBNBillController extends Controller
{
    use ResponseTrait, GlobalCodeNumberTrait;
 
    protected array $bbnBillTable;
 
    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only('update');
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);
 
        $this->bbnBillTable = [
            'id',
            'uuid',
            'code',
            'dealer_id',
            'bill_date',
            'paid_date',
            'created_at',
        ];
    }
 
    public function index(Request $request)
    {
        $query = BBNBill::with(['dealer:id,name']);
 
        try {
            foreach ($this->bbnBillTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }
 
            $sortBy = in_array($request->sort_by, $this->bbnBillTable) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
 
            $data = $query->orderBy($sortBy, $sortOrder)->paginate($request->per_page ?? 10);
 
            return $this->responseSuccess($data, 'BBN Bill list retrieved successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error retrieving BBN Bill: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve BBN Bill list', 500);
        }
    }
 
    public function store(Request $request)
    {
        $validated = $request->validate([
            'dealer_id' => [
                'required',
                new RightPersonRule('dealer')
            ],
            'bill_date' => 'nullable|date',
            'paid_date' => 'nullable|date',
        ]);
 
        $dealer = Person::findOrFail($validated['dealer_id']);
 
        if (!$request->filled('bill_date')) {
            $validated['bill_date'] = now()->toDateTimeString();
        }

        $notProcessedIds = \App\Models\VehicleRegistration::whereHas('vehicleData', function ($query) use ($validated) {
                $query->where('dealer_id', (int) $validated['dealer_id']);
            })
            ->where('is_already_processed', false)
            ->pluck('id');

        if ($notProcessedIds->isNotEmpty()) {
            return $this->responseError('Vehicle registration data has not been processed yet for IDs: ' . $notProcessedIds->implode(', '), 'Validation failed', 422);
        }

        $notUpdatedIds = \App\Models\VehicleRegistration::whereHas('vehicleData', function ($query) use ($validated) {
                $query->where('dealer_id', (int) $validated['dealer_id']);
            })
            ->where('is_update_additional_data', false)
            ->pluck('id');

        if ($notUpdatedIds->isNotEmpty()) {
            return $this->responseError('Vehicle registration data has not been updated yet for IDs: ' . $notUpdatedIds->implode(', '), 'Validation failed', 422);
        }

        $alreadyExists = BBNBill::where('dealer_id', $validated['dealer_id'])->exists();
        if ($alreadyExists) {
            return $this->responseError('A BBN Bill for this dealer already exists', 'Duplicate data found', 422);
        }
 
        $totalVehicleData = VehicleData::where('dealer_id', (int) $validated['dealer_id'])
            ->whereHas('vehicleRegistration', function ($q) {
                $q->where('is_already_processed', true);
            })
            ->count();
            
        if ($totalVehicleData == 0) {
            return $this->responseError('No vehicle data found for this dealer', 'Data not found', 404);
        }

        try {
            $companySlug = $dealer->company?->slug ?? '';
            $validated['code'] = $this->code($companySlug, 'tagihan_bbn');
            
            $data = BBNBill::create($validated);
            return $this->responseSuccess($data, 'BBN Bill created successfully', 201);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error creating BBN Bill: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'BBN Bill creation failed', 500);
        }
    }

    public function show(string $id)
    {
        try {
            $data = BBNBill::find($id);

            if (!$data) {
                return $this->responseError(null, 'BBN Bill not found', 404);
            }

            $data->load([
                'dealer' => function ($query) {
                    $query->select('id', 'uuid', 'name', 'type');
                },
                'dealer.vehicleDatas' => function ($query) {
                    $query->select('id', 'uuid', 'dealer_id', 'invoice_number', 'stnk_name', 'ktp_number', 'chassis_number', 'machine_number')
                        ->whereHas('vehicleRegistration', function ($q) {
                            $q->where('is_already_processed', true)
                              ->where('is_update_additional_data', true);
                        });
                },
                'dealer.vehicleDatas.vehicleRegistration',
                'bbnBillBillings.bbnBillBillingItems.cash'
            ]);
            return $this->responseSuccess($data, 'BBN Bill retrieved successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error retrieving BBN Bill: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'dealer_id' => [
                'sometimes',
                'required',
                new RightPersonRule('dealer'),
            ],
            'bill_date' => 'sometimes|required|date',
            'paid_date' => 'nullable|date',
        ]);
 
        try {
            $bbnBill = BBNBill::findOrFail($id);
 
            if ($request->filled('dealer_id')) {
                $dealer = Person::findOrFail($validated['dealer_id']);
 
                $unprocessedIds = VehicleData::where('dealer_id', $validated['dealer_id'])
                    ->where(function ($q) {
                        $q->whereDoesntHave('vehicleRegistration')
                          ->orWhereHas('vehicleRegistration', function ($query) {
                              $query->where('is_already_processed', false);
                          });
                    })->pluck('id');

                if ($unprocessedIds->isNotEmpty()) {
                    return $this->responseError('Cannot update BBN Bill. Unprocessed vehicle registrations found for IDs: ' . $unprocessedIds->implode(', '), 'Unprocessed Data Found', 422);
                }

                $alreadyExists = BBNBill::where('dealer_id', $validated['dealer_id'])
                    ->where('id', '!=', $id)
                    ->exists();
                if ($alreadyExists) {
                    return $this->responseError('A BBN Bill for this dealer already exists', 'Duplicate data found', 422);
                }

                $notUpdatedExists = VehicleData::where('dealer_id', $validated['dealer_id'])
                    ->whereHas('vehicleRegistration', function ($query) {
                        $query->where('is_update_additional_data', false);
                    })->exists();

                if ($notUpdatedExists) {
                    return $this->responseError('Vehicle registration data has not been updated yet', 'Validation failed', 422);
                }
            }

            $bbnBill->update($validated);
            return $this->responseSuccess($bbnBill->fresh(), 'BBN Bill updated successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error updating BBN Bill: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'BBN Bill update failed', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $bbnBill = BBNBill::findOrFail($id);

            if ($bbnBill->is_paid || $bbnBill->bbnBillBillings()->exists()) {
                return $this->responseError('BBN Bill has existing payments or is fully paid and cannot be deleted', 'Validation failed', 422);
            }   

            $bbnBill->delete();
            return $this->responseSuccess(null, 'BBN Bill deleted successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error deleting BBN Bill: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'BBN Bill deletion failed', 500);
        }
    }
}
