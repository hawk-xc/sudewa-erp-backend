<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\DOOrderListTarifItems;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DOOrderListTarifItemController extends Controller
{
    use ResponseTrait;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only('update');
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = DOOrderListTarifItems::with('doOrderListTarif');

        try {
            if ($request->filled('do_order_list_tarif_id')) {
                $query->where('do_order_list_tarif_id', $request->do_order_list_tarif_id);
            }

            $data = $query->latest()->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'DO Order List Tarif Item list retrieved successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error('Error retrieving DO Order List Tarif Item: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve DO Order List Tarif Item');
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'do_order_list_tarif_id' => 'required|exists:do_order_list_tarifs,id',
            'load_content' => 'required|string',
            'qty' => 'required|integer|min:1',
        ]);

        try {
            $item = DOOrderListTarifItems::create([
                'uuid' => (string) Str::uuid(),
                'do_order_list_tarif_id' => $validated['do_order_list_tarif_id'],
                'load_content' => $validated['load_content'],
                'qty' => $validated['qty']
            ]);

            return $this->responseSuccess($item, 'DO Order List Tarif Item created successfully', 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error('Error creating DO Order List Tarif Item: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to create DO Order List Tarif Item');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $item = DOOrderListTarifItems::with('doOrderListTarif')->findOrFail($id);
            return $this->responseSuccess($item, 'DO Order List Tarif Item details retrieved successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            return $this->responseError('DO Order List Tarif Item not found', 'Not Found', 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'do_order_list_tarif_id' => 'sometimes|required|exists:do_order_list_tarifs,id',
            'load_content' => 'sometimes|required|string',
            'qty' => 'sometimes|required|integer|min:1',
        ]);

        try {
            $item = DOOrderListTarifItems::findOrFail($id);
            $item->update($validated);

            return $this->responseSuccess($item, 'DO Order List Tarif Item updated successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error('Error updating DO Order List Tarif Item: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to update DO Order List Tarif Item');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $item = DOOrderListTarifItems::findOrFail($id);
            $item->delete();

            return $this->responseSuccess([], 'DO Order List Tarif Item deleted successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error('Error deleting DO Order List Tarif Item: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to delete DO Order List Tarif Item');
        }
    }
}
