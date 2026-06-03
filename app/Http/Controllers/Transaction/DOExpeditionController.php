<?php

namespace App\Http\Controllers\Transaction;

use App\Exports\DOExpeditionExport;
use App\Http\Controllers\Controller;
use App\Models\DOExpedition;
use App\Models\DOOrderList;
use App\Rules\RightPersonRule;
use App\Traits\GlobalCodeNumberTrait;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
 
class DOExpeditionController extends Controller
{
    use ResponseTrait, GlobalCodeNumberTrait;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show', 'export', 'checkDOCode']);
        $this->middleware(['permission:transaction:edit'])->only('update');
    }

    public function index(Request $request): JsonResponse
    {
        $query = DOExpedition::with([
            'vehicle:id,uuid,registration_number,type', 
            'driver:id,uuid,name', 
            'order_list:id,uuid,code,vehicle_type',
            'order_list.customer'
        ]);

        try {
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where('code', 'like', "%$search%");
            }

            if ($request->filled('with_driver') && $request->with_driver == 'true') {
                $query->whereHas('driver', function ($q) {
                    $q->where('type', 'driver');
                });
            }

            if ($request->filled('do_order_list_id')) {
                $query->where('do_order_list_id', $request->do_order_list_id);
            }

            if ($request->filled('is_printed')) {
                $query->where('is_printed', $request->boolean('is_printed'));
            }

            $data = $query->latest()->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'DO Expedition list retrieved successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error retrieving DO Expedition: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve DO Expedition');
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $doExpedition = DOExpedition::with(['vehicle', 'driver', 'order_list.customer', 'order_list.tarifs'])
                ->findOrFail($id);
            return $this->responseSuccess($doExpedition, 'DO Expedition retrieved successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            return $this->responseError('DO Expedition not found', 'Not Found', 404);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $doExpedition = DOExpedition::findOrFail($id);
        $orderListId = $request->input('order_list_id') ?? $doExpedition->do_order_list_id;
        $vehicleType = null;
        if ($orderListId) {
            $orderList = DOOrderList::find($orderListId);
            $vehicleType = $orderList?->vehicle_type;
        }

        $messages = [
            'vehicle_id.exists' => $vehicleType 
                ? "The selected vehicle is invalid or its type does not match the required vehicle type for this order: '{$vehicleType}'."
                : "The selected vehicle does not exist.",
            'driver_id.exists' => "The selected driver is invalid or does not have 'driver' type.",
        ];

        $validated = $request->validate([
            'order_list_id' => 'sometimes|required|exists:do_order_lists,id',
            'date' => 'sometimes|required|date',
            'vehicle_id' => [
                'sometimes',
                'required',
                Rule::exists('vehicle_fleets', 'id')->where(function ($query) use ($vehicleType) {
                    if ($vehicleType) {
                        $query->where('type', $vehicleType);
                    }
                }),
            ],
            'driver_id' => [
                'sometimes',
                'required',
                new RightPersonRule('driver'),
            ],
            'is_printed' => 'sometimes|boolean',
        ], $messages);

        try {
            $doExpedition->update($validated);
            return $this->responseSuccess($doExpedition, 'DO Expedition updated successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error updating DO Expedition: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to update DO Expedition');
        }
    }

    public function export(Request $request)
    {
        try {
            return Excel::download(new DOExpeditionExport($request), 'do_expedition_data.xlsx');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error exporting DO Expedition: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to export DO Expedition');
        }
    }

    public function checkDOCode()
    {
        try {
            $nextCode = $this->code('wjt', 'do_ekspedisi');

            return $this->responseSuccess([
                'next_code' => $nextCode
            ], 'Next DO Expedition code retrieved successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error generating next DO Expedition code: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to generate next DO Expedition code');
        }
    }
}
