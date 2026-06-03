<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\DOInvoice;
use App\Models\FinanceInvoiceBillingPayment;
use App\Rules\RightCashRule;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class FinanceInvoiceBillingPaymentController extends Controller
{
    use ResponseTrait;

    public function __construct()
    {
        $this->middleware(['permission:finance:create'])->only('store');
        $this->middleware(['permission:finance:edit'])->only('update');
    }

    /**
     * Store a new Finance Invoice Billing Payment.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'do_invoice_id' => 'required|exists:do_invoices,id',
            'cash_id' => [
                'required',
                'exists:cashes,id',
                new RightCashRule(4),
            ],
            'amount' => 'required|numeric|min:1',
        ]);

        try {
            $doInvoice = DOInvoice::with('order_list')->findOrFail($validated['do_invoice_id']);

            $totalPaidBefore = FinanceInvoiceBillingPayment::where('do_invoice_id', $validated['do_invoice_id'])->sum('amount');
            $newTotalPaid = $totalPaidBefore + $validated['amount'];
            $billInvoice = $doInvoice->order_list?->bill_invoice ?? 0;

            if ($newTotalPaid > $billInvoice) {
                throw ValidationException::withMessages([
                    'amount' => ["Total payment exceeds the bill invoice amount: {$billInvoice}. Already paid: {$totalPaidBefore}."],
                ]);
            }

            $payment = DB::transaction(function () use ($validated) {
                return FinanceInvoiceBillingPayment::create($validated);
            });

            return $this->responseSuccess($payment, 'Finance Invoice Billing Payment created successfully', 201);
        } catch (ValidationException $err) {
            return $this->responseError($err->errors(), 'Validation failed', 422);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while trying create Finance Invoice Billing Payment Data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Finance Invoice Billing Payment creation failed', 500);
        }
    }

    /**
     * Update a Finance Invoice Billing Payment.
     */
    public function update(Request $request, string $id)
    {
        try {
            $payment = FinanceInvoiceBillingPayment::findOrFail($id);

            $request->validate([
                'do_invoice_id' => 'sometimes|required|exists:do_invoices,id',
                'cash_id' => [
                    'sometimes',
                    'required',
                    'exists:cashes,id',
                    new RightCashRule(fn () => DOInvoice::find($request->do_invoice_id ?? $payment->do_invoice_id)?->customer?->company_id),
                ],
                'amount' => 'sometimes|required|numeric|min:1',
            ]);

            $data = array_filter($request->only(['do_invoice_id', 'cash_id', 'amount']), fn ($value) => $value !== '' && $value !== null);

            $doInvoiceId = $data['do_invoice_id'] ?? $payment->do_invoice_id;
            $amount = $data['amount'] ?? $payment->amount;

            $doInvoice = DOInvoice::with('order_list')->findOrFail($doInvoiceId);

            $totalPaidBefore = FinanceInvoiceBillingPayment::where('do_invoice_id', $doInvoiceId)
                ->where('id', '!=', $id)
                ->sum('amount');
            $newTotalPaid = $totalPaidBefore + $amount;
            $billInvoice = $doInvoice->order_list?->bill_invoice ?? 0;

            if ($newTotalPaid > $billInvoice) {
                throw ValidationException::withMessages([
                    'amount' => ["Total payment exceeds the bill invoice amount: {$billInvoice}. Already paid: {$totalPaidBefore}."],
                ]);
            }

            $payment = DB::transaction(function () use ($payment, $data) {
                $payment->update($data);

                return $payment->fresh();
            });

            return $this->responseSuccess($payment, 'Finance Invoice Billing Payment Update Successfully', 200);
        } catch (ValidationException $err) {
            return $this->responseError($err->errors(), 'Validation failed', 422);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while trying update Finance Invoice Billing Payment data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Error while trying update Finance Invoice Billing Payment data', 500);
        }
    }
}
