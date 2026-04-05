<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\UnitTransaction;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LiabilityController extends Controller
{
    use ResponseTrait;

    public function __construct()
    {
        $this->middleware(['permission:report:list'])->only(['index', 'show']);
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

            $query->with([
                'person:id,name',
                'unitTransactionBilling.unitTransactionBillingHistories',
            ]);

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%$search%")
                        ->orWhereHas('person', function ($q) use ($search) {
                            $q->where('name', 'like', "%$search%");
                        });
                });
            }

            if ($request->filled('liability_status')) {
                $status = $request->liability_status;
                $query->whereHas('unitTransactionBilling', function ($q) use ($status) {
                    if ($status === 'paid') {
                        $q->where('is_paid', true);
                    } elseif ($status === 'unpaid') {
                        $q->where('is_paid', false);
                    }
                });
            }

            $data = $query->paginate($request->per_page ?? 10);

            $data->getCollection()->transform(function ($item, $key) use ($data) {
                $item->code = $item->code;
                $item->date = $item->created_at;
                $item->supplier_name = $item->person->name ?? '-';

                $buyTotal = 0;
                $paidTotal = 0;
                $liabilityTotal = 0;

                if ($item->unitTransactionBilling) {
                    $billing = $item->unitTransactionBilling;

                    $totalCash = (int) $billing->unitTransactionBillingHistories->sum('cash_payment_amount');
                    $totalBca = (int) $billing->unitTransactionBillingHistories->sum('bca_payment_amount');

                    $buyTotal = (int) $billing->grand_total;
                    $paidTotal = $totalCash + $totalBca;
                    $liabilityTotal = $buyTotal - $paidTotal;
                }

                $item->grand_total = $buyTotal;
                $item->total_paid = $paidTotal;
                $item->remaining_payment = $liabilityTotal;
                $item->is_paid = $item->unitTransactionBilling->is_paid ?? false;
                $item->paid_percentage = $buyTotal > 0 ? round(($paidTotal / $buyTotal) * 100, 2) : 0;

                return $item;
            });

            return $this->responseSuccess($data, 'Liability report data retrieved successfully', 200);

        } catch (Exception $err) {
            Log::error($err->getMessage());

            return $this->responseError(null, 'Failed to retrieve data', 500);
        }
    }

    public function show(string $id)
    {
        try {
            $data = UnitTransaction::with([
                'person:id,uuid,code,name,type',
                'unitTransactionBilling.unitTransactionBillingHistories',
                'unitTransactionItems.unitType',
            ])->findOrFail($id);

            $buyTotal = 0;
            $paidTotal = 0;
            $liabilityTotal = 0;
            $isPaid = false;

            if ($data->unitTransactionBilling) {
                $billing = $data->unitTransactionBilling;

                $totalCash = (int) $billing->unitTransactionBillingHistories->sum('cash_payment_amount');
                $totalBca = (int) $billing->unitTransactionBillingHistories->sum('bca_payment_amount');

                $buyTotal = (int) $billing->grand_total;
                $paidTotal = $totalCash + $totalBca;
                $liabilityTotal = $buyTotal - $paidTotal;
                $isPaid = $billing->is_paid;
            }

            $data->billing_summary = [
                'grand_total' => $buyTotal,
                'total_paid' => $paidTotal,
                'remaining_payment' => $liabilityTotal,
                'is_paid' => $isPaid,
                'paid_percentage' => $buyTotal > 0 ? round(($paidTotal / $buyTotal) * 100, 2) : 0,
            ];

            return $this->responseSuccess($data, 'Liability detail data retrieved successfully', 200);

        } catch (Exception $err) {
            Log::error($err->getMessage());

            return $this->responseError(null, 'Detail data not found', 404);
        }
    }
}



