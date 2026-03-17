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
        if (is_string($request->unit_transaction_details)) {
            $request->merge([
                'unit_transaction_details' => json_decode($request->unit_transaction_details, true),
            ]);
        }

        $validated = $request->validate([
            'unit_transaction_item_id' => 'required|int|exists:unit_transaction_items,id',
            'unit_transaction_details' => 'required|array|min:1',
            'unit_transaction_details.*' => 'integer|exists:unit_transaction_item_details,id',
        ]);

        try {
            $unitTransactionItemSales = DB::transaction(function () use ($validated) {

                $results = [];

                $uniqueDetails = array_unique($validated['unit_transaction_details']);

                foreach ($uniqueDetails as $itemId) {

                    $exists = UnitTransactionItemSales::where('unit_transaction_item_id', $validated['unit_transaction_item_id'])
                        ->where('unit_transaction_item_detail_id', $itemId)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $results[] = UnitTransactionItemSales::create([
                        'unit_transaction_item_id' => $validated['unit_transaction_item_id'],
                        'unit_transaction_item_detail_id' => $itemId,
                    ]);
                }

                return $results;
            });

            return $this->responseSuccess(
                $unitTransactionItemSales,
                'Unit Transaction Item Sales created successfully',
                201
            );

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
