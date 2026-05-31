<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\CashFlow;
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
            $query = CashFlow::query()->whereNotNull('unit_transaction_billing_id');

            if ($request->type) {
                $query->whereHas('unitTransactionBilling.unitTransaction', function ($q) use ($request) {
                    $q->where('type', match ($request->type) {
                        'purchase' => 'purchase',
                        'sales' => 'sales',
                        default => null,
                    });
                });
            }

            $query->with([
                'financeBilling.financeBillingItems',
            ]);

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%$search%")
                        ->orWhereHas('unitTransactionBilling.unitTransaction', function ($qUt) use ($search) {
                            $qUt->where('code', 'like', "%$search%")
                                ->orWhereHas('person', function ($qp) use ($search) {
                                    $qp->where('name', 'like', "%$search%");
                                });
                        });
                });
            }

            if ($request->filled('liability_status')) {
                $status = $request->liability_status;
                $query->whereHas('financeBilling', function ($q) use ($status) {
                    if ($status === 'paid') {
                        $q->where('is_valid', true);
                    } elseif ($status === 'unpaid') {
                        $q->where('is_valid', false);
                    }
                });
            }

            $data = $query->paginate($request->per_page ?? 10);

            $data->getCollection()->transform(function ($item, $key) {
                $utBilling = $item->unitTransactionBilling;
                $unitTransaction = $utBilling ? $utBilling->unitTransaction : null;
                $person = $unitTransaction ? $unitTransaction->person : null;

                $item->code = $unitTransaction ? $unitTransaction->code : '-';
                $item->date = $item->date ?? $item->created_at;
                $item->supplier_name = $person ? $person->name : '-';

                $buyTotal = $utBilling ? (int) $utBilling->grand_total : 0;
                $paidTotal = 0;
                $liabilityTotal = $buyTotal;
                $isPaid = false;

                if ($item->financeBilling) {
                    $financeBilling = $item->financeBilling;
                    $items = $financeBilling->financeBillingItems;

                    $totalCash = $items->sum('cash_payment_amount');
                    $totalBca = $items->sum('bca_payment_amount');

                    $buyTotal = (int) $financeBilling->grand_total;
                    $paidTotal = $totalCash + $totalBca;
                    $liabilityTotal = $buyTotal - $paidTotal;
                    $isPaid = $financeBilling->is_valid;
                }

                $item->grand_total = $buyTotal;
                $item->total_paid = $paidTotal;
                $item->remaining_payment = $liabilityTotal;
                $item->is_paid = $isPaid;
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
            $data = CashFlow::with([
                'financeBilling.financeBillingItems',
            ])->findOrFail($id);

            $utBilling = $data->unitTransactionBilling;
            $buyTotal = $utBilling ? (int) $utBilling->grand_total : 0;
            $paidTotal = 0;
            $liabilityTotal = $buyTotal;
            $isPaid = false;

            if ($data->financeBilling) {
                $financeBilling = $data->financeBilling;
                $items = $financeBilling->financeBillingItems;

                $totalCash = $items->sum('cash_payment_amount');
                $totalBca = $items->sum('bca_payment_amount');

                $buyTotal = (int) $financeBilling->grand_total;
                $paidTotal = $totalCash + $totalBca;
                $liabilityTotal = $buyTotal - $paidTotal;
                $isPaid = $financeBilling->is_valid;
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



