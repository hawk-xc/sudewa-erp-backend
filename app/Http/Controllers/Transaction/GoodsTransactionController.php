<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\GoodsTransaction;
use App\Models\GoodsTransactionDetail;
use App\Models\Person;
use App\Models\VehicleFleet;
use App\Rules\RightPersonRule;
use App\Traits\FileTrait;
use App\Traits\GlobalCodeNumberTrait;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class GoodsTransactionController extends Controller
{
    use ResponseTrait, GlobalCodeNumberTrait, FileTrait;

    // projection
    protected array $goodsTransactionTable;

    protected $companySlug = "wjt";

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:warehouse:list'])->only('maintenance');
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only(['update', 'updateState', 'uploadInvoice']);
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);

        $this->goodsTransactionTable = [
            'id',
            'uuid',
            'code',
            'company_id',
            'supplier_id',
            'driver_id',
            'vehicle_fleet_id',
            'category',
            'type',
            'transaction_date',
            'location',
            'description',
            'invoice_file',
            'created_at',
        ];
    }

    /**
     * List all goods transactions.
     */
    public function index(Request $request)
    {
        $query = GoodsTransaction::query();

        $query->with(['supplier:id,uuid,name', 'driver:id,uuid,name', 'goodsTransactionBillings:id,goods_transaction_id,is_paid']);
        
        if ($request->filled('type')) {
            if ($request->type == 'receipt') {
                $query->where('type', 'receipt');
            } else {
                $query->where('type', 'issue');
            }
        } 

        $isPaidQuery = null;
        if ($request->filled('is_paid')) {
            $isPaidQuery = $request->is_paid;
        } elseif ($request->filled('billing_status')) {
            $isPaidQuery = $request->billing_status;
        }

        if (!is_null($isPaidQuery)) {
            $query->whereHas('goodsTransactionBillings', function ($q) use ($isPaidQuery) {
                $q->where('is_paid', filter_var($isPaidQuery, FILTER_VALIDATE_BOOLEAN));
            });
        }

        $query->select($this->goodsTransactionTable);

        try {
            if ($request->filled('search')) {
                $search = $request->search;
                $caseSensitive = $request->boolean('case_sensitive');

                $query->where(function ($q) use ($search, $caseSensitive) {
                    if ($caseSensitive) {
                        $q->where('code', 'LIKE BINARY', "%$search%")
                            ->orWhere('description', 'LIKE BINARY', "%$search%")
                            ->orWhereHas('supplier', function ($q) use ($search) {
                                $q->where('name', 'LIKE BINARY', "%$search%");
                            });
                    } else {
                        $q->where('code', 'like', "%$search%")
                            ->orWhere('description', 'like', "%$search%")
                            ->orWhereHas('supplier', function ($q) use ($search) {
                                $q->where('name', 'like', "%$search%");
                            });
                    }
                });
            }

            foreach ($this->goodsTransactionTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $allowedSort = $this->goodsTransactionTable;

            $sortBy = in_array($request->sort_by, $allowedSort)
                ? $request->sort_by
                : 'id';

            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->per_page ?? 10;

            $totalQuery = clone $query;
            $priceTotal = GoodsTransactionDetail::whereIn(
                'goods_transaction_id',
                $totalQuery->select('id')
            )->sum('price');

            $paginated = $query->paginate($perPage);
            
            $paginated->getCollection()->each(function ($item) {
                $isPaid = $item->goodsTransactionBillings ? (bool) $item->goodsTransactionBillings->is_paid : false;
                $item->billing_status = [
                    'is_paid' => $isPaid,
                ];
                $item->is_paid = $isPaid;
                $item->makeHidden(['goodsTransactionDetails', 'goodsTransactionBillings']);
            });

            $data = $paginated->toArray();
            $data['price_total'] = (int) $priceTotal;

            return $this->responseSuccess($data, 'Goods Transaction list retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error While retrieved Goods Transaction data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Goods Transaction list retrieved Failed', 500);
        }
    }

    /**
     * Store a new goods transaction.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:receipt,issue',
            'category' => 'required_if:type,issue|in:maintenance,equipped',
            'company_id' => 'required|exists:companies,id',
            'supplier_id' => [
                'required_if:type,receipt',
                new RightPersonRule('supplier')
            ],
            'driver_id' => [
                'required_if:type,issue',
                new RightPersonRule('driver'),
            ],
            'vehicle_fleet_id' => [
                'required_if:type,issue',
                'exists:vehicle_fleets,id',
                function ($attribute, $value, $fail) use ($request) {
                    if ($value) {
                        $fleet = VehicleFleet::find($value);
                        if ($fleet) {
                            $now = now();
                            $stnkAge = $fleet->stnk_age ? \Carbon\Carbon::parse($fleet->stnk_age) : null;
                            $kirAge = $fleet->kir_age ? \Carbon\Carbon::parse($fleet->kir_age) : null;
                            
                            if (!$stnkAge || !$stnkAge->lessThan($now)) {
                                $fail('The vehicle fleet stnk_age must be less than now.');
                            }
                            if (!$kirAge || !$kirAge->lessThan($now)) {
                                $fail('The vehicle fleet kir_age must be less than now.');
                            }
                        }

                        if ($request->type === 'issue' && $request->filled('transaction_date')) {
                            $exists = GoodsTransaction::where('type', 'issue')
                                ->where('vehicle_fleet_id', $value)
                                ->whereDate('transaction_date', $request->transaction_date)
                                ->exists();
                            if ($exists) {
                                $fail('The selected vehicle fleet already has an issue transaction on the same day.');
                            }
                        }
                    }
                }
            ],
            'transaction_date' => 'required|date',
            'location' => 'required_if:type,receipt|string|max:255',
            'description' => 'nullable|string'
        ], [
            'type.required' => 'The type is required.',
            'type.in' => 'The type must be receipt or issue.',
            'company_id.required' => 'The company is required.',
            'company_id.exists' => 'The selected company is invalid.',
            'supplier_id.required_if' => 'The supplier is required when the transaction type is receipt.',
            'supplier_id.exists' => 'The selected supplier/customer is invalid or does not match the transaction type.',
            'driver_id.required_if' => 'The driver is required when the transaction type is issue.',
            'driver_id.exists' => 'The selected driver is invalid or must be of type driver.',
            'vehicle_fleet_id.required_if' => 'The vehicle fleet is required when the transaction type is issue.',
            'vehicle_fleet_id.exists' => 'The selected vehicle fleet is invalid or must be of type vehicle fleet.',
            'location.required_if' => 'The location is required when the transaction type is receipt.',
            'category.required_if' => 'The category is required when the transaction type is issue.',
        ]);

        try {
            $data = DB::transaction(function () use ($request, $validated) {
                $typeState = $request->type == "receipt" ? "penerimaan" : "pengeluaran";
                if ($request->type == 'receipt') {
                    $validated['code'] = $this->code($this->companySlug, 'beli_penerimaan_perlengkapan');
                } else {
                    $validated['code'] = $this->code($this->companySlug, 'pengeluaran_perlengkapan');
                }
                
                if (empty($validated['description'])) {
                    $supplierName = 'supplier';
                    if (!empty($validated['supplier_id'])) {
                        $supplier = Person::find($validated['supplier_id']);
                        if ($supplier) {
                            $supplierName = $supplier->name;
                        }
                    }
                    $validated['description'] = "Pembaruan " . $typeState . " barang ke/dari " . $supplierName;
                }

                return GoodsTransaction::create($validated);
            });

            return $this->responseSuccess($data, 'Goods Transaction created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error while trying create Goods Transaction Data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Goods Transaction creation failed', 500);
        }
    }

    /**
     * Get goods transaction details.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $data = GoodsTransaction::with([
                'goodsTransactionDetails:id,uuid,qty,type,price,goods_transaction_id,material_id,vehicle_equipment_id',
                'goodsTransactionDetails.material', 
                'goodsTransactionDetails.vehicleEquipment:id,uuid,code,name', 
                'goodsTransactionBillings.payments.cash',
                'company:id,uuid,code,name,type',
                'supplier:id,uuid,code,name,type',
                'driver:id,uuid,code,name,type',
                'vehicleFleet:id,uuid,type,registration_number,machine_number,chassis_number,stnk_age,kir_age,stnk_number,kir_book'
            ])
            ->select($this->goodsTransactionTable)
            ->findOrFail($id);

            $data->makeHidden('total_brutto');
            
            return $this->responseSuccess($data, 'Goods Transaction detail retrieved successfully', 200);
        } catch (ModelNotFoundException $err) {
            return $this->responseError(null, 'Goods Transaction not found', 404);
        } catch (Exception $err) {
            Log::error('Error while retrieving Goods Transaction data: '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'An unexpected error occurred', 500);
        }
    }

    /**
     * Update a goods transaction.
     */
    public function update(Request $request, string $id)
    {
        try {
            $transaction = GoodsTransaction::findOrFail($id);

            $request->validate([
                'company_id' => 'sometimes|exists:companies,id',
                'supplier_id' => [
                    'sometimes',
                    new RightPersonRule(($request->type ?? $transaction->type) === 'receipt' ? 'supplier' : 'customer')
                ],
                'driver_id' => [
                    'sometimes',
                    new RightPersonRule('driver')
                ],
                'vehicle_fleet_id' => [
                    'sometimes',
                    'exists:vehicle_fleets,id',
                    function ($attribute, $value, $fail) {
                        if ($value) {
                            $fleet = VehicleFleet::find($value);
                            if ($fleet) {
                                $now = now();
                                $stnkAge = $fleet->stnk_age ? \Carbon\Carbon::parse($fleet->stnk_age) : null;
                                $kirAge = $fleet->kir_age ? \Carbon\Carbon::parse($fleet->kir_age) : null;
                                
                                if (!$stnkAge || !$stnkAge->lessThan($now)) {
                                    $fail('The vehicle fleet stnk_age must be less than now.');
                                }
                                if (!$kirAge || !$kirAge->lessThan($now)) {
                                    $fail('The vehicle fleet kir_age must be less than now.');
                                }
                            }
                        }
                    }
                ],
                'category' => 'sometimes|in:maintenance,equipped',
                'transaction_date' => 'sometimes|required|date',
                'location' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
            ], [
                'supplier_id.exists' => 'The selected supplier/customer is invalid or does not match the transaction type.',
                'driver_id.exists' => 'The selected driver is invalid or must be of type driver.',
            ]);
            
            $data = array_filter(
                $request->only(['company_id', 'supplier_id', 'driver_id', 'vehicle_fleet_id', 'category', 'type', 'transaction_date', 'location', 'description']),
                fn ($val) => ! is_null($val) && $val !== ''
            );

            if (empty($data)) {
                return $this->responseError(null, 'No data provided to update', 422);
            }

            DB::transaction(function () use ($data, $transaction) {
                $transaction->update($data);
            });

            return $this->responseSuccess($transaction->fresh(), 'Goods Transaction updated successfully', 200);
        } catch (ModelNotFoundException $err) {
            return $this->responseError(null, 'Goods Transaction not found', 404);
        } catch (Exception $err) {
            Log::error('Error while updating Goods Transaction data: '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Goods Transaction update failed', 500);
        }
    }

    /**
     * Delete a goods transaction.
     */
    public function destroy(string $id)
    {
        try {
            $data = GoodsTransaction::findOrFail((int) $id);

            DB::transaction(function () use ($data) {
                $data->delete();
            });

            return $this->responseSuccess(null, 'Goods Transaction deleted successfully', 200);
        } catch (ModelNotFoundException $err) {
            return $this->responseError(null, 'Goods Transaction not found', 404);
        } catch (Exception $err) {
            Log::error('Error while deleting Goods Transaction data: '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Goods Transaction deletion failed', 500);
        }
    }

    /**
     * Update goods transaction stock state.
     */
    public function updateState(Request $request, string $id)
    {
        $purchaseStates = ['draft', 'cancel', 'rejected', 'prepare', 'inbound_purchase_order', 'inbound_incoming_goods', 'inbound_receipt'];
        $salesStates = ['draft', 'cancel', 'prepare', 'outbound_reserved', 'outbound_in_transit', 'outbound_delivered'];

        try {
            $transaction = GoodsTransaction::with('goodsTransactionDetails')->findOrFail((int) $id);

            if (is_string($request->goods_transaction_details)) {
                $request->merge(['goods_transaction_details' => json_decode($request->goods_transaction_details, true)]);
            }

            $validated = $request->validate([
                'stock_state' => 'required|string',
                'goods_transaction_details' => 'nullable|array',
                'goods_transaction_details.*' => 'integer|distinct|exists:goods_transaction_details,id',
            ]);

            $allowedStates = $transaction->type === 'receipt' ? $purchaseStates : $salesStates;

            if (! in_array($validated['stock_state'], $allowedStates)) {
                return $this->responseError(null, 'Invalid stock state for this transaction type', 422);
            }

            if ($transaction->goodsTransactionDetails->isEmpty()) {
                return $this->responseError(null, 'No transaction items found', 422);
            }

            if (! empty($validated['goods_transaction_details'])) {
                $detailIds = array_unique($validated['goods_transaction_details']);
            } else {
                $detailIds = $transaction->goodsTransactionDetails->pluck('id')->toArray();
            }

            $validDetails = GoodsTransactionDetail::whereIn('id', $detailIds)
                ->where('goods_transaction_id', $transaction->id)
                ->get();

            if ($validDetails->isEmpty()) {
                return $this->responseError(null, 'Selected details not found in this transaction', 422);
            }

            DB::transaction(function () use ($validated, $validDetails) {
                // Since stock_state column was removed, we do not update $transaction->stock_state here.
                switch ($validated['stock_state']) {
                    case 'inbound_incoming_goods':
                        GoodsTransactionDetail::whereIn('id', $validDetails->pluck('id'))
                            ->update([
                                'is_forecast' => true,
                            ]);
                        break;

                    case 'outbound_delivered':
                        GoodsTransactionDetail::whereIn('id', $validDetails->pluck('id'))
                            ->update([
                                'is_forecast' => true,
                            ]);
                        break;
                }
            });

            return $this->responseSuccess(
                $transaction->fresh()->load([
                    'goodsTransactionDetails'
                ]),
                'Goods Transaction state updated successfully',
                200
            );

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->responseError($e->errors(), 'Validation failed', 422);
        } catch (Exception $err) {
            Log::error('Error updating Goods Transaction state: '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Goods Transaction state update failed', 500);
        }
    }

    public function uploadInvoice(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'invoice_file' => 'required|file|mimes:pdf,doc,docx|max:2048',
        ]);

        try {
            $transaction = GoodsTransaction::findOrFail((int) $id);

            $path = $this->updateFile(
                $request->file('invoice_file'),
                $transaction->invoice_file,
                'invoices'
            );

            $transaction->update(['invoice_file' => $path]);

            return $this->responseSuccess(null, 'Invoice uploaded successfully', 200);
        } catch (ModelNotFoundException $err) {
            return $this->responseError(null, 'Goods Transaction not found', 404);
        } catch (Exception $err) {
            Log::error('Error while uploading invoice: '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Invoice upload failed', 500);
        }
    }

    public function maintenance(Request $request): JsonResponse
    {
        $query = GoodsTransaction::query();
        
        $query->where('category', 'maintenance');
        
        $query->with([
            'goodsTransactionDetails:id,uuid,qty,type,price,goods_transaction_id,material_id,vehicle_equipment_id',
            'goodsTransactionDetails.material', 
            'goodsTransactionDetails.vehicleEquipment:id,uuid,code,name', 
            'goodsTransactionBillings.payments.cash',
            'company:id,uuid,code,name,type',
            'supplier:id,uuid,code,name,type',
            'driver:id,uuid,code,name,type',
            'vehicleFleet:id,uuid,type,registration_number,machine_number,chassis_number,stnk_age,kir_age,stnk_number,kir_book'
        ]);

        $query->select($this->goodsTransactionTable);

        try {
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('vehicleFleet', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('registration_number', 'like', "%{$search}%");
                        });
                });
            }

            foreach ($this->goodsTransactionTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $allowedSort = $this->goodsTransactionTable;
            $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->per_page ?? 10;
            $data = $query->paginate($perPage);

            return $this->responseSuccess($data, 'Goods Transaction maintenance list retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error While retrieved Goods Transaction maintenance data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Goods Transaction maintenance list retrieved Failed', 500);
        }
    }
}
