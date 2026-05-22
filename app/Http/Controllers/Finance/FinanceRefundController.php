<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\FinanceRefund;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FinanceRefundController extends Controller
{
    use ResponseTrait;

    public function __construct()
    {
        $this->middleware(['permission:finance:list'])->only(['index', 'show']);
        $this->middleware(['permission:finance:edit'])->only('update');
    }

    public function index(Request $request)
    {
        try {
            $query = FinanceRefund::with([
                'unitTransactionRefund',
                'cash'
            ]);

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $data = $query->latest()->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'Finance Refund list retrieved successfully');
        } catch (Exception $err) {
            Log::error('Error Work While retrieving Finance Refund data: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Finance Refund list retrieval failed', 500);
        }
    }

    public function show($id)
    {
        try {
            $data = FinanceRefund::with([
                'unitTransactionRefund',
                'cash',
                'financeRefundPayments'
            ])->findOrFail($id);

            return $this->responseSuccess($data, 'Finance Refund retrieved successfully');
        } catch (Exception $err) {
            return $this->responseError($err->getMessage(), 'Finance Refund not found', 404);
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'cash_id' => 'sometimes|nullable|exists:cashes,id',
            'status' => 'sometimes|required|in:waiting,reject,approve',
        ]);

        try {
            $financeRefund = FinanceRefund::findOrFail($id);
            $financeRefund->update($validated);

            return $this->responseSuccess($financeRefund->load(['unitTransactionRefund', 'cash']), 'Finance Refund updated successfully');
        } catch (Exception $err) {
            Log::error('Error While updating Finance Refund data: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Finance Refund update failed', 500);
        }
    }
}
