<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\DOExpedition;
use App\Models\UJDriverBillingPayment;
use App\Rules\RightCashRule;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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
                new RightCashRule(4),
            ],
            'amount' => 'required|numeric|min:1',
        ]);

        try {
            $doExpedition = DOExpedition::with([
                'order_list.customer',
                'order_list.expeditions',
            ])->findOrFail($validated['do_expedition_id']);

            $requiredAmount = $doExpedition->order_list?->uj_driver ?? 0;

            if ((float) $validated['amount'] !== (float) $requiredAmount) {
                throw ValidationException::withMessages([
                    'amount' => ["The amount must be exactly equal to the driver's UJ: {$requiredAmount}."],
                ]);
            }

            $payment = DB::transaction(function () use ($validated) {
                return UJDriverBillingPayment::create($validated);
            });

            return $this->responseSuccess($payment, 'UJ Driver Billing Payment created successfully', 201);
        } catch (ValidationException $err) {
            return $this->responseError($err->errors(), 'Validation failed', 422);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while trying create UJ Driver Billing Payment Data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'UJ Driver Billing Payment creation failed', 500);
        }
    }
}
