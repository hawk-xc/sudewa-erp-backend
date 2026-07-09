<?php
 
namespace App\Http\Controllers\Finance;
 
use App\Http\Controllers\Controller;
use App\Models\Cash;
use App\Models\CashFlow;
use App\Repositories\AuthRepository;
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
 
    protected AuthRepository $authRepository;
 
    protected array $cashFlowTable;
 
    public function __construct(AuthRepository $ar)
    {
        $this->middleware(['permission:cashflow:list'])->only(['index', 'show']);
        $this->middleware(['permission:cashflow:create'])->only(['store']);
        $this->middleware(['permission:cashflow:edit'])->only(['update']);
        $this->middleware(['permission:cashflow:delete'])->only(['destroy']);
 
        $this->authRepository = $ar;
 
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
            'is_valid',
            'created_at'
        ];
    }
 
    public function index(Request $request)
    {
        $query = CashFlow::query();
 
        $query->with([
            'company:id,uuid,name',
            'financeBillings:id,uuid,cash_flow_id,unit_transaction_billing_id,last_payment_at'
        ]);
 
        try {
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%$search%")
                        ->orWhere('note', 'like', "%$search%");
                });
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
                'financeBillings',
                'financeBillings.cash:id,uuid,company_id,code,cash_name',
            ])->find($id);
 
            if (! $cashFlow) {
                return $this->responseError(null, 'Cash Flow not found', 404);
            }
 
            return $this->responseSuccess($cashFlow, 'Cash Flow data retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while retrieving Cash Flow data : ' . $err->getMessage());
 
            return $this->responseError($err->getMessage(), 'Cash Flow data retrieved Failed', 500);
        }
    }
 
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'company_id' => 'required|integer|exists:companies,id',
                'date' => 'required|date',
                'note' => 'nullable|string',
                'debet' => 'required_without:credit|numeric|min:0',
                'credit' => 'required_without:debet|numeric|min:0',
                'transaction_category' => 'required|string',
                'payment_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
                'is_paid' => 'sometimes|boolean',
            ]);
 
            if ($request->hasFile('payment_proof')) {
                $validated['payment_proof'] = $this->storeFile(
                    $request->file('payment_proof'),
                    'cash_flow_proof'
                );
            }
 
            $cashFlow = DB::transaction(function () use ($validated) {
                $cashFlow = CashFlow::create($validated);
                return $cashFlow;
            });
 
            return $this->responseSuccess($cashFlow, 'Cash Flow created successfully', 201);
        } catch (ValidationException $err) {
            return $this->responseError($err->errors(), 'Validation failed', 422);
        } catch (Exception $err) {
            Log::error('Error while creating Cash Flow : ' . $err->getMessage());
 
            return $this->responseError($err->getMessage(), 'Cash Flow creation failed', 500);
        }
    }
 
    public function update(Request $request, string $id)
    {
        try {
            $cashFlow = CashFlow::findOrFail($id);
 
            $validated = $request->validate([
                'date' => 'sometimes|date',
                'note' => 'sometimes|nullable|string',
                'debet' => 'sometimes|numeric|min:0',
                'credit' => 'sometimes|numeric|min:0',
                'payment_proof' => 'sometimes|nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
                'is_paid' => 'sometimes|boolean',
            ]);
 
            if ($request->hasFile('payment_proof')) {
                if ($cashFlow->payment_proof) {
                    $this->destroyFile('cash_flow_proof/' . $cashFlow->payment_proof);
                }
 
                $validated['payment_proof'] = $this->storeFile(
                    $request->file('payment_proof'),
                    'cash_flow_proof'
                );
            }
 
            $data = $validated;
 
            $cashFlow = DB::transaction(function () use ($cashFlow, $data, $request) {
                if ($request->filled('is_paid')) {
                    $isPaidRequest = $request->is_paid == "true";
 
                    if ($isPaidRequest && !$cashFlow->is_paid) {
                        // Transition from unpaid to paid: adjust cash
                        $type = $cashFlow->debet > 0 ? 'sales' : 'purchase';
 
                        $financeBillings = $cashFlow->financeBillings;
                        if ($financeBillings->isNotEmpty()) {
                            $companyId = $cashFlow->company_id;
                            foreach ($financeBillings as $item) {
                                $cash = $item->cash;
                                $amount = (float) $item->amount;
 
                                if ($cash && $cash->company_id === $companyId && $amount > 0) {
                                    $cash->adjustAmount($amount, $type);
                                }
                            }
                        }
                    } elseif (!$isPaidRequest && $cashFlow->is_paid) {
                        // Transition from paid to unpaid: reverse cash adjustment
                        $type = $cashFlow->debet > 0 ? 'sales' : 'purchase';
                        $reverseType = $type === 'sales' ? 'purchase' : 'sales';
 
                        $financeBillings = $cashFlow->financeBillings;
                        if ($financeBillings->isNotEmpty()) {
                            $companyId = $cashFlow->company_id;
                            foreach ($financeBillings as $item) {
                                $cash = $item->cash;
                                $amount = (float) $item->amount;
 
                                if ($cash && $cash->company_id === $companyId && $amount > 0) {
                                    $cash->adjustAmount($amount, $reverseType);
                                }
                            }
                        }
                    }
                }
 
                $data['is_paid'] = (bool) $request->is_paid;
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
                if ($cashFlow->is_paid) {
                    $type = $cashFlow->debet > 0 ? 'sales' : 'purchase';
                    $reverseType = $type === 'sales' ? 'purchase' : 'sales';
 
                    $financeBillings = $cashFlow->financeBillings;
                    if ($financeBillings->isNotEmpty()) {
                        $companyId = $cashFlow->company_id;
                        foreach ($financeBillings as $item) {
                            $cash = $item->cash;
                            $amount = (float) $item->amount;
 
                            if ($cash && $cash->company_id === $companyId && $amount > 0) {
                                $cash->adjustAmount($amount, $reverseType);
                            }
                        }
                    }
                }
                $cashFlow->delete();
            });
 
            return $this->responseSuccess([], 'Cash Flow deleted successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while deleting Cash Flow : ' . $err->getMessage());
 
            return $this->responseError($err->getMessage(), 'Error while deleting Cash Flow', 500);
        }
    }
}
