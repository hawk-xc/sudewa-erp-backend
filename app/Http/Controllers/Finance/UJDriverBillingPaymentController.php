<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Rules\RightCashRule;
use App\Models\UJDriverBillingPayment;
use App\Models\Cash;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * @group Finance
 *
 * API for managing UJ driver billing payments.
 */
class UJDriverBillingPaymentController extends Controller
{
    use ResponseTrait;

    public function __construct()
    {
        $this->middleware(['permission:finance:create'])->only('store');
        $this->middleware(['permission:finance:edit'])->only('update');
    }

    /**
     * Store a new UJ driver billing payment.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'do_expedition_id' => 'required|exists:do_expeditions,id',
            'cash_id' => [
                'required',
                'exists:cashes,id',
                new RightCashRule(fn () => \App\Models\DOExpedition::find($request->do_expedition_id)?->order_list?->customer?->company_id),
            ],
            'amount' => 'required|numeric|min:1',
        ]);

        try {
            $payment = DB::transaction(function () use ($validated) {
                return UJDriverBillingPayment::create($validated);
            });

            return $this->responseSuccess($payment, 'UJ Driver Billing Payment created successfully', 201);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while trying create UJ Driver Billing Payment Data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'UJ Driver Billing Payment creation failed', 500);
        }
    }

    /**
     * Update a UJ driver billing payment.
     */
    public function update(Request $request, string $id)
    {
        try {
            $payment = UJDriverBillingPayment::findOrFail($id);

            $request->validate([
                'do_expedition_id' => 'sometimes|required|exists:do_expeditions,id',
                'cash_id' => [
                    'sometimes',
                    'required',
                    'exists:cashes,id',
                    new RightCashRule(fn () => \App\Models\DOExpedition::find($request->do_expedition_id ?? $payment->do_expedition_id)?->order_list?->customer?->company_id),
                ],
                'amount' => 'sometimes|required|numeric|min:1',
            ]);

            $data = array_filter($request->only(['do_expedition_id', 'cash_id', 'amount']), fn ($value) => $value !== '' && $value !== null);

            $payment = DB::transaction(function () use ($payment, $data) {
                $payment->update($data);

                return $payment->fresh();
            });

            return $this->responseSuccess($payment, 'UJ Driver Billing Payment Update Successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while trying update UJ Driver Billing Payment data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Error while trying update UJ Driver Billing Payment data', 500);
        }
    }
}
