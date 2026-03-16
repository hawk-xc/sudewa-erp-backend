<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\UnitTransactionItemSales;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UnitTransactionItemSalesController extends Controller
{
    use ResponseTrait;

    public function index()
    {
        //
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'unit_transaction_item_id' => 'required|int|exists:unit_transaction_items,id',
            'unit_transaction_item_detail_id' => 'required|int|exists:unit_transaction_item_details,id',
        ]);

        try {
            $unitTransactionItemSales = DB::transaction(function () use ($validated) {
                return UnitTransactionItemSales::create($validated);
            });

            return $this->responseSuccess($unitTransactionItemSales, 'Unit Transaction Item Sales created successfully', 201);
        } catch (Exception $err) {
            return $this->responseError(
                $err->getMessage(),
                'Validation failed',
                422
            );
        }
    }

    public function show(string $id)
    {
        try {
            $unitTransactionItemSales = UnitTransactionItemSales::findOrFail((int) $id)->with(['unitTransactionItem', 'unitTransactionItemDetail']);

            return $this->responseSuccess($unitTransactionItemSales, 'Unit Transaction Item Sales retrieved successfully!', 200);
        } catch (Exception $err) {
            Log::error('Error while showing Unit Transaction Item Sales '.$err->getMessage());
            $this->responseError($err->getMessage(), 'Failed Showing Unit Transaction Item Sales Data', 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $unitTransactionItemSales = UnitTransactionItemSales::findOrFail((int) $id);

        $validated = $request->validate([
            'unit_transaction_item_id' => 'required|int|exists:unit_transaction_items,id',
            'unit_transaction_item_detail_id' => 'required|int|exists:unit_transaction_item_details,id',
        ]);

        try {
            $unitTransactionItemSalesUpdate = DB::transaction(function () use ($validated, $unitTransactionItemSales) {
                return $unitTransactionItemSales::update($validated);
            });

            return $this->responseSuccess($unitTransactionItemSalesUpdate->fresh(), 'Unit Transaction Item Sales created successfully', 201);
        } catch (Exception $err) {
            return $this->responseError(
                $err->getMessage(),
                'Validation failed',
                422
            );
        }
    }

    public function destroy(string $id)
    {
        $unitTranscationItemSalesData = UnitTransactionItemSales::findOrFail((int) $id);

        try {
            DB::transaction(function () use ($unitTranscationItemSalesData) {
                $unitTranscationItemSalesData->delete();
            });

            return $this->responseSuccess([], 'Unit Transaction Item Detail successfully Deleted', 200);
        } catch (Exception $err) {
            Log::error('Error While deleting Unit Transaction Item Sales data : '.$err->getMessage());

            return $this->responseError(
                $err->getMessage(),
                '',
                422
            );
        }
    }
}
