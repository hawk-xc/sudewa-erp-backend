<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\UnitTransaction;
use App\Models\UnitTransactionBilling;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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
            'bca_payment_liability',
            'bca_payment_usd_liability',
            'cash_payment_liability',
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
                'is_paid' => 'sometimes|boolean',
            ]);

            $unitTransaction = UnitTransaction::findOrFail($request->unit_transaction_id);
            $unitTransactionBrutoTotal = $unitTransaction->getBrutoAmount();

            if ($unitTransaction->warehouse->company_id !== $request->company_id) {
                throw ValidationException::withMessages([
                    'company_id' => 'Company don\'t have this unit transaction!.',
                ]);
            }

            $bcaPayment = $request->bca_payment_amount ?? 0;
            $cashPayment = $request->cash_payment_amount ?? 0;

            $totalIdrPayment = $bcaPayment + $cashPayment;

            if ($request->bca_payment_amount > $cashPayment) {
                throw ValidationException::withMessages([
                    'bca_payment_amount' => 'Total IDR Bca payment (BCA) cannot exceed the transaction bruto amount.',
                ]);
            }
            if ($request->cash_payment_amount > $unitTransactionBrutoTotal) {
                throw ValidationException::withMessages([
                    'cash_payment_amount' => 'Total IDR Cash payment (Cash) cannot exceed the transaction bruto amount.',
                ]);
            }

            $bca_payment_liability = 0;
            $cash_payment_liability = 0;

            if ($totalIdrPayment < $unitTransactionBrutoTotal) {
                $remaining = $unitTransactionBrutoTotal - $totalIdrPayment;

                if ($cashPayment < $remaining) {
                    $cash_payment_liability = $remaining;
                } else {
                    $bca_payment_liability = $remaining;
                }
            }

            $validated['bca_payment_liability'] = $bca_payment_liability;
            $validated['cash_payment_liability'] = $cash_payment_liability;
            $validated['bca_payment_usd_liability'] = 0;

            if ($bca_payment_liability == 0 && $cash_payment_liability == 0 && ! $request->filled('is_paid')) {
                $validated['is_paid'] = true;

                // change inbound_purcase_order state
                $unitTransaction->update(['stock_state' => 'inbound_purcase_order']);
            } else {
                $validated['is_paid'] = $request->filled('is_paid') ?? false;
            }

            $data = DB::transaction(function () use ($validated) {
                return UnitTransactionBilling::create($validated);
            });

            return $this->responseSuccess($data->fresh(), 'Unit Transaction Billing created successfully', 201);
        } catch (ValidationException $err) {
            return $this->responseError($err->errors(), 'Validation failed', 422);
        } catch (Exception $err) {
            Log::error('Error While storing Unit Transaction Billing data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Unit Transaction Billing creation failed', 500);
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
