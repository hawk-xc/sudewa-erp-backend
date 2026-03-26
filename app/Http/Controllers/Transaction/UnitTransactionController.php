<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Person;
use App\Models\UnitTransaction;
use App\Models\UnitTransactionItem;
use App\Models\UnitTransactionItemDetail;
use App\Models\UnitType;
use App\Traits\FileTrait;
use App\Traits\ResponseTrait;
use App\Traits\TransactionTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UnitTransactionController extends Controller
{
    use FileTrait, ResponseTrait, TransactionTrait;

    protected $unitTransactionTable;

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
            'created_at',
        ];
    }

    public function index(Request $request)
    {
        try {
            $query = UnitTransaction::query();

            if ($request->type) {
                $query->where('type', match ($request->type) {
                    'purchase' => 'purchase',
                    'sales' => 'sales',
                    default => null,
                });
            }

            $query->select($this->unitTransactionTable)
                ->with(['warehouse:id,uuid,name,capacity', 'person:id,uuid,code,name,type', 'transactionFlow:id,uuid,transaction_date,description', 'unitTransactionBilling:id,uuid,unit_transaction_id,bca_payment_amount,bca_payment_usd_amount,cash_payment_amount,bca_payment_liability,bca_payment_usd_liability,cash_payment_liability,payment_at,is_paid']);

            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%$search%")
                        ->orWhere('type', 'like', "%$search%")
                        ->orWhere('stock_state', 'like', "%$search%");
                });
            }

            foreach ($this->unitTransactionTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $allowedSort = $this->unitTransactionTable;

            $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->per_page ?? 10;

            $data = $query->paginate($perPage);

            $data->getCollection()->transform(function ($item) {
                $item->transaction_bruto_total = $item->getBrutoAmount();
                $item->transaction_dpp_total = $item->getSumAmount('dpp_total_price');
                $item->transaction_ppn_total = $item->getSumAmount('ppn_total_price');
                $item->transaction_bbn_total = $item->getSumAmount('bbn_price');
                $item->transaction_other_fee = $item->getSumAmount('other_fee');

                return $item;
            });

            return $this->responseSuccess($data, 'Unit Transaction list retrieved successfully', 200);
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
                'unitTransactionBilling',
                'unitTransactionItems:id,unit_transaction_id,uuid,qty_total,price,dpp_total_price,ppn_total_price',
                'unitTransactionItems.unitTransactionItemDetails:id,unit_transaction_item_id,uuid,color,machine_number,chassis_number,in_stock,is_forecast',
                'unitTransactionItems.unitTypeSoldDetails:id,uuid,unit_transaction_item_id,color,machine_number,chassis_number,in_stock,is_forecast',
            ])
                ->select($this->unitTransactionTable)
                ->findOrFail($id);
            $data->unit_transaction_item_total_dpp = $data->unitTransactionItems->sum('dpp_total_price');
            $data->unit_transaction_item_total_ppn = $data->unitTransactionItems->sum('ppn_total_price');
            $data->unit_transaction_item_bruto_total = $data->getBrutoAmount();

            return $this->responseSuccess($data, 'Unit Transaction retrieved successfully', 200);
        } catch (Exception $err) {
            return $this->responseError($err->getMessage(), 'Unit Transaction not found', 404);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'company_id' => 'required|integer|exists:companies,id',
                'person_id' => 'required|integer|exists:persons,id',
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

            // ❗ guard: tidak boleh dua-duanya
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

            if ($request->type === 'purchase' && $personData->type !== 'supplier') {
                throw ValidationException::withMessages([
                    'person_id' => 'Must be supplier',
                ]);
            }

            if ($request->type === 'sales' && $personData->type !== 'customer') {
                throw ValidationException::withMessages([
                    'person_id' => 'Must be customer',
                ]);
            }

            $warehouseForecastCapacity = $warehouseData->capacity - $warehouseData->getWarehouseCapacityUsage();

            if ($request->type === 'purchase' && $request->max_capacity > $warehouseForecastCapacity) {
                throw ValidationException::withMessages([
                    'max_capacity' => 'Warehouse capacity is not sufficient.',
                ]);
            }

            $validated['warehouse_id'] = $warehouseData->id;

            if (! $request->filled('code')) {
                $validated['code'] = $this->generateCode($request->type);
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
        } catch (Exception $err) {
            Log::error('Error While storing Unit Transaction: '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Failed', 500);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $unitTransaction = UnitTransaction::findOrFail((int) $id);

            $validated = $request->validate([
                'warehouse_id' => 'sometimes|integer|exists:warehouses,id',
                'person_id' => 'sometimes|integer|exists:persons,id',
                'code' => 'sometimes|required|string|max:255|unique:unit_transactions,code,'.$id,
                'type' => 'sometimes|required|string|in:purchase,sales',
                'max_capacity' => 'sometimes|numeric|min:0|max:100',
            ]);

            DB::transaction(function () use ($unitTransaction, $validated) {
                $unitTransaction->update($validated);
            });

            return $this->responseSuccess($unitTransaction->fresh(), 'Unit Transaction updated successfully', 200);
        } catch (ValidationException $e) {
            return $this->responseError($e->errors(), 'Validation failed', 422);
        } catch (Exception $err) {
            Log::error('Error While updating Unit Transaction data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Unit Transaction update failed', 500);
        }
    }

    public function updateState(Request $request, string $id)
    {
        $purchaseStates = [
            'draft', 'cancel', 'rejected', 'prepare',
            'inbound_purcase_order', 'inbound_incoming_goods',
            'inbound_receipt', 'inbound_return',
        ];

        $salesStates = [
            'draft', 'cancel', 'prepare',
            'outbound_reserved', 'outbound_in_transit',
            'outbound_delivered', 'outbound_return',
        ];

        try {
            $unitTransaction = UnitTransaction::with('unitTransactionItems')
                ->findOrFail((int) $id);

            if (is_string($request->unit_transaction_details)) {
                $request->merge([
                    'unit_transaction_details' => json_decode($request->unit_transaction_details, true),
                ]);
            }

            $validated = $request->validate([
                'stock_state' => 'required|string',
                'unit_transaction_details' => 'nullable|array',
                'unit_transaction_details.*' => 'integer|distinct|exists:unit_transaction_item_details,id',
            ]);

            $allowedStates = $unitTransaction->type === 'purchase'
                ? $purchaseStates
                : $salesStates;

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
                        $ids = $item->unitTypeSoldDetails()
                            ->pluck('unit_transaction_item_details.id')
                            ->toArray();

                        $detailIds = array_merge($detailIds, $ids);
                    }

                    $detailIds = array_unique($detailIds);
                }
            }

            if ($unitTransaction->type === 'purchase') {
                $validDetails = UnitTransactionItemDetail::whereIn('id', $detailIds)
                    ->whereHas('unitTransactionItem', function ($q) use ($unitTransaction) {
                        $q->where('unit_transaction_id', $unitTransaction->id);
                    })
                    ->get();

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
                return $this->responseError(
                    null,
                    'Selected details not found in this transaction',
                    422
                );
            }

            DB::transaction(function () use ($unitTransaction, $validated) {
                $unitTransaction->update([
                    'stock_state' => $validated['stock_state'],
                ]);

                foreach ($unitTransaction->unitTransactionItems as $item) {
                    $item->update([
                        'stock_state' => $validated['stock_state'],
                    ]);
                }
            });

            return $this->responseSuccess(
                $unitTransaction->fresh()->load([
                    'unitTransactionItems:id,uuid,unit_transaction_id,unit_type_id,sparepart_id,price',
                    'unitTransactionItems.unitTransactionItemDetails:id,uuid,unit_transaction_item_id,color,machine_number,chassis_number,in_stock,is_forecast',
                    'unitTransactionItems.unitTypeSoldDetails:id,uuid,unit_transaction_item_id,color,machine_number,chassis_number,in_stock,is_forecast',
                ]),
                'Unit Transaction state updated successfully',
                200
            );
        } catch (ValidationException $e) {
            return $this->responseError($e->errors(), 'Validation failed', 422);
        } catch (Exception $err) {
            Log::error('Error updating Unit Transaction state: '.$err->getMessage());

            return $this->responseError(
                $err->getMessage(),
                'Unit Transaction state update failed',
                500
            );
        }
    }

    public function destroy(string $id)
    {
        try {
            $data = UnitTransaction::findOrFail($id);

            DB::transaction(function () use ($data) {
                $data->delete();
            });

            return $this->responseSuccess($data, 'Unit Transaction successfully Deleted', 200);
        } catch (Exception $err) {
            Log::error('Error While deleting Unit Transaction data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Unit Transaction Not Found or Failed Deleted', 500);
        }
    }

    public function uploadInvoiceFile(Request $request, string $id)
    {

        $validated = $request->validate([
            'invoice_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        try {
            $data = UnitTransaction::findOrFail((int) $id);

            if ($request->hasFile('invoice_file')) {
                $validated['invoice_file'] = $this->storeFile(
                    $request->file('invoice_file'),
                    'invoices'
                );
            }

            $unitTransaction = DB::transaction(function () use ($validated, $data) {
                return $data->update($validated);
            });

            return $this->responseSuccess($unitTransaction, 'Successfully upload unit transaction invoice file', 200);

        } catch (Exception $err) {
            return $this->responseError($err->getMessage(), 'Failed upload unit transaction invoice file!', 0);
        }
    }
}
