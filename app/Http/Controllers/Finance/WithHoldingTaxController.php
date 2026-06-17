<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\WithholdingTax;
use App\Models\Cash;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WithHoldingTaxController extends Controller
{
    use ResponseTrait;

    protected array $withholdingTaxTable;

    public function __construct()
    {
        $this->middleware(['permission:finance:list'])->only(['index', 'show']);
        $this->middleware(['permission:finance:create'])->only('store');
        $this->middleware(['permission:finance:edit'])->only('update');
        $this->middleware(['permission:finance:delete'])->only(['destroy']);

        $this->withholdingTaxTable = [
            'id',
            'source',
            'cash_id',
            'unit_transaction_id',
            'bbn_bill_id',
            'do_invoice_id',
            'withholding_number',
            'withholding_age',
            'pph_amount',
            'pph_description',
            'payment_amount',
            'payment_date',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * Display a listing of withholding taxes.
     */
    public function index(Request $request)
    {
        $query = WithholdingTax::with(['cash', 'unitTransaction', 'bbnBill', 'doInvoice']);

        try {
            foreach ($this->withholdingTaxTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('withholding_number', 'like', "%$search%")
                      ->orWhere('pph_description', 'like', "%$search%");
                });
            }

            $sortBy = in_array($request->sort_by, $this->withholdingTaxTable) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $data = $query->orderBy($sortBy, $sortOrder)->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'Withholding Tax list retrieved successfully');
        } catch (Exception $err) {
            Log::error('Error retrieving Withholding Tax: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve Withholding Tax list', 500);
        }
    }

    /**
     * Store a newly created withholding tax in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'source' => 'required|in:internal,external',
            'cash_id' => 'required|exists:cashes,id',
            'unit_transaction_id' => 'nullable|exists:unit_transactions,id',
            'bbn_bill_id' => 'nullable|exists:bbn_bills,id',
            'do_invoice_id' => 'nullable|exists:do_invoices,id',
            'withholding_number' => 'required|string|max:100|unique:withholding_taxes,withholding_number',
            'withholding_age' => 'required|integer',
            'pph_amount' => 'required|numeric|min:0',
            'pph_description' => 'nullable|string|max:255',
            'payment_amount' => 'nullable|numeric|min:0',
            'payment_date' => 'required|date',
        ]);

        try {
            $data = DB::transaction(function () use ($validated) {
                $withholdingTax = WithholdingTax::create($validated);

                // If internal, deduct cash (credit)
                if ($validated['source'] === 'internal') {
                    $cash = Cash::findOrFail($validated['cash_id']);
                    $cash->adjustAmount((float)$validated['pph_amount'], 'credit');
                }

                return $withholdingTax;
            });

            return $this->responseSuccess($data, 'Withholding Tax created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error creating Withholding Tax: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Withholding Tax creation failed', 500);
        }
    }

    /**
     * Display the specified withholding tax.
     */
    public function show(string $id)
    {
        try {
            $withholdingTax = WithholdingTax::with(['cash', 'unitTransaction', 'bbnBill', 'doInvoice'])->findOrFail($id);
            return $this->responseSuccess($withholdingTax, 'Withholding Tax retrieved successfully');
        } catch (ModelNotFoundException $err) {
            return $this->responseError(null, 'Withholding Tax not found', 404);
        } catch (Exception $err) {
            Log::error('Error showing Withholding Tax: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve Withholding Tax details', 500);
        }
    }

    /**
     * Update the specified withholding tax in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $withholdingTax = WithholdingTax::findOrFail($id);

            $validated = $request->validate([
                'source' => 'sometimes|required|in:internal,external',
                'cash_id' => 'sometimes|required|exists:cashes,id',
                'unit_transaction_id' => 'nullable|exists:unit_transactions,id',
                'bbn_bill_id' => 'nullable|exists:bbn_bills,id',
                'do_invoice_id' => 'nullable|exists:do_invoices,id',
                'withholding_number' => 'sometimes|required|string|max:100|unique:withholding_taxes,withholding_number,' . $id,
                'withholding_age' => 'sometimes|required|integer',
                'pph_amount' => 'sometimes|required|numeric|min:0',
                'pph_description' => 'nullable|string|max:255',
                'payment_amount' => 'nullable|numeric|min:0',
                'payment_date' => 'sometimes|required|date',
            ]);

            $data = DB::transaction(function () use ($validated, $withholdingTax) {
                // Keep track of old state to handle balance adjustment reversals
                $oldSource = $withholdingTax->source;
                $oldCashId = $withholdingTax->cash_id;
                $oldPphAmount = $withholdingTax->pph_amount;

                $withholdingTax->update($validated);
                $withholdingTax->refresh();

                // Reverse the old cash deduction if it was internal
                if ($oldSource === 'internal') {
                    $oldCash = Cash::findOrFail($oldCashId);
                    $oldCash->adjustAmount((float)$oldPphAmount, 'debet'); // Add back the amount (debet)
                }

                // Apply the new cash deduction if it is internal
                if ($withholdingTax->source === 'internal') {
                    $newCash = Cash::findOrFail($withholdingTax->cash_id);
                    $newCash->adjustAmount((float)$withholdingTax->pph_amount, 'credit'); // Deduct the new amount (credit)
                }

                return $withholdingTax;
            });

            return $this->responseSuccess($data, 'Withholding Tax updated successfully');
        } catch (ModelNotFoundException $err) {
            return $this->responseError(null, 'Withholding Tax not found', 404);
        } catch (Exception $err) {
            Log::error('Error updating Withholding Tax: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Withholding Tax update failed', 500);
        }
    }

    /**
     * Remove the specified withholding tax from storage.
     */
    public function destroy(string $id)
    {
        try {
            $withholdingTax = WithholdingTax::findOrFail($id);

            DB::transaction(function () use ($withholdingTax) {
                // Reverse the cash deduction if it was internal before deleting
                if ($withholdingTax->source === 'internal') {
                    $cash = Cash::findOrFail($withholdingTax->cash_id);
                    $cash->adjustAmount((float)$withholdingTax->pph_amount, 'debet'); // Add back the amount (debet)
                }

                $withholdingTax->delete();
            });

            return $this->responseSuccess(null, 'Withholding Tax deleted successfully');
        } catch (ModelNotFoundException $err) {
            return $this->responseError(null, 'Withholding Tax not found', 404);
        } catch (Exception $err) {
            Log::error('Error deleting Withholding Tax: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Withholding Tax deletion failed', 500);
        }
    }
}
