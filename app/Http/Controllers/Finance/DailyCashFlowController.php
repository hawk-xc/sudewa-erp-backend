<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\CashFlow;
use App\Traits\FileTrait;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DailyCashFlowController extends Controller
{
    use FileTrait, ResponseTrait;

    protected array $cashFlowTable;

    public function __construct()
    {
        $this->middleware(['permission:finance:list'])->only(['index', 'show']);
        $this->middleware(['permission:finance:create'])->only('store');
        $this->middleware(['permission:finance:edit'])->only('update');
        $this->middleware(['permission:finance:delete'])->only(['destroy']);

        $this->cashFlowTable = [
            'id',
            'uuid',
            'company_id',
            'code',
            'date',
            'note',
            'debet',
            'credit',
            'transaction_category',
            'payment_proof',
            'is_paid',
            'created_at'
        ];
    }

    public function index(Request $request)
    {
        $query = CashFlow::query();

        $query->with([
            'company:id,uuid,name',
            'financeBilling:id,uuid,cash_flow_id,unit_transaction_billing_id,last_payment_at,is_valid'
        ]);

        try {
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%$search%")
                        ->orWhere('note', 'like', "%$search%");
                });
            }

            if ($request->filled('transaction_category') && in_array($request->transaction_category, ['general', 'operational', 'director_receivable', 'shareholder_receivable', 'receivable', 'inventory'])) {
                $query->where('transaction_category', $request->transaction_category);
            }

            if ($request->filled('start_date') && $request->filled('end_date')) {
                $query->whereBetween('date', [$request->start_date, $request->end_date]);
            } elseif ($request->filled('start_date')) {
                $query->where('date', '>=', $request->start_date);
            } elseif ($request->filled('end_date')) {
                $query->where('date', '<=', $request->end_date);
            }

            foreach ($this->cashFlowTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $allowedSort = $this->cashFlowTable;
            $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->per_page ?? 10;
            $data = $query->paginate($perPage);

            return $this->responseSuccess($data, 'Cash Flow list retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while retrieving Cash Flow data : ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Cash Flow list retrieved Failed', 500);
        }
    }

    public function show(string $id)
    {
        try {
            $cashFlow = CashFlow::with([
                'company:id,uuid,name',
                'financeBilling',
                'financeBilling.financeBillingItems'
            ])->find($id);

            if (! $cashFlow) {
                return $this->responseError(null, 'Cash Flow not found', 404);
            }

            return $this->responseSuccess($cashFlow, 'Cash Flow retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while retrieving Cash Flow data : ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Cash Flow retrieved Failed', 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'company_id' => 'required|integer|exists:companies,id',
                'date' => 'required|date',
                'note' => 'nullable|string',
                'debet' => 'nullable|numeric|min:0|prohibits:credit',
                'credit' => 'nullable|numeric|min:0|prohibits:debet',
                'transaction_category' => 'nullable|string|in:general,operational,director_receivable,shareholder_receivable,receivable,inventory',
                'payment_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
                'is_paid' => 'nullable|in:true,false',
            ]);

            $cfData = collect($validated)->toArray();

            if ($request->hasFile('payment_proof')) {
                $cfData['payment_proof'] = $this->storeFile(
                    $request->file('payment_proof'),
                    'cash_flow_proof'
                );
            }

            $cashFlow = DB::transaction(function () use ($cfData) {
                return CashFlow::create($cfData);
            });

            return $this->responseSuccess($cashFlow, 'Cash Flow created successfully', 201);
        } catch (ValidationException $err) {
            return $this->responseError($err->errors(), 'Validation failed', 422);
        } catch (Exception $err) {
            Log::error('Error while creating Cash Flow : ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Error while creating Cash Flow', 500);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $cashFlow = CashFlow::findOrFail($id);

            $validated = $request->validate([
                'company_id' => 'sometimes|integer|exists:companies,id',
                'date' => 'sometimes|date',
                'note' => 'nullable|string',
                'debet' => 'sometimes|numeric|min:0|prohibits:credit',
                'credit' => 'sometimes|numeric|min:0|prohibits:debet',
                'transaction_category' => 'sometimes|string|in:general,operational,director_receivable,shareholder_receivable,receivable,inventory',
                'payment_proof' => 'sometimes|nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
                'is_paid' => 'sometimes|in:true,false',
            ]);

            $data = array_filter($request->only(['company_id', 'date', 'note', 'debet', 'credit', 'transaction_category', 'is_paid']), fn($value) => ! is_null($value) && $value !== '');

            if ($request->hasFile('payment_proof')) {
                if ($cashFlow->payment_proof) {
                    $this->destroyFile('cash_flow_proof/' . $cashFlow->payment_proof);
                }

                $data['payment_proof'] = $this->storeFile(
                    $request->file('payment_proof'),
                    'cash_flow_proof'
                );
            }

            // credit amount protector
            if ($cashFlow->unitTransactionBilling && $cashFlow->unitTransactionBilling->unitTransaction) {
                if ($cashFlow->unitTransactionBilling->unitTransaction->type === 'sales') {
                    if ($request->filled('debet')) {
                        return $this->responseError(null, 'Debet is not allowed for sales transaction', 422);
                    }
                } else {
                    if ($request->filled('credit')) {
                        return $this->responseError(null, 'Credit is not allowed for operational transaction', 422);
                    }
                }
            }

            $cashFlow = DB::transaction(function () use ($cashFlow, $data, $request) {
                if ($request->filled('is_paid') && $request->is_paid == "true" && !$cashFlow->is_paid) {
                    $type = $cashFlow->debet > 0 ? 'sales' : 'purchase';

                    $financeBilling = $cashFlow->financeBilling;
                    if ($financeBilling) {
                        $utBilling = $financeBilling->unitTransactionBilling;
                        if ($utBilling && $utBilling->unitTransaction) {
                            $companyId = $utBilling->unitTransaction->warehouse->company_id;
                            foreach ($financeBilling->financeBillingItems as $item) {
                                $cash = $item->cash;
                                $amount = (float) $item->amount;

                                if ($cash && $cash->company_id === $companyId && $amount > 0) {
                                    $cash->adjustAmount($amount, $type);
                                }
                            }
                        }
                    }
                }

                $cashFlow->update($data);
                return $cashFlow->fresh();
            });

            return $this->responseSuccess($cashFlow, 'Cash Flow updated successfully', 200);
        } catch (ValidationException $err) {
            return $this->responseError($err->errors(), 'Validation failed', 422);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while updating Cash Flow : ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Error while updating Cash Flow', 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $cashFlow = CashFlow::findOrFail($id);

            if (!empty($cashFlow->unitTransactionBilling()->first())) {
                throw ValidationException::withMessages([
                    'unit_transaction_billing_id' => ['Cash Flow is linked to a unit transaction billing, cannot be deleted'],
                ]);
            }

            if ($cashFlow->payment_proof) {
                $this->destroyFile('cash_flow_proof/' . $cashFlow->payment_proof);
            }

            DB::transaction(function () use ($cashFlow) {
                $cashFlow->delete();
            });

            return $this->responseSuccess([], 'Cash Flow deleted successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while deleting Cash Flow : ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Cash Flow deleted Failed', 500);
        }
    }
}
