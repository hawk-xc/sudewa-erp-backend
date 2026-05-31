<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Cash;
use App\Models\CashFlow;
use App\Traits\FileTrait;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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

        $this->cashFlowTable = ['id', 'uuid', 'company_id', 'code', 'account_id', 'cash_id', 'date', 'note', 'debet', 'credit', 'transaction_category','payment_proof', 'created_at'];
    }

    public function index(Request $request)
    {
        $query = CashFlow::query();

        $query->with(['account:id,uuid,code,name', 'cash:id,uuid,code,description', 'company:id,uuid,name', 'financeBilling:id,uuid,cash_flow_id,unit_transaction_billing_id,last_payment_at,is_valid']);

        try {
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%$search%")
                        ->orWhere('note', 'like', "%$search%")
                        ->orWhereHas('account', function ($qAccount) use ($search) {
                            $qAccount->where('code', 'like', "%$search%")
                                ->orWhere('name', 'like', "%$search%");
                        });
                });
            }

            if ($request->filled('transaction_category') && in_array($request->transaction_category, ['general', 'operational', 'director_receivable', 'shareholder_receivable', 'receivable', 'inventory'])) {
                $query->where('transaction_category', $request->transaction_category);
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
            Log::error('Error while retrieving Cash Flow data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Cash Flow list retrieved Failed', 500);
        }
    }

    public function show(string $id)
    {
        try {
            $cashFlow = CashFlow::with(['account:id,uuid,code,name', 'cash:id,uuid,code,description', 'company:id,uuid,name', 'financeBilling', 'financeBilling.financeBillingItems'])->find($id);

            if (! $cashFlow) {
                return $this->responseError(null, 'Cash Flow not found', 404);
            }

            return $this->responseSuccess($cashFlow, 'Cash Flow retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while retrieving Cash Flow data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Cash Flow retrieved Failed', 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'company_id' => 'required|integer|exists:companies,id',
                'account_id' => 'required|integer|exists:accounts,id',
                'cash_id' => 'nullable|integer|exists:cashes,id',
                'date' => 'required|date',
                'note' => 'nullable|string',
                'debet' => 'nullable|numeric|min:0|prohibits:credit',
                'credit' => 'nullable|numeric|min:0|prohibits:debet',
                'transaction_category' => 'nullable|string|in:general,operational,director_receivable,shareholder_receivable,receivable,inventory',
                'payment_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            ]);

            $companyId = (int) $validated['company_id'];

            // Check if account_id belongs to company_id
            $account = Account::with('accountGroup')->find($validated['account_id']);
            if (!$account || !$account->accountGroup || (int) $account->accountGroup->company_id !== $companyId) {
                throw ValidationException::withMessages([
                    'account_id' => ['The selected account_id does not belong to the selected company.'],
                ]);
            }

            // Check if cash_id belongs to company_id
            if (!empty($validated['cash_id'])) {
                $cash = Cash::find($validated['cash_id']);
                if (!$cash || (int) $cash->company_id !== $companyId) {
                    throw ValidationException::withMessages([
                        'cash_id' => ['The selected cash_id does not belong to the selected company.'],
                    ]);
                }
            }

            if ($request->hasFile('payment_proof')) {
                $validated['payment_proof'] = $this->storeFile(
                    $request->file('payment_proof'),
                    'cash_flow_proof'
                );
            }
            $cashFlow = DB::transaction(function () use ($validated) {
                return CashFlow::create($validated);
            });

            return $this->responseSuccess($cashFlow, 'Cash Flow created successfully', 201);
        } catch (ValidationException $err) {
            return $this->responseError($err->errors(), 'Validation failed', 422);
        } catch (Exception $err) {
            Log::error('Error while creating Cash Flow : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Error while creating Cash Flow', 500);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $validated = $request->validate([
                'company_id' => 'sometimes|integer|exists:companies,id',
                'account_id' => 'sometimes|integer|exists:accounts,id',
                'cash_id' => 'sometimes|nullable|integer|exists:cashes,id',
                'date' => 'sometimes|date',
                'note' => 'nullable|string',
                'debet' => 'sometimes|numeric|min:0|prohibits:credit',
                'credit' => 'sometimes|numeric|min:0|prohibits:debet',
                'transaction_category' => 'sometimes|string|in:general,operational,director_receivable,shareholder_receivable,receivable,inventory',
                'payment_proof' => 'sometimes|nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            ]);

            $cashFlow = CashFlow::findOrFail($id);

            // Determine active company_id
            $companyId = isset($validated['company_id']) ? (int) $validated['company_id'] : (int) $cashFlow->company_id;

            // Determine active account_id
            $accountId = isset($validated['account_id']) ? (int) $validated['account_id'] : (int) $cashFlow->account_id;

            // Determine active cash_id
            $cashId = $cashFlow->cash_id;
            if ($request->has('cash_id')) {
                // If present in request, update it. Note: could be null
                $cashId = $validated['cash_id'] ?? null;
            }

            // Check if account_id belongs to company_id
            $account = Account::with('accountGroup')->find($accountId);
            if (!$account || !$account->accountGroup || (int) $account->accountGroup->company_id !== $companyId) {
                throw ValidationException::withMessages([
                    'account_id' => ['The selected account_id does not belong to the selected company.'],
                ]);
            }

            // Check if cash_id belongs to company_id
            if (!is_null($cashId)) {
                $cash = Cash::find($cashId);
                if (!$cash || (int) $cash->company_id !== $companyId) {
                    throw ValidationException::withMessages([
                        'cash_id' => ['The selected cash_id does not belong to the selected company.'],
                    ]);
                }
            }

            $data = array_filter($request->only(['company_id', 'account_id', 'cash_id', 'date', 'note', 'debet', 'credit', 'transaction_category']), fn ($value) => ! is_null($value) && $value !== '');

            if ($request->hasFile('payment_proof')) {
                if ($cashFlow->payment_proof) {
                    $this->destroyFile('cash_flow_proof/' . $cashFlow->payment_proof);
                }

                $data['payment_proof'] = $this->storeFile(
                    $request->file('payment_proof'),
                    'cash_flow_proof'
                );
            }

            if (empty($data)) {
                return $this->responseError(null, 'No data provided to update', 422);
            }

            $cashFlow = DB::transaction(function () use ($cashFlow, $data) {
                $cashFlow->update($data);

                return $cashFlow->fresh();
            });

            return $this->responseSuccess($cashFlow, 'Cash Flow updated successfully', 200);
        } catch (ValidationException $err) {
            return $this->responseError($err->errors(), 'Validation failed', 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while updating Cash Flow : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Error while updating Cash Flow', 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $cashFlow = CashFlow::findOrFail($id);
            
            if ($cashFlow->payment_proof) {
                $this->destroyFile('cash_flow_proof/' . $cashFlow->payment_proof);
            }

            $cashFlow->delete();

            return $this->responseSuccess([], 'Cash Flow deleted successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while deleting Cash Flow : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Cash Flow deleted Failed', 500);
        }
    }
}
