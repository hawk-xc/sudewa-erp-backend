<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\UnitTransactionBilling;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UnitTransactionBillingController extends Controller
{
    use ResponseTrait;

    protected $unitTransactionBillingTable;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only('update');
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);

        $this->unitTransactionBillingTable = [
            'id',
            'uuid',
            'unit_transaction_id',
            'bca_payment_amount',
            'bca_payment_usd_amount',
            'cash_payment_amount',
            'payment_at',
            'is_paid',
            'created_at',
        ];
    }

    public function index(Request $request)
    {
        try {
            $query = UnitTransactionBilling::query();

            $query->select($this->unitTransactionBillingTable)
                ->with(['unitTransaction:id,code']);

            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('uuid', 'like', "%$search%")
                        ->orWhere('bca_payment_amount', 'like', "%$search%")
                        ->orWhere('cash_payment_amount', 'like', "%$search%");
                });
            }

            foreach ($this->unitTransactionBillingTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $allowedSort = $this->unitTransactionBillingTable;

            $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->per_page ?? 10;

            $data = $query->paginate($perPage);

            return $this->responseSuccess($data, 'Unit Transaction Billing list retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error While retrieved Unit Transaction Billing data : '.$err->getMessage());

            return $this->responseError(null, 'Unit Transaction Billing list retrieved Failed', 500);
        }
    }

    public function show(string $id)
    {
        try {
            $data = UnitTransactionBilling::with(['unitTransaction:id,code'])
                ->select($this->unitTransactionBillingTable)
                ->findOrFail($id);

            return $this->responseSuccess($data, 'Unit Transaction Billing retrieved successfully', 200);
        } catch (Exception $err) {
            return $this->responseError($err->getMessage(), 'Unit Transaction Billing not found', 404);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'company_id' => 'required|integer|exists:companies,id',
                'unit_transaction_id' => 'required|integer|exists:unit_transactions,id|unique:unit_transaction_billings,unit_transaction_id',
                'bca_payment_amount' => 'nullable|numeric|min:0',
                'bca_payment_usd_amount' => 'nullable|numeric|min:0',
                'cash_payment_amount' => 'nullable|numeric|min:0',
                'payment_at' => 'nullable|date',
                'is_paid' => 'required|boolean',
            ]);

            $data = DB::transaction(function () use ($validated) {
                return UnitTransactionBilling::create($validated);
            });

            return $this->responseSuccess($data->fresh(), 'Unit Transaction Billing created successfully', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->responseError($e->errors(), 'Validation failed', 422);
        } catch (Exception $err) {
            Log::error('Error While storing Unit Transaction Billing data : '.$err->getMessage());

            return $this->responseError(null, 'Unit Transaction Billing creation failed', 500);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $billing = UnitTransactionBilling::findOrFail((int) $id);

            $validated = $request->validate([
                'unit_transaction_id' => 'sometimes|integer|exists:unit_transactions,id|unique:unit_transaction_billings,unit_transaction_id,'.$id,
                'bca_payment_amount' => 'nullable|numeric|min:0',
                'bca_payment_usd_amount' => 'nullable|numeric|min:0',
                'cash_payment_amount' => 'nullable|numeric|min:0',
                'payment_at' => 'nullable|date',
                'is_paid' => 'sometimes|boolean',
            ]);

            DB::transaction(function () use ($billing, $validated) {
                $billing->update($validated);
            });

            return $this->responseSuccess($billing->fresh(), 'Unit Transaction Billing updated successfully', 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->responseError($e->errors(), 'Validation failed', 422);
        } catch (Exception $err) {
            Log::error('Error While updating Unit Transaction Billing data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Unit Transaction Billing update failed', 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $billing = UnitTransactionBilling::findOrFail($id);

            DB::transaction(function () use ($billing) {
                $billing->delete();
            });

            return $this->responseSuccess([], 'Unit Transaction Billing successfully Deleted', 200);
        } catch (Exception $err) {
            Log::error('Error While deleting Unit Transaction Billing data : '.$err->getMessage());

            return $this->responseError([], 'Unit Transaction Billing Not Found or Failed Deleted', 500);
        }
    }
}
