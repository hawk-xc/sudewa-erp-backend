<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Person;
use App\Models\UnitTransaction;
use App\Models\UnitTransactionItemDetail;
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
                ->with([
                    'warehouse:id,uuid,name,capacity',
                    'person:id,uuid,code,name,type',
                    'transactionFlow:id,uuid,transaction_date,description',
                    'unitTransactionBilling.unitTransactionBillingHistories',
                ]);

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

                    $totalCash = (int) $billing->unitTransactionBillingHistories->sum('cash_payment_amount');
                    $totalBca = (int) $billing->unitTransactionBillingHistories->sum('bca_payment_amount');

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

            // ===== EXISTING =====
            $data->unit_transaction_bruto_total = $data->getBrutoAmount();
            $data->unit_transaction_bruto_total_actual = $data->getBrutoAmountActual();

            // ===== BILLING =====
            if ($data->unitTransactionBilling) {
                $billing = $data->unitTransactionBilling;

                $totalCash = (int) $billing->unitTransactionBillingHistories->sum('cash_payment_amount');
                $totalBca = (int) $billing->unitTransactionBillingHistories->sum('bca_payment_amount');

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
            ]);

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

            $validated['warehouse_id'] = $warehouseData->id;

            if (! $request->filled('code')) {
                $validated['code'] = $this->generateCode($request->type);
            }

            $data = DB::transaction(function () use ($validated) {
                return UnitTransaction::create($validated);
            });

            return $this->responseSuccess($data, 'Unit Transaction created successfully', 201);

        } catch (ValidationException $e) {
            return $this->responseError($e->errors(), 'Validation failed', 422);
        } catch (Exception $err) {
            Log::error('Error While storing Unit Transaction: '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Failed', 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $data = UnitTransaction::findOrFail($id);

            DB::transaction(fn () => $data->delete());

            return $this->responseSuccess($data, 'Unit Transaction successfully Deleted', 200);

        } catch (Exception $err) {
            Log::error($err->getMessage());

            return $this->responseError($err->getMessage(), 'Delete failed', 500);
        }
    }

    public function updateState(Request $request, string $id)
    {
        $purchaseStates = ['draft', 'cancel', 'rejected', 'prepare', 'inbound_purcase_order', 'inbound_incoming_goods', 'inbound_receipt', 'inbound_return'];
        $salesStates = ['draft', 'cancel', 'prepare', 'outbound_reserved', 'outbound_in_transit', 'outbound_delivered', 'outbound_return'];
        try {
            $unitTransaction = UnitTransaction::with('unitTransactionItems')->findOrFail((int) $id);
            if (is_string($request->unit_transaction_details)) {
                $request->merge(['unit_transaction_details' => json_decode($request->unit_transaction_details, true)]);
            } $validated = $request->validate(['stock_state' => 'required|string', 'unit_transaction_details' => 'nullable|array', 'unit_transaction_details.*' => 'integer|distinct|exists:unit_transaction_item_details,id']);
            $allowedStates = $unitTransaction->type === 'purchase' ? $purchaseStates : $salesStates;
            if (! in_array($validated['stock_state'], $allowedStates)) {
                return $this->responseError(null, 'Invalid stock state for this transaction type', 422);
            } if ($unitTransaction->unitTransactionItems->isEmpty()) {
                return $this->responseError(null, 'No transaction items found', 422);
            } if (! empty($validated['unit_transaction_details'])) {
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
                    } $detailIds = array_unique($detailIds);
                }
            } if ($unitTransaction->type === 'purchase') {
                $validDetails = UnitTransactionItemDetail::whereIn('id', $detailIds)->whereHas('unitTransactionItem', function ($q) use ($unitTransaction) {
                    $q->where('unit_transaction_id', $unitTransaction->id);
                })->get();
            } else {
                $validDetails = collect();
                foreach ($unitTransaction->unitTransactionItems as $item) {
                    $details = $item->unitTypeSoldDetails()->whereIn('unit_transaction_item_details.id', $detailIds)->get();
                    $validDetails = $validDetails->merge($details);
                }
            } if ($validDetails->isEmpty()) {
                return $this->responseError(null, 'Selected details not found in this transaction', 422);
            } DB::transaction(function () use ($unitTransaction, $validated) {
                $unitTransaction->update(['stock_state' => $validated['stock_state']]);
                foreach ($unitTransaction->unitTransactionItems as $item) {
                    $item->update(['stock_state' => $validated['stock_state']]);
                }
            });

            return $this->responseSuccess($unitTransaction->fresh()->load(['unitTransactionItems:id,uuid,unit_transaction_id,unit_type_id,sparepart_id,price', 'unitTransactionItems.unitTransactionItemDetails:id,uuid,unit_transaction_item_id,color,machine_number,chassis_number,in_stock,is_forecast', 'unitTransactionItems.unitTypeSoldDetails:id,uuid,unit_transaction_item_id,color,machine_number,chassis_number,in_stock,is_forecast']), 'Unit Transaction state updated successfully', 200);
        } catch (ValidationException $e) {
            return $this->responseError($e->errors(), 'Validation failed', 422);
        } catch (Exception $err) {
            Log::error('Error updating Unit Transaction state: '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Unit Transaction state update failed', 500);
        }
    }
}
