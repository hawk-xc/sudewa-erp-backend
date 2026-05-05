<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\BBNBillBilling;
use App\Models\BBNBillBillingItem;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BBNBillBillingItemController extends Controller
{
    use ResponseTrait;

    protected $bbnBillBillingItemTable;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only('update');
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);

        $this->bbnBillBillingItemTable = [
            'id',
            'uuid',
            'bbn_bill_billing_id',
            'paid_date',
            'cash_id',
            'amount',
            'created_at',
        ];
    }

    public function index(Request $request)
    {
        $query = BBNBillBillingItem::with(['bbnBillBilling', 'cash']);

        try {
            foreach ($this->bbnBillBillingItemTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $sortBy = in_array($request->sort_by, $this->bbnBillBillingItemTable) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $data = $query->orderBy($sortBy, $sortOrder)->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'BBN Bill Billing Item list retrieved successfully');
        } catch (Exception $err) {
            Log::error('Error retrieving BBN Bill Billing Item: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'BBN Bill Billing Item list retrieved Failed', 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'bbn_bill_billing_id' => 'required|exists:bbn_bill_billings,id',
            'paid_date' => 'required|date',
            'cash_id' => 'required|exists:cashes,id',
            'amount' => 'required|numeric|min:1',
        ]);

        $billing = BBNBillBilling::findOrFail($validated['bbn_bill_billing_id']);
        $remainingBefore = $billing->getRemainingAmount();

        if ($validated['amount'] > $remainingBefore) {
            return $this->responseError('Payment amount exceeds remaining balance of ' . number_format($remainingBefore), 'Overpayment Error', 422);
        }

        try {
            $data = BBNBillBillingItem::create($validated);
            
            // Append remaining payment info to response
            $data->remaining_payment = $billing->getRemainingAmount();
            
            return $this->responseSuccess($data, 'BBN Bill Billing Item created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error creating BBN Bill Billing Item: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'BBN Bill Billing Item creation failed', 500);
        }
    }

    public function show($id)
    {
        try {
            $data = BBNBillBillingItem::with(['bbnBillBilling', 'cash'])->findOrFail($id);
            return $this->responseSuccess($data, 'BBN Bill Billing Item retrieved successfully');
        } catch (Exception $err) {
            return $this->responseError($err->getMessage(), 'BBN Bill Billing Item not found', 404);
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'bbn_bill_billing_id' => 'sometimes|required|exists:bbn_bill_billings,id',
            'paid_date' => 'sometimes|required|date',
            'cash_id' => 'sometimes|required|exists:cashes,id',
            'amount' => 'sometimes|required|numeric',
        ]);

        try {
            $item = BBNBillBillingItem::findOrFail($id);
            $item->update($validated);
            return $this->responseSuccess($item->fresh(), 'BBN Bill Billing Item updated successfully');
        } catch (Exception $err) {
            Log::error('Error updating BBN Bill Billing Item: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'BBN Bill Billing Item update failed', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $item = BBNBillBillingItem::findOrFail($id);
            $item->delete();
            return $this->responseSuccess(null, 'BBN Bill Billing Item deleted successfully');
        } catch (Exception $err) {
            Log::error('Error deleting BBN Bill Billing Item: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'BBN Bill Billing Item deletion failed', 500);
        }
    }
}
