<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\DOOrderList;
use App\Models\DOOrderListTarif;
use App\Traits\DOTrait;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DOOrderListController extends Controller
{
    use ResponseTrait, DOTrait;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only('update');
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);
    }

    public function index(Request $request)
    {
        $query = DOOrderList::with([
            'customer:id,name,code,address', 
            'tarifs',
            'expeditions.vehicle',
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
            
            $data->getCollection()->makeHidden(['tarifs', 'expeditions']);

            return $this->responseSuccess($data, 'DO Order List retrieved successfully');
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
                Rule::exists('persons', 'id')->where(function ($query) {
                    $query->where('type', 'customer');
                }),
            ],
            'status' => 'sometimes|in:deliver,process,pending,reject',
            'bill_invoice' => 'nullable|integer',
        ]);

        try {
            $orderList = DOOrderList::create([
                'code' => $this->generateDOCode('order_list'),
                'customer_id' => $validated['customer_id'],
                'status' => $validated['status'] ?? 'pending',
                'bill_invoice' => $validated['bill_invoice'] ?? null,
                // ppn calculation
                'ppn' => $validated['bill_invoice'] ? (1.1 * $validated['bill_invoice'] / 100) : 0,
            ]);

            return $this->responseSuccess($orderList, 'DO Order List created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error creating DO Order List: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to create DO Order List');
        }
    }

    public function show($id)
    {
        try {
            $orderList = DOOrderList::with([
                'customer', 
                'tarifs', 
                'expeditions.vehicle', 
                'expeditions.order_list_tarifs.tarif'
            ])->findOrFail($id);
            return $this->responseSuccess($orderList, 'DO Order List details retrieved successfully');
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
                Rule::exists('persons', 'id')->where(function ($query) {
                    $query->where('type', 'customer');
                }),
            ],
            'status' => 'sometimes|required|in:deliver,process,pending,reject',
            'bill_invoice' => 'sometimes|nullable|integer',
        ]);

        try {
            // ppn calculation
            $validated['ppn'] = ($validated['bill_invoice']) ? ($validated['bill_invoice'] * 1.1 / 100) : 0;

            $orderList = DOOrderList::findOrFail($id);
            $orderList->update($validated);

            return $this->responseSuccess($orderList->load('customer'), 'DO Order List updated successfully');
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
        } catch (Exception $err) {
            Log::error('Error deleting DO Order List: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to delete DO Order List');
        }
    }
}
