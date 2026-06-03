<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\DOOrderList;
use App\Rules\RightPersonRule;
use App\Traits\GlobalCodeNumberTrait;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
 
class DOOrderListController extends Controller
{
    use ResponseTrait, GlobalCodeNumberTrait;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only('update');
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);
    }

    public function index(Request $request)
    {
        $query = DOOrderList::select([
            'id',
            'uuid',
            'code',
            'customer_id',
            'status',
            'vehicle_type',
            'bill_invoice',
            'ppn',
            'created_at'
        ])->with([
            'customer:id,name,code,address', 
            'tarifs',
            'expeditions.order_list_tarifs.tarif'
        ]);

        try {
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where('code', 'like', "%$search%")
                    ->orWhereHas('customer', function ($q) use ($search) {
                        $q->where('name', 'like', "%$search%");
                    });
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $data = $query->latest()->paginate($request->per_page ?? 10);
            
            $data->getCollection()->transform(function ($item) {
                $item->vehicles = $item->expeditions->map(function ($expedition) {
                    return $expedition->vehicle;
                })->filter()->values();
                
                return $item;
            });

            $data->getCollection()->makeHidden(['tarifs', 'expeditions', 'vehicles']);

            return $this->responseSuccess($data, 'DO Order List retrieved successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error retrieving DO Order List: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve DO Order List');
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => [
                'required',
                new RightPersonRule('customer'),
            ],
            'status' => 'sometimes|in:deliver,process,pending,reject',
            'vehicle_type' => 'required|in:fuso,cdd,towing',
            'bill_invoice' => 'nullable|integer',
        ]);

        try {
            $orderList = \Illuminate\Support\Facades\DB::transaction(function () use ($validated) {
                $customer = \App\Models\Person::find($validated['customer_id']);
                $companySlug = $customer?->company?->slug ?? '';

                return DOOrderList::create([
                    'code' => $this->code($companySlug, 'order_list'),
                    'customer_id' => $validated['customer_id'],
                    'status' => $validated['status'] ?? 'pending',
                    'bill_invoice' => $validated['bill_invoice'] ?? null,
                    'vehicle_type' => $validated['vehicle_type'] ?? null,
                    // ppn calculation
                    'ppn' => $validated['bill_invoice'] ? (1.1 * $validated['bill_invoice'] / 100) : 0,
                ]);
            });

            return $this->responseSuccess($orderList, 'DO Order List created successfully', 201);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error creating DO Order List: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to create DO Order List');
        }
    }

    public function show($id)
    {
        try {
            $orderList = DOOrderList::with([
                'customer:id,uuid,company_id,code,type,name', 
                'expeditions:id,uuid,code,do_order_list_id,vehicle_id,driver_id,date,driver_note,is_printed',
                'expeditions.vehicle:id,uuid,registration_number,type,machine_number,chassis_number', 
                'expeditions.order_list_tarifs.doOrderListTarifItems'
            ])->findOrFail($id);
            return $this->responseSuccess($orderList, 'DO Order List details retrieved successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            return $this->responseError('DO Order List not found', 'Not Found', 404);
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'customer_id' => [
                'sometimes',
                'required',
                new RightPersonRule('customer'),
            ],
            'status' => 'sometimes|required|in:deliver,process,pending,reject',
            'vehicle_type' => 'sometimes|in:cdd,fuso,towing',
            'bill_invoice' => 'sometimes|nullable|integer',
        ]);

        try {
            // ppn calculation
            $validated['ppn'] = ($validated['bill_invoice']) ? ($validated['bill_invoice'] * 1.1 / 100) : 0;

            $orderList = DOOrderList::findOrFail($id);
            $orderList->update($validated);

            return $this->responseSuccess($orderList->load('customer'), 'DO Order List updated successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error updating DO Order List: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to update DO Order List');
        }
    }

    public function destroy($id)
    {
        try {
            $orderList = DOOrderList::findOrFail($id);
            $orderList->delete();
            return $this->responseSuccess([], 'DO Order List deleted successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error deleting DO Order List: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to delete DO Order List');
        }
    }
}
