<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\UnitTransaction;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PpnPurchaseController extends Controller
{
    use ResponseTrait;

    public function index(Request $request)
    {
        try {
            $query = UnitTransaction::with([
                'person:id,name',
                'unitTransactionItems.unitType',
                'unitTransactionItems.unitTransactionItemDetails',
            ])->where('type', 'purchase');

            if ($request->filled('code')) {
                $query->where('code', 'like', "%{$request->code}%");
            }

            if ($request->filled('supplier')) {
                $query->whereHas('person', function ($q) use ($request) {
                    $q->where('name', 'like', "%{$request->supplier}%");
                });
            }

            if ($request->filled('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }

            $transactions = $query->get();

            $result = collect();

            foreach ($transactions as $trx) {

                foreach ($trx->unitTransactionItems as $item) {

                    if ($request->filled('unit_type_id') && $item->unit_type_id != $request->unit_type_id) {
                        continue;
                    }

                    $unitType = $item->unitType;
                    $details = $item->unitTransactionItemDetails;

                    $qty = $details->count();

                    if ($qty <= 0) {
                        continue;
                    }

                    $hargaUnit = (int) $item->price;
                    $dppUnit = (int) $item->dpp_per_unit_price;
                    $ppnUnit = (int) $item->ppn_per_unit_price;

                    foreach ($details as $detail) {

                        if ($request->filled('machine_number') && stripos($detail->machine_number, $request->machine_number) === false) {
                            continue;
                        }

                        if ($request->filled('chassis_number') && stripos($detail->chassis_number, $request->chassis_number) === false) {
                            continue;
                        }

                        if ($request->filled('color') && stripos($detail->color, $request->color) === false) {
                            continue;
                        }

                        if ($request->filled('min_price') && $hargaUnit < $request->min_price) {
                            continue;
                        }

                        if ($request->filled('max_price') && $hargaUnit > $request->max_price) {
                            continue;
                        }

                        if ($request->filled('min_dpp') && $dppUnit < $request->min_dpp) {
                            continue;
                        }

                        if ($request->filled('max_dpp') && $dppUnit > $request->max_dpp) {
                            continue;
                        }

                        if ($request->filled('min_ppn') && $ppnUnit < $request->min_ppn) {
                            continue;
                        }

                        if ($request->filled('max_ppn') && $ppnUnit > $request->max_ppn) {
                            continue;
                        }

                        $result->push([
                            'code' => $trx->code,
                            'buy_date' => $trx->created_at,
                            'supplier' => $trx->person?->name,

                            'fpm_date' => null,
                            'nsfpm_age' => null,
                            'nsfpm_input' => null,

                            'qty' => 1,

                            'unit_type' => [
                                'id' => $unitType->id,
                                'code' => $unitType->code,
                                'name' => $unitType->name,
                                'unit_type' => $unitType->unit_type,
                                'unit_model' => $unitType->unit_model,
                            ],

                            'unit_transaction_item_detail' => [
                                'id' => $detail->id,
                                'machine_number' => $detail->machine_number,
                                'chassis_number' => $detail->chassis_number,
                                'color' => $detail->color,
                            ],

                            'unit_price' => $hargaUnit,
                            'dpp_amount' => $dppUnit,
                            'ppn_11' => $ppnUnit,

                            'payment_amount' => $hargaUnit,
                        ]);
                    }
                }
            }

            return $this->responseSuccess(
                $result->values(),
                'PPN Pembelian retrieved successfully',
                200
            );

        } catch (Exception $err) {
            Log::error('Error PPN Purchase: '.$err->getMessage());

            return $this->responseError(
                null,
                'Failed to retrieve PPN Pembelian',
                500
            );
        }
    }
}
