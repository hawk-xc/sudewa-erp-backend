<?php

namespace App\Http\Controllers\Transaction\UnitTransactionPurchase;

use App\Http\Controllers\Controller;
use App\Models\UnitTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class UnitTransactionController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = UnitTransaction::with(['warehouse', 'person', 'transactionFlow', 'unitTransactionBilling']);

            if ($request->warehouse_id) {
                $query->where('warehouse_id', $request->warehouse_id);
            }

            if ($request->type) {
                $query->where('type', $request->type);
            }

            $data = $query->latest()->paginate($request->per_page ?? 10);

            return response()->json([
                'success' => true,
                'message' => 'Unit Transactions retrieved successfully',
                'data' => $data
            ], 200);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'person_id' => 'required|exists:persons,id',
            'code' => 'required|string|max:255|unique:unit_transactions,code',
            'type' => 'required|string|max:255|in:purchase,sales',
            'max_capacity' => 'required|decimal:0,2|max:100|min:0',
            'stock_state' => 'required|string|max:255|in:draft,cancel,rejected,prepare,inbound_purcase_order,inbound_incoming_goods,inbound_receipt,inbound_return,outbound_reserved,outbound_in_transit,outbound_delivered,outbound_return',
        ]);

        try {
            $data = DB::transaction(function () use ($validated) {
                return UnitTransaction::create($validated);
            });

            return response()->json([
                'success' => true,
                'message' => 'Unit Transaction created successfully',
                'data' => $data
            ], 201);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $data = UnitTransaction::with(['warehouse', 'person', 'transactionFlow', 'unitTransactionBilling'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Unit Transaction retrieved successfully',
                'data' => $data
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unit Transaction not found'
            ], 404);
        }
    }

    public function edit(string $id)
    {
        try {
            $data = UnitTransaction::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Unit Transaction retrieved successfully',
                'data' => $data
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unit Transaction not found'
            ], 404);
        }
    }

    public function update(Request $request, string $id)
    {
        $unitTransaction = UnitTransaction::findOrFail($id);

        $validated = $request->validate([
            'warehouse_id' => 'sometimes|exists:warehouses,id',
            'person_id' => 'sometimes|exists:persons,id',
            'code' => 'sometimes|string|max:255|unique:unit_transactions,code,' . $id,
            'type' => 'sometimes|string|max:255|in:purchase,sales',
            'max_capacity' => 'sometimes|decimal:0,2|max:100|min:0',
            'stock_state' => 'sometimes|string|max:255|in:draft,cancel,rejected,prepare,inbound_purcase_order,inbound_incoming_goods,inbound_receipt,inbound_return,outbound_reserved,outbound_in_transit,outbound_delivered,outbound_return',
        ]);

        try {
            DB::transaction(function () use ($validated, $unitTransaction) {
                $unitTransaction->update($validated);
            });

            return response()->json([
                'success' => true,
                'message' => 'Unit Transaction updated successfully',
                'data' => $unitTransaction->fresh()
            ], 200);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error'
            ], 500);
        }
    }

    public function updateState(Request $request, string $id)
    {
        $unitTransaction = UnitTransaction::findOrFail($id);

        $validated = $request->validate([
            'stock_state' => 'required|string|max:255|in:draft,cancel,rejected,prepare,inbound_purcase_order,inbound_incoming_goods,inbound_receipt,inbound_return,outbound_reserved,outbound_in_transit,outbound_delivered,outbound_return',
        ]);

        try {
            DB::transaction(function () use ($validated, $unitTransaction) {
                $unitTransaction->update($validated);
            });

            return response()->json([
                'success' => true,
                'message' => 'Unit Transaction updated successfully',
                'data' => $unitTransaction->fresh()
            ], 200);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error'
            ], 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $data = UnitTransaction::findOrFail($id);

            DB::transaction(function () use ($data) {
                $data->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Unit Transaction deleted successfully'
            ], 200);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error'
            ], 500);
        }
    }
}