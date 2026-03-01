<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\TransactionFlow;
use App\Repositories\AuthRepository;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class TransactionFlowController extends Controller
{
    use ResponseTrait;

    protected AuthRepository $authRepository;
    protected $transactionFlowTable;

    public function __construct(AuthRepository $ar)
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only('update');
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);

        $this->authRepository = $ar;

        $this->transactionFlowTable = [
            'id',
            'uuid',
            'company_id',
            'unit_transaction_id',
            'transaction_date',
            'description',
            'bank_usd_debit',
            'bank_usd_credit',
            'bank_idr_debit',
            'bank_idr_credit',
            'cash_idr_debit',
            'cash_idr_credit',
            'created_at'
        ];
    }

    public function index(Request $request)
    {
        try {
            $query = TransactionFlow::select($this->transactionFlowTable)
                ->with('unitTransaction');

            // optional filter
            if ($request->company_id) {
                $query->where('company_id', $request->company_id);
            }

            if ($request->start_date && $request->end_date) {
                $query->whereBetween('transaction_date', [
                    $request->start_date,
                    $request->end_date
                ]);
            }

            $data = $query->latest()->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'Transaction Flows retrieved successfully', 200);

        } catch (Exception $e) {
            Log::error('Error fetching Transaction Flows: '.$e->getMessage());
            return $this->responseError(null, 'Internal Server Error', 500);
        }
    }

    public function show(string $id)
    {
        try {
            $transactionFlow = TransactionFlow::select($this->transactionFlowTable)
                ->with('unitTransaction')
                ->findOrFail($id);

            return $this->responseSuccess($transactionFlow, 'Transaction Flow retrieved successfully', 200);

        } catch (Exception $e) {
            Log::error('Error fetching Transaction Flow: '.$e->getMessage());
            return $this->responseError(null, 'Transaction Flow not found', 404);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'unit_transaction_id' => 'nullable|exists:unit_transactions,id',
            'transaction_date' => 'required|date',
            'description' => 'nullable|string',
            'bank_usd_debit' => 'nullable|numeric',
            'bank_usd_credit' => 'nullable|numeric',
            'bank_idr_debit' => 'nullable|numeric',
            'bank_idr_credit' => 'nullable|numeric',
            'cash_idr_debit' => 'nullable|numeric',
            'cash_idr_credit' => 'nullable|numeric',
        ]);

        try {
            $transactionFlow = DB::transaction(function () use ($validated) {
                return TransactionFlow::create($validated);
            });

            return $this->responseSuccess($transactionFlow, 'Transaction Flow created successfully', 201);

        } catch (Exception $e) {
            Log::error('Error creating Transaction Flow: '.$e->getMessage());
            return $this->responseError(null, 'Internal Server Error', 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $transactionFlow = TransactionFlow::findOrFail($id);

        $validated = $request->validate([
            'company_id' => 'sometimes|exists:companies,id',
            'unit_transaction_id' => 'sometimes|exists:unit_transactions,id',
            'transaction_date' => 'sometimes|date',
            'description' => 'nullable|string',
            'bank_usd_debit' => 'nullable|numeric',
            'bank_usd_credit' => 'nullable|numeric',
            'bank_idr_debit' => 'nullable|numeric',
            'bank_idr_credit' => 'nullable|numeric',
            'cash_idr_debit' => 'nullable|numeric',
            'cash_idr_credit' => 'nullable|numeric',
        ]);

        try {
            DB::transaction(function () use ($validated, $transactionFlow) {
                $transactionFlow->update($validated);
            });

            return $this->responseSuccess($transactionFlow->fresh(), 'Transaction Flow updated successfully', 200);

        } catch (Exception $e) {
            Log::error('Error updating Transaction Flow: '.$e->getMessage());
            return $this->responseError(null, 'Internal Server Error', 500);
        }
    }

    /**
     * DELETE /transaction-flows/{id}
     */
    public function destroy(string $id)
    {
        try {
            $transactionFlow = TransactionFlow::findOrFail($id);

            DB::transaction(function () use ($transactionFlow) {
                $transactionFlow->delete();
            });

            return $this->responseSuccess(null, 'Transaction Flow deleted successfully', 200);

        } catch (Exception $e) {
            Log::error('Error deleting Transaction Flow: '.$e->getMessage());
            return $this->responseError(null, 'Internal Server Error', 500);
        }
    }
}