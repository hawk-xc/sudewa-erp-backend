<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\DOExpedition;
use App\Models\DOOrderListTarif;
use App\Traits\GlobalCodeNumberTrait;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DOOrderListTarifController extends Controller
{
    use ResponseTrait, GlobalCodeNumberTrait;

    public function index(Request $request)
    {
        $query = DOOrderListTarif::with(['tarif', 'do_order_list']);

        try {
            if ($request->filled('do_orderlist_id')) {
                $query->where('do_orderlist_id', $request->do_orderlist_id);
            }

            $data = $query->latest()->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'DO Order List Tarif list retrieved successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error('Error retrieving DO Order List Tarif: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve DO Order List Tarif');
        }
    }

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only('update');
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);
    }

    public function show($id)
    {
        try {
            $item = DOOrderListTarif::with(['tarif', 'do_order_list', 'doOrderListTarifItems'])->findOrFail($id);
            return $this->responseSuccess($item, 'DO Order List Tarif details retrieved successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            return $this->responseError('DO Order List Tarif not found', 'Not Found', 404);
        }
    }

    /**
     * Add a new tariff item to a DO Order List.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'do_orderlist_id' => 'required|exists:do_order_lists,id',
            'tarif_id' => 'required|exists:tarifs,id',
            'vehicle_type' => 'nullable|in:towing,cdd,fuso',
            'delivery_destination' => 'nullable|string'
        ]);

        try {
            $item = DOOrderListTarif::create([
                'uuid' => (string) Str::uuid(),
                'do_orderlist_id' => $validated['do_orderlist_id'],
                'tarif_id' => $validated['tarif_id'],
                'vehicle_type' => $validated['vehicle_type'] ?? null,
                'delivery_destination' => $validated['delivery_destination'] ?? null
            ]);
            
            // DO Expedition trigger
            $orderList = \App\Models\DOOrderList::with('customer.company')->find($validated['do_orderlist_id']);
            $companySlug = $orderList?->customer?->company?->slug ?? '';
            $expedition = DOExpedition::create([
                'code' => $this->code($companySlug, 'do_ekspedisi'),
                'do_order_list_id' => $validated['do_orderlist_id'],
            ]);

            // Link item to expedition via pivot
            $expedition->order_list_tarifs()->attach($item->id);

            return $this->responseSuccess($item->load('tarif'), 'Tarif item added to order list successfully', 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error('Error adding DO Order List Tarif: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to add tarif item');
        }
    }

    /**
     * Update a tariff item.
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'tarif_id' => 'sometimes|required|exists:tarifs,id',
            'vehicle_type' => 'sometimes|nullable|in:towing,cdd,fuso',
            'delivery_destination' => 'sometimes|nullable|string',
        ]);

        try {
            $item = DOOrderListTarif::findOrFail($id);
            $item->update($validated);

            return $this->responseSuccess($item->load('tarif'), 'Tarif item updated successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error('Error updating DO Order List Tarif: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to update tarif item');
        }
    }

    /**
     * Remove a tariff item from the order list.
     */
    public function destroy($id)
    {
        try {
            $item = DOOrderListTarif::findOrFail($id);
            $item->delete();

            return $this->responseSuccess([], 'Tarif item removed from order list successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error('Error deleting DO Order List Tarif: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to remove tarif item');
        }
    }
}
