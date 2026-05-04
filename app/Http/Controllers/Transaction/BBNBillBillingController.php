<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\BBNBillBilling;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BBNBillBillingController extends Controller
{
    use ResponseTrait;

    protected $bbnBillBillingTable;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only('update');
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);

        $this->bbnBillBillingTable = [
            'id',
            'uuid',
            'bbn_bill_id',
            'total_payment',
            'created_at',
        ];
    }

    public function index(Request $request)
    {
        $query = BBNBillBilling::with(['bbnBill']);

        try {
            foreach ($this->bbnBillBillingTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $sortBy = in_array($request->sort_by, $this->bbnBillBillingTable) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $data = $query->orderBy($sortBy, $sortOrder)->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'BBN Bill Billing list retrieved successfully');
        } catch (Exception $err) {
            Log::error('Error retrieving BBN Bill Billing: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'BBN Bill Billing list retrieved Failed', 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'bbn_bill_id' => 'required|exists:bbn_bills,id',
            'total_payment' => 'required|numeric',
        ]);

        try {
            $data = BBNBillBilling::create($validated);
            return $this->responseSuccess($data, 'BBN Bill Billing created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error creating BBN Bill Billing: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'BBN Bill Billing creation failed', 500);
        }
    }

    public function show($id)
    {
        try {
            $data = BBNBillBilling::with(['bbnBill', 'bbnBillBillingItems.cash'])->findOrFail($id);
            return $this->responseSuccess($data, 'BBN Bill Billing retrieved successfully');
        } catch (Exception $err) {
            return $this->responseError($err->getMessage(), 'BBN Bill Billing not found', 404);
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'bbn_bill_id' => 'sometimes|required|exists:bbn_bills,id',
            'total_payment' => 'sometimes|required|numeric',
        ]);

        try {
            $billing = BBNBillBilling::findOrFail($id);
            $billing->update($validated);
            return $this->responseSuccess($billing->fresh(), 'BBN Bill Billing updated successfully');
        } catch (Exception $err) {
            Log::error('Error updating BBN Bill Billing: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'BBN Bill Billing update failed', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $billing = BBNBillBilling::findOrFail($id);
            $billing->delete();
            return $this->responseSuccess(null, 'BBN Bill Billing deleted successfully');
        } catch (Exception $err) {
            Log::error('Error deleting BBN Bill Billing: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'BBN Bill Billing deletion failed', 500);
        }
    }
}
