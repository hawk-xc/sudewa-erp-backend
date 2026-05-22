<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\UnitTransactionItem;
use App\Repositories\AuthRepository;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TransactionReportController extends Controller
{
    use ResponseTrait;

    protected AuthRepository $authRepository;

    public function __construct(AuthRepository $ar)
    {
        $this->authRepository = $ar;
        $this->middleware(['permission:report:list'])->only(['purchaseTransactionReport', 'salesTransactionReport']);
    }

    public function purchaseTransactionReport(Request $request)
    {
        return $this->getTransactionReport($request, 'purchase');
    }

    public function salesTransactionReport(Request $request)
    {
        return $this->getTransactionReport($request, 'sales');
    }

    private function getTransactionReport(Request $request, string $type)
    {
        try {
            $query = UnitTransactionItem::query()
                ->whereHas('unitTransaction', function ($q) use ($type) {
                    $q->where('type', $type);
                })
                ->with([
                    'unitTransaction:id,code,type,person_id,warehouse_id,created_at',
                    'unitTransaction.person:id,name',
                    'unitType:id,code,name,unit_type,unit_model',
                    'unitTransaction.unitTransactionBilling:id,unit_transaction_id,is_paid,grand_total',
                ]);

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->whereHas('unitTransaction', function ($sq) use ($search) {
                        $sq->where('code', 'like', "%$search%");
                    })->orWhereHas('unitTransaction.person', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%$search%");
                    })->orWhereHas('unitType', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%$search%")
                            ->orWhere('code', 'like', "%$search%");
                    });
                });
            }

            if ($request->filled('start_date') && $request->filled('end_date')) {
                $query->whereHas('unitTransaction', function ($q) use ($request) {
                    $q->whereBetween('created_at', [$request->start_date . ' 00:00:00', $request->end_date . ' 23:59:59']);
                });
            }

            $perPage = $request->per_page ?? 10;
            $data = $query->paginate($perPage);

            $data->getCollection()->transform(function ($item) {
                return [
                    'id' => $item->id,
                    'transaction_date' => $item->unitTransaction->created_at ?? null,
                    'transaction_code' => $item->unitTransaction->code ?? null,
                    'person_name' => $item->unitTransaction->person->name ?? null,
                    'unit_name' => $item->unitType->name ?? null,
                    'unit_code' => $item->unitType->code ?? null,
                    'qty' => $item->qty_total,
                    'price' => $item->price,
                    'dpp' => $item->dpp_total_price,
                    'ppn' => $item->ppn_total_price,
                    'bbn' => $item->bbn_price,
                    'other_fee' => $item->other_fee,
                    'expedition_fee' => $item->expedition_fee,
                    'hpp_fee' => $item->hpp_total_price,
                    'total' => (float) ($item->dpp_total_price + $item->ppn_total_price + ($item->bbn_price ?? 0) + ($item->other_fee ?? 0)),
                    'is_paid' => (bool) ($item->unitTransaction->unitTransactionBilling->is_paid ?? false),
                    'payment_status' => ($item->unitTransaction->unitTransactionBilling->is_paid ?? false) ? 'Lunas' : 'Belum Lunas',
                ];
            });

            return $this->responseSuccess($data, ucfirst($type) . ' transaction report retrieved successfully');
        } catch (Exception $e) {
            Log::error("Error in {$type}TransactionReport: " . $e->getMessage());
            return $this->responseError($e->getMessage(), 'Failed to retrieve report', 500);
        }
    }
}
