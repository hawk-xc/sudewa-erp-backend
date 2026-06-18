<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Cash;
use App\Models\UnitTransaction;
use App\Models\WithholdingTax;
use App\Rules\RightCashRule;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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
            'company_id',
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
        $query = WithholdingTax::with([
            'company:id,name,slug',
            'cash:id,uuid,company_id,code,cash_name,type',
            'unitTransaction:id,uuid,warehouse_id,code,type',
            'bbnBill',
            'doInvoice:id,uuid,code,customer_id,date'
        ]);

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

            if ($request->filled('start_date')) {
                $query->whereDate('payment_date', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $query->whereDate('payment_date', '<=', $request->end_date);
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
        $filledRelations = collect([
            $request->unit_transaction_id,
            $request->bbn_bill_id,
            $request->do_invoice_id,
        ])->filter();

        if ($filledRelations->count() !== 1) {
            throw ValidationException::withMessages([
                'unit_transaction_id' => ['Exactly one of unit_transaction_id, bbn_bill_id, or do_invoice_id must be provided.'],
                'bbn_bill_id' => ['Exactly one of unit_transaction_id, bbn_bill_id, or do_invoice_id must be provided.'],
                'do_invoice_id' => ['Exactly one of unit_transaction_id, bbn_bill_id, or do_invoice_id must be provided.'],
            ]);
        }

        $validated = $request->validate([
            'source' => 'required|in:internal,external',
            'cash_id' => [
                'required',
                'exists:cashes,id',
                new RightCashRule(function () use ($request) {
                    if ($request->filled('unit_transaction_id')) {
                        $unitTransaction = UnitTransaction::find((int) $request->unit_transaction_id);
                        if ($unitTransaction && $unitTransaction->warehouse && $unitTransaction->warehouse->company) {
                            $request['company_id'] = $unitTransaction->warehouse->company->id;
                            return [1, 2, 5];
                        }
                        return null;
                    }
                    if ($request->filled('bbn_bill_id')) {
                        $request['company_id'] = 3;
                        return 3;
                    }
                    if ($request->filled('do_invoice_id')) {
                        $request['company_id'] = 4;

                        return 4;
                    }
                    return null;
                }),
            ],
            'unit_transaction_id' => 'nullable|exists:unit_transactions,id|unique:withholding_taxes,unit_transaction_id',
            'bbn_bill_id' => 'nullable|exists:bbn_bills,id|unique:withholding_taxes,bbn_bill_id',
            'do_invoice_id' => 'nullable|exists:do_invoices,id|unique:withholding_taxes,do_invoice_id',
            'withholding_number' => 'required|string|max:100|unique:withholding_taxes,withholding_number',
            'withholding_age' => 'required|integer',
            'pph_amount' => 'required|numeric|min:0',
            'pph_description' => 'nullable|string|max:255',
            'payment_amount' => 'nullable|numeric|min:0',
            'payment_date' => 'required|date',
        ]);

        try {
            $data = DB::transaction(function () use ($validated, $request) {
                $validated['company_id'] = $request->company_id;
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
                'cash_id' => [
                    'sometimes',
                    'required',
                    'exists:cashes,id',
                    new RightCashRule(function () use ($request, $withholdingTax) {
                        $unitTransactionId = $request->has('unit_transaction_id') ? $request->unit_transaction_id : $withholdingTax->unit_transaction_id;
                        $bbnBillId = $request->has('bbn_bill_id') ? $request->bbn_bill_id : $withholdingTax->bbn_bill_id;
                        $doInvoiceId = $request->has('do_invoice_id') ? $request->do_invoice_id : $withholdingTax->do_invoice_id;

                        if (!empty($unitTransactionId)) {
                            return [1, 2, 3];
                        }
                        if (!empty($bbnBillId)) {
                            return 3;
                        }
                        if (!empty($doInvoiceId)) {
                            return 4;
                        }
                        return null;
                    }),
                ],
                'unit_transaction_id' => 'nullable|exists:unit_transactions,id|unique:withholding_taxes,unit_transaction_id,' . $id,
                'bbn_bill_id' => 'nullable|exists:bbn_bills,id|unique:withholding_taxes,bbn_bill_id,' . $id,
                'do_invoice_id' => 'nullable|exists:do_invoices,id|unique:withholding_taxes,do_invoice_id,' . $id,
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
                    $newCash->adjustAmount((float)$withholdingTax->payment_amount, 'credit'); // Deduct the new amount (credit)
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
                    $cash->adjustAmount((float)$withholdingTax->payment_amount, 'debet'); // Add back the amount (debet)
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
