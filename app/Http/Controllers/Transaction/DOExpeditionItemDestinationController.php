<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\DOExpeditionItem;
use App\Models\DOExpeditionItemDestination;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DOExpeditionItemDestinationController extends Controller
{
    use ResponseTrait;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only('update');
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);
    }

    public function index(Request $request)
    {
        $query = DOExpeditionItemDestination::query();

        if ($request->filled('do_expedition_item_id')) {
            $query->where('do_expedition_item_id', $request->do_expedition_item_id);
        }

        $data = $query->orderBy('order_number', 'asc')->get();
        return $this->responseSuccess($data, 'DO Expedition Item Destinations retrieved successfully');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'do_expedition_item_id' => 'required|exists:do_expedition_items,id',
            'destination' => 'required|string',
            'driver_note' => 'nullable|string',
            'order_number' => 'nullable|integer',
        ]);

        try {
            if (!$request->filled('order_number')) {
                $maxOrder = DOExpeditionItemDestination::where('do_expedition_item_id', $validated['do_expedition_item_id'])
                    ->max('order_number');
                $validated['order_number'] = ($maxOrder ?? 0) + 1;
            }

            $destination = DOExpeditionItemDestination::create($validated);
            return $this->responseSuccess($destination, 'Destination added successfully', 201);
        } catch (Exception $err) {
            Log::error('Error adding DO Expedition Item Destination: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to add destination');
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'destination' => 'sometimes|required|string',
            'driver_note' => 'nullable|string',
            'order_number' => 'sometimes|required|integer',
        ]);

        try {
            $destination = DOExpeditionItemDestination::findOrFail($id);
            $destination->update($validated);
            return $this->responseSuccess($destination, 'Destination updated successfully');
        } catch (Exception $err) {
            Log::error('Error updating DO Expedition Item Destination: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to update destination');
        }
    }

    public function destroy($id)
    {
        try {
            $destination = DOExpeditionItemDestination::findOrFail($id);
            $destination->delete();
            return $this->responseSuccess([], 'Destination deleted successfully');
        } catch (Exception $err) {
            Log::error('Error deleting DO Expedition Item Destination: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to delete destination');
        }
    }
}
