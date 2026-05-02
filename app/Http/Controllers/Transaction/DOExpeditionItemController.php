<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\DOExpeditionItem;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class DOExpeditionItemController extends Controller
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
        $query = DOExpeditionItem::with(['customer:id,uuid,name', 'expedition:id,uuid,do_code,date', 'expeditionDestinations']);

        if ($request->filled('do_expedition_id')) {
            $query->where('do_expedition_id', $request->do_expedition_id);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('loading_in')) {
            $query->where('loading_in', 'like', '%' . $request->loading_in . '%');
        }

        if ($request->filled('loading_out')) {
            $query->where('loading_out', 'like', '%' . $request->loading_out . '%');
        }

        if ($request->filled('destination')) {
            $query->whereHas('expeditionDestinations', function ($q) use ($request) {
                $q->where('destination', 'like', '%' . $request->destination . '%');
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('loading_in', 'like', "%$search%")
                    ->orWhere('loading_out', 'like', "%$search%")
                    ->orWhereHas('expeditionDestinations', function ($sq) use ($search) {
                        $sq->where('destination', 'like', "%$search%");
                    });
            });
        }

        $data = $query->orderBy('id', 'desc')->paginate($request->per_page ?? 10);
        return $this->responseSuccess($data, 'DO Expedition Item list retrieved successfully');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'do_expedition_id' => 'required|exists:do_expeditions,id',
            'customer_id' => [
                'required',
                Rule::exists('persons', 'id')->where(function ($query) {
                    $query->where('type', 'customer');
                }),
            ],
            'loading_in' => 'required|string',
            'loading_out' => 'required|string',
            'destination' => 'required|string',
            'driver_note' => 'nullable|string',
            'maps_url' => 'nullable|string',
            'invoice_fee' => 'required|numeric|min:0',
            'additional_cost_fee' => 'nullable|numeric|min:0',
            'other_fee' => 'nullable|numeric|min:0',
            'driver_fee' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $item = DOExpeditionItem::create($validated);
            
            $item->expeditionDestinations()->create([
                'destination' => $validated['destination'],
                'driver_note' => $validated['driver_note'] ?? null,
                'maps_url' => $validated['maps_url'] ?? null,
                'order_number' => 1,
            ]);

            DB::commit();
            return $this->responseSuccess($item->load('expeditionDestinations'), 'DO Expedition Item created successfully', 201);
        } catch (Exception $err) {
            DB::rollBack();
            Log::error('Error creating DO Expedition Item: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to create DO Expedition Item');
        }
    }

    public function show($id)
    {
        try {
            $item = DOExpeditionItem::with(['customer', 'expedition.vehicle', 'expedition.driver', 'expeditionDestinations'])->findOrFail($id);
            return $this->responseSuccess($item, 'DO Expedition Item retrieved successfully');
        } catch (Exception $err) {
            return $this->responseError('DO Expedition Item not found', 'Not Found', 404);
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'customer_id' => [
                'sometimes',
                'required',
                Rule::exists('persons', 'id')->where(function ($query) {
                    $query->where('type', 'customer');
                }),
            ],
            'loading_in' => 'sometimes|required|string',
            'loading_out' => 'sometimes|required|string',
            'invoice_fee' => 'sometimes|required|numeric|min:0',
            'additional_cost_fee' => 'nullable|numeric|min:0',
            'other_fee' => 'nullable|numeric|min:0',
            'driver_fee' => 'nullable|numeric|min:0',
        ]);

        try {
            $item = DOExpeditionItem::findOrFail($id);
            $item->update($validated);
            return $this->responseSuccess($item->load('expeditionDestinations')->fresh(), 'DO Expedition Item updated successfully');
        } catch (Exception $err) {
            Log::error('Error updating DO Expedition Item: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to update DO Expedition Item');
        }
    }

    public function destroy($id)
    {
        try {
            $item = DOExpeditionItem::findOrFail($id);
            $item->delete();
            return $this->responseSuccess([], 'DO Expedition Item deleted successfully');
        } catch (Exception $err) {
            Log::error('Error deleting DO Expedition Item: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to delete DO Expedition Item');
        }
    }
}
