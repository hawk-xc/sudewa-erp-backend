<?php

namespace App\Http\Controllers\Transaction;

use App\Exports\UnitTransactionUnitTypeStockExport;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Person;
use App\Models\UnitTransaction;
use App\Models\UnitTransactionAdjustment;
use App\Models\UnitTransactionItem;
use App\Models\UnitTransactionItemDetail;
use App\Models\UnitType;
use App\Traits\FileTrait;
use App\Traits\ResponseTrait;
use App\Traits\GlobalCodeNumberTrait;
use App\Rules\RightPersonRule;
use App\Rules\RightCashRule;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class UnitTransactionController extends Controller
{
    use FileTrait, ResponseTrait, GlobalCodeNumberTrait;

    protected array $unitTransactionTable;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only(['update', 'updateState']);
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);

        $this->unitTransactionTable = [
            'id',
            'uuid',
            'warehouse_id',
            'person_id',
            'code',
            'type',
            'max_capacity',
            'stock_state',
            'invoice_file',
            'is_refunded',
            'created_at',
        ];
    }

    public function index(Request $request)
    {
        try {
            $query = UnitTransaction::query();

            $query->withCount('unitTransactionItems');

            if ($request->type) {
                $query->where('type', match ($request->type) {
                    'purchase' => 'purchase',
                    'sales' => 'sales',
                    default => null,
                });
            }

            $query->select($this->unitTransactionTable)
                ->withCount([
                    'unitTransactionItems as unit_transaction_item_counts'
                ])
                ->with([
                    'warehouse:id,uuid,name,capacity',
                    'person:id,uuid,code,name,type',
                    'transactionFlow:id,uuid,transaction_date,description',
                    'unitTransactionBilling.unitTransactionBillingHistories',
                ]);

            if ($request->filled('is_paid')) {
                $query->whereHas('unitTransactionBilling', function ($q) use ($request) {
                    $q->where('is_paid', $request->is_paid == 'true' ? true : 0);
                });
            }

            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%$search%")
                        ->orWhere('type', 'like', "%$search%")
                        ->orWhere('stock_state', 'like', "%$search%");
                });
            }

            $query->orderBy(
                in_array($request->sort_by, $this->unitTransactionTable) ? $request->sort_by : 'id',
                $request->sort_order === 'asc' ? 'asc' : 'desc'
            );

            $data = $query->paginate($request->per_page ?? 10);

            $data->getCollection()->transform(function ($item) {

                $item->transaction_bruto_total = $item->getBrutoAmount();
                $item->transaction_dpp_total = $item->getSumAmount('dpp_total_price');
                $item->transaction_ppn_total = $item->getSumAmount('ppn_total_price');
                $item->transaction_bbn_total = $item->getSumAmount('bbn_price');
                $item->transaction_other_fee = $item->getSumAmount('other_fee');

                if ($item->unitTransactionBilling) {
                    $billing = $item->unitTransactionBilling;

                    $totalCash = $billing->getTotalCashPayment();
                    $totalBca = $billing->getTotalBcaCashPayment();

                    $totalPaid = $totalCash + $totalBca;
                    $remaining = (int) $billing->grand_total - $totalPaid;

                    $item->billing_summary = [
                        'grand_total' => (int) $billing->grand_total,
                        'total_cash_payment' => $totalCash,
                        'total_bca_payment' => $totalBca,
                        'total_paid' => $totalPaid,
                        'remaining_payment' => $remaining,
                        'is_paid' => $billing->is_paid,
                    ];
                } else {
                    $item->billing_summary = null;
                }

                return $item;
            });

            return $this->responseSuccess($data, 'Unit Transaction list retrieved successfully', 200);

        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error While retrieved Unit Transaction data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Unit Transaction list retrieved Failed', 500);
        }
    }

    public function show(string $id)
    {
        try {
            $data = UnitTransaction::with([
                'warehouse:id,uuid,name,capacity',
                'person:id,uuid,code,type,name',
                'transactionFlow:id,uuid,transaction_date,description',
                'unitTransactionBilling.unitTransactionBillingHistories',
                'unitTransactionItems',
                'unitTransactionItems.unitTransactionItemDetails',
                'unitTransactionItems.unitTypeSoldDetails',
            ])
                ->select($this->unitTransactionTable)
                ->findOrFail($id);

            $data->unit_transaction_bruto_total = $data->getBrutoAmount();
            $data->unit_transaction_bruto_total_actual = $data->getBrutoAmountActual();
            $data->unit_transaction_bruto_refund = $data->getBrutoAmountRefund();
            $data->unit_transaction_bruto_return = $data->getBrutoAmountReturn();

            if ($data->unitTransactionBilling) {
                $billing = $data->unitTransactionBilling;

                $totalCash = $billing->getTotalCashPayment();
                $totalBca = $billing->getTotalBcaCashPayment();

                $totalPaid = $totalCash + $totalBca;
                $remaining = (int) $billing->grand_total - $totalPaid;

                $data->billing_summary = [
                    'grand_total' => (int) $billing->grand_total,
                    'total_cash_payment' => $totalCash,
                    'total_bca_payment' => $totalBca,
                    'total_paid' => $totalPaid,
                    'remaining_payment' => $remaining,
                    'is_paid' => $billing->is_paid,
                ];
            } else {
                $data->billing_summary = null;
            }

            $data->unitTransactionAdjustments->transform(function ($adjustment) {
                $adjustment->details = $adjustment->unitTransactionAdjustmentItems
                    ->groupBy(function ($item) {
                        return $item->unitTransactionItem->unitType->name ?? 'Unknown';
                    })
                    ->map(function ($items, $unitTypeName) {
                        return [
                            'unit_type_name' => $unitTypeName,
                            'qty' => $items->sum('qty'),
                        ];
                    })
                    ->values();
                
                // Unset raw items to keep response clean
                unset($adjustment->unitTransactionAdjustmentItems);
                
                return $adjustment;
            });

            return $this->responseSuccess($data, 'Unit Transaction retrieved successfully', 200);

        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            return $this->responseError($err->getMessage(), 'Unit Transaction not found', 404);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'company_id' => 'required|integer|exists:companies,id',
                'person_id' => [
                    'required',
                    'integer',
                    new RightPersonRule($request->type === 'purchase' ? 'supplier' : 'customer')
                ],
                'code' => 'sometimes|string|max:255|unique:unit_transactions,code',
                'type' => 'required|string|in:purchase,sales',
                'max_capacity' => 'required|numeric|min:0|max:100',
                'stock_state' => 'required|string',
 
                // optional item
                'unit_type_id' => 'nullable|integer|exists:unit_types,id',
                'sparepart_id' => 'nullable|integer|exists:spareparts,id',
                'qty_total' => 'required_with:unit_type_id,sparepart_id|integer|min:1',
                'price' => 'required_with:unit_type_id,sparepart_id|numeric',
                'bbn_price' => 'nullable|numeric',
                'other_fee' => 'nullable|numeric',
            ]);
 
            if ($request->filled('unit_type_id') && $request->filled('sparepart_id')) {
                return $this->responseError(
                    'Please select either unit_type_id or sparepart_id',
                    'Validation failed',
                    422
                );
            }
 
            $warehouseData = Company::findOrFail($request->company_id)
                ->warehouse()
                ->firstOrCreate(
                    ['company_id' => $request->company_id],
                    [
                        'name' => 'Company Default Warehouse',
                        'capacity' => 100,
                        'description' => 'Default Warehouse',
                    ]
                );
 
            $personData = Person::findOrFail($request->person_id);

            $warehouseForecastCapacity = $warehouseData->capacity - $warehouseData->getWarehouseCapacityUsage();

            if ($request->type === 'purchase' && $request->max_capacity > $warehouseForecastCapacity) {
                throw ValidationException::withMessages([
                    'max_capacity' => 'Warehouse capacity is not sufficient.',
                ]);
            }

            $validated['warehouse_id'] = $warehouseData->id;

            if (! $request->filled('code')) {
                $companySlug = $personData->company?->slug ?? '';
                $feature = $request->type === 'purchase' ? 'pembelian' : 'sales';
                $validated['code'] = $this->code($companySlug, $feature);
            }

            $data = DB::transaction(function () use ($validated, $request) {

                $unitTransaction = UnitTransaction::create($validated);

                if ($request->filled('unit_type_id') || $request->filled('sparepart_id')) {

                    if ($unitTransaction->type === 'sales' && $request->filled('unit_type_id')) {

                        $stock = UnitType::findOrFail($request->unit_type_id)
                            ->getRealStock($unitTransaction->warehouse_id);

                        if ($stock <= 0) {
                            throw ValidationException::withMessages([
                                'unit_type_id' => 'No stock available',
                            ]);
                        }

                        if ($request->qty_total > $stock) {
                            throw ValidationException::withMessages([
                                'qty_total' => 'Qty exceeds stock',
                            ]);
                        }
                    }

                    if ($request->qty_total > $unitTransaction->max_capacity) {
                        throw ValidationException::withMessages([
                            'qty_total' => 'Exceeds transaction capacity',
                        ]);
                    }

                    $additional_fee =
                        ($request->bbn_price ?? 0) +
                        ($request->other_fee ?? 0);

                    $hpp = $request->price - $additional_fee;
                    $dpp = ceil($hpp / 1.11);
                    $ppn = floor($dpp * 0.11);

                    UnitTransactionItem::create([
                        'unit_transaction_id' => $unitTransaction->id,
                        'unit_type_id' => $request->unit_type_id,
                        'sparepart_id' => $request->sparepart_id,
                        'qty_total' => $request->qty_total,
                        'price' => $request->price,
                        'bbn_price' => $request->bbn_price ?? 0,
                        'other_fee' => $request->other_fee ?? 0,

                        'hpp_per_unit_price' => $hpp,
                        'dpp_per_unit_price' => $dpp,
                        'ppn_per_unit_price' => $ppn,

                        'hpp_total_price' => $hpp * $request->qty_total,
                        'dpp_total_price' => $dpp * $request->qty_total,
                        'ppn_total_price' => $ppn * $request->qty_total,
                    ]);
                }

                return $unitTransaction;
            });

            return $this->responseSuccess(
                $data->load('unitTransactionItems'),
                'Unit Transaction created successfully',
                201
            );

        } catch (ValidationException $e) {
            return $this->responseError($e->errors(), 'Validation failed', 422);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error While storing Unit Transaction: '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Failed', 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $data = UnitTransaction::with('unitTransactionBilling.financeBilling')->findOrFail($id);

            if ($data->unitTransactionBilling) {
                if ($data->unitTransactionBilling->financeBilling && $data->unitTransactionBilling->financeBilling->is_valid) {
                    return $this->responseError(null, 'Cannot delete because finance billing is already valid', 422);
                }

                if ($data->unitTransactionBilling->is_paid) {
                    return $this->responseError(null, 'Cannot delete because transaction is already paid', 422);
                }
            }

            DB::transaction(fn () => $data->delete());

            return $this->responseSuccess($data, 'Unit Transaction successfully Deleted', 200);

        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error($err->getMessage());

            return $this->responseError($err->getMessage(), 'Delete failed', 500);
        }
    }

    public function updateState(Request $request, string $id)
    {
        $purchaseStates = ['draft', 'cancel', 'rejected', 'prepare', 'inbound_purcase_order', 'inbound_incoming_goods', 'inbound_receipt'];
        $salesStates = ['draft', 'cancel', 'prepare', 'outbound_reserved', 'outbound_in_transit', 'outbound_delivered'];

        try {
            $unitTransaction = UnitTransaction::with('unitTransactionItems')->findOrFail((int) $id);

            if (is_string($request->unit_transaction_details)) {
                $request->merge(['unit_transaction_details' => json_decode($request->unit_transaction_details, true)]);
            }

            $validated = $request->validate([
                'stock_state' => 'required|string',
                'unit_transaction_details' => 'nullable|array',
                'unit_transaction_details.*' => 'integer|distinct|exists:unit_transaction_item_details,id',
            ]);

            $allowedStates = $unitTransaction->type === 'purchase' ? $purchaseStates : $salesStates;

            if (! in_array($validated['stock_state'], $allowedStates)) {
                return $this->responseError(null, 'Invalid stock state for this transaction type', 422);
            }

            if ($unitTransaction->unitTransactionItems->isEmpty()) {
                return $this->responseError(null, 'No transaction items found', 422);
            }

            if (! empty($validated['unit_transaction_details'])) {
                $detailIds = array_unique($validated['unit_transaction_details']);
            } else {
                if ($unitTransaction->type === 'purchase') {
                    $detailIds = UnitTransactionItemDetail::whereHas('unitTransactionItem', function ($q) use ($unitTransaction) {
                        $q->where('unit_transaction_id', $unitTransaction->id);
                    })->pluck('id')->toArray();
                } else {
                    $detailIds = [];
                    foreach ($unitTransaction->unitTransactionItems as $item) {
                        $ids = $item->unitTypeSoldDetails()->pluck('unit_transaction_item_details.id')->toArray();
                        $detailIds = array_merge($detailIds, $ids);
                    }
                    $detailIds = array_unique($detailIds);
                }
            }

            if ($unitTransaction->type === 'purchase') {
                $validDetails = UnitTransactionItemDetail::whereIn('id', $detailIds)
                    ->whereHas('unitTransactionItem', function ($q) use ($unitTransaction) {
                        $q->where('unit_transaction_id', $unitTransaction->id);
                    })->get();
            } else {
                $validDetails = collect();
                foreach ($unitTransaction->unitTransactionItems as $item) {
                    $details = $item->unitTypeSoldDetails()
                        ->whereIn('unit_transaction_item_details.id', $detailIds)
                        ->get();
                    $validDetails = $validDetails->merge($details);
                }
            }

            if ($validDetails->isEmpty()) {
                return $this->responseError(null, 'Selected details not found in this transaction', 422);
            }

            DB::transaction(function () use ($unitTransaction, $validated, $validDetails) {

                $unitTransaction->update(['stock_state' => $validated['stock_state']]);

                foreach ($unitTransaction->unitTransactionItems as $item) {
                    $item->update(['stock_state' => $validated['stock_state']]);
                }

                switch ($validated['stock_state']) {
                    case 'inbound_incoming_goods':
                        UnitTransactionItemDetail::whereIn('id', $validDetails->pluck('id'))
                            ->update([
                                'is_forecast' => true,
                            ]);
                        break;

                    case 'outbound_delivered':
                        UnitTransactionItemDetail::whereIn('id', $validDetails->pluck('id'))
                            ->update([
                                'is_forecast' => true,
                            ]);
                        break;
                }
            });

            return $this->responseSuccess(
                $unitTransaction->fresh()->load([
                    'unitTransactionItems:id,uuid,unit_transaction_id,unit_type_id,sparepart_id,price',
                    'unitTransactionItems.unitTransactionItemDetails:id,uuid,unit_transaction_item_id,color,machine_number,chassis_number,in_stock,is_forecast,status',
                    'unitTransactionItems.unitTypeSoldDetails:id,uuid,unit_transaction_item_id,color,machine_number,chassis_number,in_stock,is_forecast,status',
                    'unitTransactionAdjustments:id,uuid,unit_transaction_id,cash_id,amount,description,created_at',
                ]),
                'Unit Transaction state updated successfully',
                200
            );

        } catch (ValidationException $e) {
            return $this->responseError($e->errors(), 'Validation failed', 422);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error updating Unit Transaction state: '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Unit Transaction state update failed', 500);
        }
    }

    public function getStock(Request $request)
    {
        try {
            $query = UnitTransaction::query();

            if ($request->filled('type') && in_array($request->type, ['purchase', 'sales'])) {
                $query->where('type', (string) $request->type);
            }

            if ($request->filled('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }

            $query->with([
                'person:id,name',
                'unitTransactionItems.unitType:id,code,name,unit_type',
                'unitTransactionItems.unitTransactionItemDetails:id,unit_transaction_item_id,is_forecast',
            ]);

            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%$search%");
                });
            }

            $data = $query->paginate($request->input('per_page', 10));

            $collection = $data->getCollection()->transform(function ($trx) {

                $items = collect();

                foreach ($trx->unitTransactionItems as $item) {

                    $forecastQty = $item->unitTransactionItemDetails
                        ->where('is_forecast', true)
                        ->count();

                    $actualQty = $item->unitTransactionItemDetails->count();

                    $items->push([
                        'unit_transaction' => [
                            'id' => $trx->id,
                            'code' => $trx->code,
                            'person' => $trx->person?->name,
                            'created_at' => $trx->created_at,
                        ],
                        'unit_type' => [
                            'id' => $item->unitType?->id,
                            'code' => $item->unitType?->code,
                            'name' => $item->unitType?->name,
                            'unit_type' => $item->unitType?->unit_type,
                        ],
                        'qty_forecast' => $forecastQty,
                        'qty_actual' => $actualQty,
                        'qty_input' => (int) $item->qty_total,
                        'qty_difference' => (int) $item->qty_total - $actualQty,
                    ]);
                }

                return [
                    'id' => $trx->id,
                    'code' => $trx->code,
                    'date' => preg_replace('/\s.*/', '', (string) $trx->created_at),
                    'person' => $trx->person?->name,
                    'items' => $items,
                ];
            });

            $data->setCollection($collection);

            return $this->responseSuccess($data, 'Stock data retrieved successfully', 200);

        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error($err->getMessage());

            return $this->responseError(null, 'Failed to retrieve stock data', 500);
        }
    }

    public function exportStock(Request $request)
    {
        try {
            return Excel::download(
                new UnitTransactionUnitTypeStockExport($request),
                'wajira_stock_data.xlsx'
            );
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error export stock : '.$err->getMessage());

            return $this->responseError(
                $err->getMessage(),
                'Stock export failed',
                500
            );
        }
    }
    
    public function refund(Request $request, string $id)
    {
        $validated = $request->validate([
            'cash_id' => [
                'required',
                'integer',
                'exists:cashes,id',
                new RightCashRule(fn () => \App\Models\UnitTransaction::find($id)?->warehouse?->company_id),
            ],
            'amount' => 'nullable|numeric|min:0',
            'description' => 'required|string',
            'unit_transaction_details' => 'required|array|min:1',
            'unit_transaction_details.*' => 'integer|exists:unit_transaction_item_details,id',
        ]);

        try {
            $unitTransaction = UnitTransaction::findOrFail($id);

            if ($unitTransaction->type !== 'sales') {
                return $this->responseError(null, 'Refund is only allowed for sales transactions.', 422);
            }

            if (!$unitTransaction->unitTransactionBilling || !$unitTransaction->unitTransactionBilling->is_paid) {
                return $this->responseError(null, 'Transaction has not been paid.', 422);
            }

            $amount = (int) ($validated['amount'] ?? $unitTransaction->getBrutoAmount());

            $data = DB::transaction(function () use ($unitTransaction, $validated, $amount) {
                $unitTransaction->update(['is_refunded' => true]);

                $adjustment = $unitTransaction->unitTransactionAdjustments()->create([
                    'cash_id' => $validated['cash_id'],
                    'amount' => $amount,
                    'description' => $validated['description'],
                    'type' => 'refund',
                ]);

                $details = UnitTransactionItemDetail::whereIn('id', $validated['unit_transaction_details'])->get();
                foreach ($details as $detail) {
                    $detail->refundStock();
                }

                return $adjustment;
            });

            return $this->responseSuccess($data, 'Refund processed successfully');

        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $e) {
            Log::error('Refund error: ' . $e->getMessage());
            return $this->responseError($e->getMessage(), 'Refund failed', 500);
        }
    }

    public function return(Request $request, string $id)
    {
        $validated = $request->validate([
            'cash_id' => [
                'required',
                'integer',
                'exists:cashes,id',
                new RightCashRule(fn () => \App\Models\UnitTransaction::find($id)?->warehouse?->company_id),
            ],
            'amount' => 'nullable|numeric|min:0',
            'description' => 'required|string',
            'unit_transaction_details' => 'required|array|min:1',
            'unit_transaction_details.*' => 'integer|exists:unit_transaction_item_details,id',
        ]);

        try {
            $unitTransaction = UnitTransaction::findOrFail($id);

            if ($unitTransaction->type !== 'purchase') {
                return $this->responseError(null, 'Return is only allowed for purchase transactions.', 422);
            }

            if (!$unitTransaction->unitTransactionBilling || !$unitTransaction->unitTransactionBilling->is_paid) {
                return $this->responseError(null, 'Transaction has not been paid.', 422);
            }

            $amount = (int) ($validated['amount'] ?? $unitTransaction->getBrutoAmount());

            $data = DB::transaction(function () use ($unitTransaction, $validated, $amount) {
                $unitTransaction->update(['is_refunded' => true]);

                $adjustment = $unitTransaction->unitTransactionAdjustments()->create([
                    'cash_id' => $validated['cash_id'],
                    'amount' => $amount,
                    'description' => $validated['description'],
                    'type' => 'return',
                ]);

                $details = UnitTransactionItemDetail::whereIn('id', $validated['unit_transaction_details'])->get();
                foreach ($details as $detail) {
                    $detail->returnStock();
                }

                return $adjustment;
            });

            return $this->responseSuccess($data, 'Return processed successfully');

        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $e) {
            Log::error('Return error: ' . $e->getMessage());
            return $this->responseError($e->getMessage(), 'Return failed', 500);
        }
    }

    public function storeTransactionAdjustment(Request $request, string $id)
    {
        if (is_string($request->unit_transaction_item_details_ids)) {
            $request->merge([
                'unit_transaction_item_details_ids' => json_decode($request->unit_transaction_item_details_ids, true),
            ]);
        }

        try {
            $validated = $request->validate([
                'cash_id' => [
                    'required',
                    'integer',
                    'exists:cashes,id',
                    new RightCashRule(fn () => \App\Models\UnitTransaction::find($id)?->warehouse?->company_id),
                ],
                'amount' => 'required|numeric|min:0',
                'description' => 'nullable|string',
                'unit_transaction_item_details_ids' => 'sometimes|nullable|array',
                'unit_transaction_item_details_ids.*' => 'required|integer|exists:unit_transaction_item_details,id',
            ]);

            $unitTransaction = UnitTransaction::findOrFail($id);

            if (!$unitTransaction->unitTransactionBilling || !$unitTransaction->unitTransactionBilling->is_paid) {
                return $this->responseError(null, 'Transaction has not been paid or has no billing data.', 422);
            }

            $adjustmentType = match ($unitTransaction->type) {
                'purchase' => 'return',
                'sales' => 'refund',
                default => throw new Exception("Invalid transaction type for adjustment")
            };

            $adjustment = DB::transaction(function () use ($validated, $unitTransaction, $adjustmentType) {
                // Prepare primary adjustment data
                $adjustmentData = [
                    'cash_id' => $validated['cash_id'],
                    'amount' => $validated['amount'],
                    'description' => $validated['description'] ?? null,
                    'type' => $adjustmentType,
                ];

                $adjustment = $unitTransaction->unitTransactionAdjustments()->create($adjustmentData);

                // Create adjustment items if detail IDs are provided
                if (!empty($validated['unit_transaction_item_details_ids'])) {
                    $details = UnitTransactionItemDetail::whereIn('id', $validated['unit_transaction_item_details_ids'])->get();
                    foreach ($details as $detail) {
                        $adjustment->unitTransactionAdjustmentItems()->create([
                            'unit_transaction_item_id' => $detail->unit_transaction_item_id,
                            'unit_transaction_item_detail_id' => $detail->id,
                            'qty' => 1,
                        ]);

                        // Trigger stock adjustment based on type
                        if ($adjustmentType === 'refund') {
                            $detail->refundStock();
                        } else {
                            $detail->returnStock();
                        }
                    }
                }

                $unitTransaction->update(['is_refunded' => true]);

                return $adjustment->load('unitTransactionAdjustmentItems.unitTransactionItem.unitType');
            });

            return $this->responseSuccess($adjustment, 'Unit Transaction Adjustment created successfully', 201);

        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while creating Unit Transaction Adjustment: '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Unit Transaction Adjustment creation failed', 500);
        }
    }
}
