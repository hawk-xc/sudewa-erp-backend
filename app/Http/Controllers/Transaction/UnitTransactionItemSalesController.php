<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\UnitTransactionItem;
use App\Models\UnitTransactionItemSales;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UnitTransactionItemSalesController extends Controller
{
    use ResponseTrait;

    public function index(Request $request)
    {
        try {
            $query = UnitTransactionItemSales::query();

            $query->with([
                'unitTransactionItem:id,unit_transaction_id,unit_type_id,qty_total',
                'unitTransactionItem.unitType:id,name',
                'unitTransactionItemDetail:id,color,machine_number,chassis_number,is_forecast,status',
                'unitTransactionItem.unitTransaction:id,code,type,created_at',
            ]);

            if ($request->filled('unit_transaction_id')) {
                $query->whereHas('unitTransactionItem', function ($q) use ($request) {
                    $q->where('unit_transaction_id', $request->unit_transaction_id);
                });
            }

            if ($request->filled('unit_type_id')) {
                $query->whereHas('unitTransactionItem', function ($q) use ($request) {
                    $q->where('unit_type_id', $request->unit_type_id);
                });
            }

            if ($request->filled('start_date') && $request->filled('end_date')) {
                $query->whereHas('unitTransactionItem.unitTransaction', function ($q) use ($request) {
                    $q->whereBetween('created_at', [
                        $request->start_date,
                        $request->end_date
                    ]);
                });
            }

            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->whereHas('unitTransactionItem.unitTransaction', function ($q2) use ($search) {
                        $q2->where('code', 'like', "%$search%");
                    })
                    ->orWhereHas('unitTransactionItemDetail', function ($q2) use ($search) {
                        $q2->where('machine_number', 'like', "%$search%")
                        ->orWhere('chassis_number', 'like', "%$search%");
                    });
                });
            }

            $query->orderBy(
                in_array($request->sort_by, ['id', 'created_at']) ? $request->sort_by : 'id',
                $request->sort_order === 'asc' ? 'asc' : 'desc'
            );

            $data = $query->paginate($request->per_page ?? 10);

            $data->getCollection()->transform(function ($item) {
                return [
                    'id' => $item->id,
                    'unit_transaction_code' => $item->unitTransactionItem->unitTransaction->code ?? null,
                    'unit_transaction_item_id' => $item->unitTransactionItem->id ?? null,
                    'transaction_type' => $item->unitTransactionItem->unitTransaction->type ?? null,
                    'unit_type_name' => $item->unitTransactionItem->unitType->name ?? null,
                    'machine_number' => $item->unitTransactionItemDetail->machine_number ?? null,
                    'chassis_number' => $item->unitTransactionItemDetail->chassis_number ?? null,
                    'color' => $item->unitTransactionItemDetail->color ?? null,
                    'is_forecast' => (bool) ($item->unitTransactionItemDetail->is_forecast ?? false),
                    'status' => $item->unitTransactionItemDetail->status ?? null,
                    'created_at' => $item->created_at,
                ];
            });

            return $this->responseSuccess($data, 'Unit Transaction Item Sales retrieved successfully', 200);

        } catch (Exception $err) {
            return $this->responseError($err->getMessage(), 'Failed to retrieve data', 500);
        }
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

        $unitTransactionItem = UnitTransactionItem::select(['id', 'qty_total'])->findOrFail(intval($validated['unit_transaction_item_id']));

        $totalSelected = count($validated['unit_transaction_details']);

        if ($totalSelected > $unitTransactionItem->qty_total) {
            throw ValidationException::withMessages([
                'unit_transaction_details' => "Selected {$totalSelected} items exceeds limit {$unitTransactionItem->qty_total} on Unit Transaction Item.",
            ]);
        }

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
            $data = UnitTransactionItemSales::with([
                    'unitTransactionItem:id,unit_transaction_id,unit_type_id,qty_total,price',
                    'unitTransactionItem.unitType:id,name',
                    'unitTransactionItem.unitTransaction:id,code,type,created_at',
                    'unitTransactionItemDetail:id,color,machine_number,chassis_number,is_forecast,status'
                ])
                ->findOrFail((int) $id);

            $result = [
                'id' => $data->id,
                'unit_transaction_code' => $data->unitTransactionItem->unitTransaction->code ?? null,
                'unit_transaction_item_id' => $item->unitTransactionItem->id ?? null,
                'transaction_type' => $data->unitTransactionItem->unitTransaction->type ?? null,
                'unit_type_name' => $data->unitTransactionItem->unitType->name ?? null,
                'qty_total' => (int) ($data->unitTransactionItem->qty_total ?? 0),
                'price' => (float) ($data->unitTransactionItem->price ?? 0),
                'machine_number' => $data->unitTransactionItemDetail->machine_number ?? null,
                'chassis_number' => $data->unitTransactionItemDetail->chassis_number ?? null,
                'color' => $data->unitTransactionItemDetail->color ?? null,
                'is_forecast' => (bool) ($data->unitTransactionItemDetail->is_forecast ?? false),
                'status' => $data->unitTransactionItemDetail->status ?? null,
                'created_at' => $data->created_at,
            ];

            return $this->responseSuccess($result, 'Unit Transaction Item Sales retrieved successfully!', 200);

        } catch (Exception $err) {
            Log::error('Error while showing Unit Transaction Item Sales '.$err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed Showing Unit Transaction Item Sales Data', 500);
        }
    }

    public function update(Request $request, string $id)
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

        $unitTransactionItem = UnitTransactionItem::select(['id', 'qty_total'])
            ->findOrFail((int) $validated['unit_transaction_item_id']);

        $totalSelected = count($validated['unit_transaction_details']);

        if ($totalSelected > $unitTransactionItem->qty_total) {
            throw ValidationException::withMessages([
                'unit_transaction_details' => "Selected {$totalSelected} items exceeds limit {$unitTransactionItem->qty_total} on Unit Transaction Item.",
            ]);
        }

        try {
            $results = DB::transaction(function () use ($validated) {

                $results = [];

                $uniqueDetails = array_unique($validated['unit_transaction_details']);

                foreach ($uniqueDetails as $detailId) {

                    $exists = UnitTransactionItemSales::where('unit_transaction_item_id', $validated['unit_transaction_item_id'])
                        ->where('unit_transaction_item_detail_id', $detailId)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $results[] = UnitTransactionItemSales::create([
                        'unit_transaction_item_id' => $validated['unit_transaction_item_id'],
                        'unit_transaction_item_detail_id' => $detailId,
                    ]);
                }

                return $results;
            });

            return $this->responseSuccess(
                $results,
                'Unit Transaction Item Sales updated successfully',
                200
            );

        } catch (Exception $err) {
            return $this->responseError(
                $err->getMessage(),
                'Update failed',
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
