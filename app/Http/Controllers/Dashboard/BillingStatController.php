<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Cash;
use App\Models\UnitTransaction;
use App\Models\UnitTransactionBilling;
use App\Models\UnitTransactionItem;
use App\Models\UnitTransactionItemSales;
use App\Models\UnitType;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BillingStatController extends Controller
{
    use ResponseTrait;
    public function billingStat(Request $request)
    {
        try {
            $startDate = $request->start_date ? date('Y-m-d 00:00:00', strtotime($request->start_date)) : null;
            $endDate   = $request->end_date ? date('Y-m-d 23:59:59', strtotime($request->end_date)) : null;

            if ($request->filled('date')) {
                $startDate = date('Y-m-d 00:00:00', strtotime($request->date));
                $endDate   = date('Y-m-d 23:59:59', strtotime($request->date));
            }

            $query = UnitTransactionBilling::query();

            $query->with([
                'unitTransaction:id,uuid,code,type,warehouse_id,person_id,created_at',
                'unitTransaction.warehouse:id,company_id',
                'unitTransactionBillingHistories' => function ($q) use ($endDate) {
                    if ($endDate) {
                        $q->where('payment_at', '<=', $endDate);
                    }
                },
                'unitTransactionBillingHistories.cashes'
            ]);

            if ($request->filled('type') && in_array($request->type, ['purchase', 'sales'])) {
                $query->whereHas('unitTransaction', function ($q) use ($request) {
                    $q->where('type', $request->type);
                });
            }

            // Apply detail filters
            if ($request->filled('company_id')) {
                $query->whereHas('unitTransaction.warehouse', function ($q) use ($request) {
                    $q->where('company_id', $request->company_id);
                });
            }

            if ($request->filled('cash_id')) {
                $query->whereHas('unitTransactionBillingHistories.cashes', function ($q) use ($request) {
                    $q->where('cashes.id', $request->cash_id);
                });
            }

            if ($request->filled('warehouse_id')) {
                $query->whereHas('unitTransaction', function ($q) use ($request) {
                    $q->where('warehouse_id', $request->warehouse_id);
                });
            }

            if ($request->filled('person_id')) {
                $query->whereHas('unitTransaction', function ($q) use ($request) {
                    $q->where('person_id', $request->person_id);
                });
            }

            $query->where('is_paid', true);

            $billings = $query->get();

            // Fetch relevant cash records to dynamically build keys based on Cash model relation
            $cashQuery = Cash::query();
            if ($request->filled('company_id')) {
                $cashQuery->where('company_id', $request->company_id);
            }
            if ($request->filled('cash_id')) {
                $cashQuery->where('id', $request->cash_id);
            }
            $cashes = $cashQuery->get();
            $uniqueCodes = $cashes->pluck('code')->unique()->toArray();

            $openingBalance = [
                'debet' => [],
                'kredit' => [],
            ];

            $mutation = [
                'debet' => [],
                'kredit' => [],
            ];

            foreach ($uniqueCodes as $code) {
                $openingBalance['debet'][$code] = 0;
                $openingBalance['kredit'][$code] = 0;
                $mutation['debet'][$code] = 0;
                $mutation['kredit'][$code] = 0;
            }

            foreach ($billings as $billing) {
                $type = $billing->unitTransaction->type === 'purchase' ? 'debet' : 'kredit';

                foreach ($billing->unitTransactionBillingHistories as $history) {
                    $paymentDate = $history->payment_at ?? $billing->created_at;

                    foreach ($history->cashes as $cashRelation) {
                        // Apply filters inside cash aggregation
                        if ($request->filled('cash_id') && $cashRelation->id != $request->cash_id) {
                            continue;
                        }
                        if ($request->filled('company_id') && $cashRelation->company_id != $request->company_id) {
                            continue;
                        }

                        $code = $cashRelation->code;
                        $amount = (int) $cashRelation->pivot->amount;

                        // Ensure dynamic key initialization in case a history references a code not in the filtered cashes list
                        if (!isset($openingBalance['debet'][$code])) {
                            $openingBalance['debet'][$code] = 0;
                            $openingBalance['kredit'][$code] = 0;
                            $mutation['debet'][$code] = 0;
                            $mutation['kredit'][$code] = 0;
                        }

                        if (!$startDate && !$endDate) {
                            $openingBalance[$type][$code] += $amount;
                            continue;
                        }

                        if ($startDate && $paymentDate < $startDate) {
                            $openingBalance[$type][$code] += $amount;
                        }

                        if (
                            (!$startDate || $paymentDate >= $startDate) &&
                            (!$endDate || $paymentDate <= $endDate)
                        ) {
                            $mutation[$type][$code] += $amount;
                        }
                    }
                }
            }

            $calculatePercentage = function ($data) {
                $total = array_sum($data);
                $percentages = [];
                foreach ($data as $code => $value) {
                    $percentages[$code] = $total > 0 ? round(($value / $total) * 100, 2) : 0;
                }
                return $percentages;
            };

            $percentage = [
                'opening_balance' => [
                    'debet' => $calculatePercentage($openingBalance['debet']),
                    'kredit' => $calculatePercentage($openingBalance['kredit']),
                ],
                'mutation' => [
                    'debet' => $calculatePercentage($mutation['debet']),
                    'kredit' => $calculatePercentage($mutation['kredit']),
                ],
            ];

            return response()->json([
                'status' => true,
                'message' => 'Billing statistics retrieved successfully',
                'data' => [
                    'opening_balance' => $openingBalance,
                    'mutation' => $mutation,
                    'percentage' => $percentage,
                ],
                'filters' => [
                    'company_id' => $request->company_id,
                    'cash_id' => $request->cash_id,
                    'warehouse_id' => $request->warehouse_id,
                    'person_id' => $request->person_id,
                    'type' => $request->type,
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date,
                    'date' => $request->date,
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve billing statistics',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    public function customerOverview(Request $request)
    {
        try {
            $perPage = $request->per_page ?? 5;

            $query = UnitTransaction::query();

            $query->where('type', 'sales')
                ->whereHas('unitTransactionBilling', function ($q) {
                    $q->where('is_paid', true);
                })
                ->with([
                    'person:id,uuid,name,code',
                    'unitTransactionBilling.unitTransactionBillingHistories.cashes'
                ]);

            if ($request->filled('search')) {
                $search = $request->search;

                $query->whereHas('person', function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%");
                });
            }

            $transactions = $query->get();

            $customers = [];

            foreach ($transactions as $trx) {
                $person = $trx->person;

                if (!$person) continue;

                $customerId = $person->id;

                if (!isset($customers[$customerId])) {
                    $customers[$customerId] = [
                        'customer_id' => $person->id,
                        'customer_name' => $person->name,
                        'customer_code' => $person->code,
                        'total_transaction' => 0,
                        'total_cash' => 0,
                        'total_bca_idr' => 0,
                        'total_bca_usd' => 0,
                    ];
                }

                $customers[$customerId]['total_transaction'] += 1;

                foreach ($trx->unitTransactionBilling->unitTransactionBillingHistories as $history) {
                    $customers[$customerId]['total_cash'] += (int) $history->cashes->where('code', 'cash_idr')->sum('pivot.amount');
                    $customers[$customerId]['total_bca_idr'] += (int) $history->cashes->where('code', 'bca_idr')->sum('pivot.amount');
                    $customers[$customerId]['total_bca_usd'] += (int) $history->cashes->where('code', 'bca_usd')->sum('pivot.amount');
                }
            }

            $customerList = collect($customers)->map(function ($item) {

                $totalRevenue =
                    $item['total_cash'] +
                    $item['total_bca_idr'] +
                    $item['total_bca_usd'];

                $item['total_revenue'] = $totalRevenue;

                return $item;
            })->values();

            $totalCustomer = $customerList->count();

            $totalRevenueAll = $customerList->sum('total_revenue');

            $averageRevenue = $totalCustomer > 0
                ? round($totalRevenueAll / $totalCustomer, 2)
                : 0;

            $page = $request->page ?? 1;

            $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
                $customerList->forPage($page, $perPage)->values(),
                $customerList->count(),
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => request()->query()]
            );

            return response()->json([
                'status' => true,
                'message' => 'Customer overview retrieved successfully',
                'data' => [
                    'summary' => [
                        'total_customer' => $totalCustomer,
                        'total_revenue' => $totalRevenueAll,
                        'average_revenue_per_customer' => $averageRevenue,
                    ],
                    'customers' => $paginated,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve customer overview',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    public function unitTypeOverview(Request $request)
    {
        try {
            $query = UnitTransactionItem::query();

            $query->whereHas('unitTransaction', function ($q) {
                $q->where('type', 'sales');
            });

            if ($request->filled('unit_type_id')) {
                $query->where('unit_type_id', $request->unit_type_id);
            }

            $query->with([
                'unitType:id,uuid,name',
                'unitTransactionItemSales:id,unit_transaction_item_id',
            ]);

            $data = $query->get()
                ->groupBy('unit_type_id')
                ->map(function ($items, $unitTypeId) {

                    $unitType = $items->first()->unitType;

                    $totalProducts = $items->count();

                    $totalActual = $items->sum(function ($item) {
                        return $item->unitTransactionItemSales->count();
                    });

                    $totalForecast = max(0, $totalProducts - $totalActual);

                    return [
                        'unit_type_id' => $unitTypeId,
                        'unit_type_name' => $unitType->name ?? '-',
                        'total_products' => $totalProducts,
                        'total_sold_actual' => $totalActual,
                        'total_sold_forecast' => $totalForecast,
                        'total_sold' => $totalActual + $totalForecast,
                    ];
                })
                ->values();

            $perPage = $request->per_page ?? 5;
            $currentPage = $request->page ?? 1;

            $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
                $data->forPage($currentPage, $perPage),
                $data->count(),
                $perPage,
                $currentPage,
                ['path' => request()->url()]
            );

            $totalUnitType = UnitType::count();

            $totalSoldUnitType = $data->filter(function ($item) {
                return $item['total_sold_actual'] > 0;
            })->count();

            return $this->responseSuccess((object) [
                'summary' => [
                    'total_unit_type' => $totalUnitType,
                    'total_unit_type_sold' => $totalSoldUnitType,
                ],
                'data' => $paginated,
            ], 'Unit Type overview retrieved successfully', 200);
        } catch (Exception $err) {
            return $this->responseError(null, 'Failed to retrieve data', 500);
        }
    }

    public function revenueOverview(Request $request) {}
}
