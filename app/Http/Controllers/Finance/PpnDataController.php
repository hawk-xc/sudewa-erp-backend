<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\UnitTypeDetailPpn;
use App\Repositories\AuthRepository;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PpnDataController extends Controller
{
    use ResponseTrait;

    protected AuthRepository $authRepository;

    // projection
    protected $unitTypePpnDetailTable;

    /**
     * AuthController constructor.
     */
    public function __construct(AuthRepository $ar)
    {
        $this->middleware(['permission:finance:list'])->only(['index', 'show']);
        $this->middleware(['permission:finance:edit'])->only('update');

        $this->authRepository = $ar;

        $this->unitTypePpnDetailTable = [
            'id',
            'uuid',
            'unit_transaction_item_detail_id',
            'unit_transaction_id',
            'type',
            'fp_date',
            'nsfp_age',
            'nsfp_amount',
            'nsfp_number',
            'amount',
            'created_at',
            'updated_at',
        ];
    }

    public function index(Request $request)
    {
        try {
            $query = UnitTypeDetailPpn::select($this->unitTypePpnDetailTable)->with([
                'unitTransaction:id,code,created_at,person_id',
                'unitTransaction.person:id,name',
                'unitTransactionItemDetails.unitTransactionItem.unitType',
            ]);

            if ($request->type) {
                $query->where('type', match ($request->type) {
                    'ppn_purchase' => 'ppn_purchase',
                    'ppn_sales' => 'ppn_sales',
                    default => null,
                });
            }

            if ($request->filled('code')) {
                $query->whereHas('unitTransaction', function ($q) use ($request) {
                    $q->where('code', 'like', "%{$request->code}%");
                });
            }

            if ($request->filled('supplier')) {
                $query->whereHas('unitTransaction.person', function ($q) use ($request) {
                    $q->where('name', 'like', "%{$request->supplier}%");
                });
            }

            if ($request->filled('start_date')) {
                $query->whereHas('unitTransaction', function ($q) use ($request) {
                    $q->whereDate('created_at', '>=', $request->start_date);
                });
            }

            if ($request->filled('end_date')) {
                $query->whereHas('unitTransaction', function ($q) use ($request) {
                    $q->whereDate('created_at', '<=', $request->end_date);
                });
            }

            $data = $query->get();

            $result = collect();

            foreach ($data as $ppn) {

                $trx = $ppn->unitTransaction;
                $detail = $ppn->unitTransactionItemDetails;
                $item = $detail->unitTransactionItem;
                $unitType = $item->unitType;

                if (!$trx || !$detail || !$item) {
                    continue;
                }

                if ($request->filled('unit_type_id') && $item->unit_type_id != $request->unit_type_id) {
                    continue;
                }

                if ($request->filled('machine_number') && stripos($detail->machine_number, $request->machine_number) === false) {
                    continue;
                }

                if ($request->filled('chassis_number') && stripos($detail->chassis_number, $request->chassis_number) === false) {
                    continue;
                }

                if ($request->filled('color') && stripos($detail->color, $request->color) === false) {
                    continue;
                }

                $trxBrutto = $trx->getBrutoAmount();

                $dppUnit = (int) $trxBrutto / 1.11;
                $ppnUnit = (int) $dppUnit*0.11;
                $hargaUnit = (int) $item->price;

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
                    'id' => $ppn->id,
                    'code' => $trx->code,
                    'buy_date' => $trx->created_at,
                    'supplier' => $trx->person?->name,

                    'fp_date' => $ppn->fp_date,
                    'nsfp_age' => $ppn->nsfp_age,
                    'nsfp_number' => $ppn->nsfp_number,
                    'nsfp_amount' => $ppn->nsfp_amount,

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

                    'total_price' => $trx->getBrutoAmount(),
                    'unit_price' => $hargaUnit,
                    'dpp_amount' => $dppUnit,
                    'ppn_11' => $ppnUnit,

                    'payment_amount' => $dppUnit + $ppnUnit,
                ]);
            }

            $perPage = (int) ($request->per_page ?? 10);
            $page = (int) ($request->page ?? 1);

            $total = $result->count();

            $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
                $result->forPage($page, $perPage)->values(),
                $total,
                $perPage,
                $page,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );

            return $this->responseSuccess(
                $paginated,
                'PPN Pembelian retrieved successfully',
                200
            );

        } catch (Exception $err) {
            Log::error('Error PPN Purchase: ' . $err->getMessage());

            return $this->responseError(
                $err->getMessage(),
                'Failed to retrieve PPN Pembelian',
                500
            );
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $ppn = UnitTypeDetailPpn::findOrFail($id);

            $validated = $request->validate([
                'fp_date' => 'nullable|date',
                'nsfp_age' => 'nullable|string|max:50',
                'nsfp_amount' => 'nullable|numeric|min:0',
                'nsfp_number' => 'nullable|string',
                'amount' => 'nullable|numeric|min:0',
            ]);

            $ppn->update([
                'fp_date' => $validated['fp_date'] ?? $ppn->fp_date,
                'nsfp_age' => $validated['nsfp_age'] ?? $ppn->nsfp_age,
                'nsfp_amount' => $validated['nsfp_amount'] ?? $ppn->nsfp_amount,
                'nsfp_number' => $validated['nsfp_number'] ?? $ppn->nsfp_number,
                'amount' => $validated['amount'] ?? $ppn->amount,
            ]);

            return $this->responseSuccess(
                $ppn->fresh(),
                'PPN Purchase updated successfully',
                200
            );

        } catch (\Illuminate\Validation\ValidationException $err) {
            return $this->responseError($err->errors(), 'Validation failed', 422);
        } catch (Exception $err) {
            Log::error('Error Update PPN Purchase: ' . $err->getMessage());

            return $this->responseError(
                $err->getMessage(),
                'Failed to update PPN Purchase',
                500
            );
        }
    }
}
