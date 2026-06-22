<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\BBNBill;
use App\Models\VehicleRegistration;
use App\Repositories\AuthRepository;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VehicleRegistrationReport extends Controller
{
    use ResponseTrait;

    protected AuthRepository $authRepository;

    public function __construct(AuthRepository $ar)
    {
        $this->authRepository = $ar;
        $this->middleware(['permission:report:list'])->only(['getBPKBReport', 'getSTNKReport', 'getSKPDReport', 'getTNKBReport']);
    }

    protected function basicQuery(string $dataType, Request $request)
    {
        $query = VehicleRegistration::query()->with(['vendor:persons.id,persons.name,persons.code', 'vehicleData']);

        // Map placeholder fields to actual columns
        $column = match ($dataType) {
            'stnk_number' => 'stnk_registration_date',
            'skpd_number' => 'skpd_payment_date',
            default => $dataType,
        };

        if ($request->boolean('is_outstanding')) {
            $query->whereNull($column);
        } else {
            $query->whereNotNull($column);
        }

        // Filter: Vendor
        if ($request->filled('vendor_id')) {
            $query->whereHas('ditlantasProcess', function ($q) use ($request) {
                $q->where('vendor_id', $request->vendor_id);
            });
        }

        // Filter: Dealer
        if ($request->filled('dealer_id')) {
            $query->whereHas('vehicleData', function ($q) use ($request) {
                $q->where('dealer_id', $request->dealer_id);
            });
        }

        // Filter: Physical Status (bpkb_physical_status, stnk_physical_status, etc.)
        foreach (['bpkb_physical_status', 'stnk_physical_status', 'skpd_physical_status', 'tnkb_physical_status'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->boolean($field));
            }
        }

        // Filter: Physical Status specific to the report type
        if ($request->filled('physical_status')) {
            $statusField = match ($dataType) {
                'bpkb_number' => 'bpkb_physical_status',
                'stnk_number' => 'stnk_physical_status',
                'skpd_number' => 'skpd_physical_status',
                'tnkb_number' => 'tnkb_physical_status',
                default => null,
            };
            if ($statusField) {
                $query->where($statusField, $request->boolean('physical_status'));
            }
        }

        // Filter: Dates and Date Ranges
        $dateFields = [
            'process_date',
            'customer_delivery_date',
            'bpkb_registration_date',
            'bpkb_received_date',
            'stnk_registration_date',
            'stnk_received_date',
            'skpd_payment_date',
            'skpd_received_date',
            'tnkb_received_date',
        ];

        foreach ($dateFields as $field) {
            if ($request->filled($field)) {
                $query->whereDate($field, $request->$field);
            }
            if ($request->filled($field . '_start')) {
                $query->whereDate($field, '>=', $request->input($field . '_start'));
            }
            if ($request->filled($field . '_end')) {
                $query->whereDate($field, '<=', $request->input($field . '_end'));
            }
        }

        // Filter: Processing Status
        if ($request->filled('is_already_processed')) {
            $query->where('is_already_processed', $request->boolean('is_already_processed'));
        }

        // Filter: Search query (across numbers, stnk name, chassis, machine, brand, type, invoice)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('bpkb_number', 'like', "%$search%")
                    ->orWhere('tnkb_number', 'like', "%$search%")
                    ->orWhereHas('vehicleData', function ($q2) use ($search) {
                        $q2->where('stnk_name', 'like', "%$search%")
                            ->orWhere('chassis_number', 'like', "%$search%")
                            ->orWhere('machine_number', 'like', "%$search%")
                            ->orWhere('invoice_number', 'like', "%$search%")
                            ->orWhere('motorcycle_brand', 'like', "%$search%")
                            ->orWhere('motorcycle_type', 'like', "%$search%");
                    });
            });
        }

        return $query;
    }

    private function transformReport($data, string $surat)
    {
        $data->getCollection()->transform(function ($item) use ($surat) {
            // Determine TGL DAFTAR (registration_date) based on document type
            $registrationDate = match ($surat) {
                'bpkb' => $item->bpkb_registration_date,
                'stnk' => $item->stnk_registration_date,
                'skpd' => $item->skpd_payment_date,
                'tnkb' => $item->tnkb_received_date,
                default => null,
            };

            return [
                'id' => $item->id,
                'stnk_name' => $item->vehicleData?->stnk_name,
                "{$surat}_number" => $item->{"{$surat}_number"} ?? null,
                'region' => $item->vehicleData?->region?->name,
                'dealer' => $item->vehicleData?->dealer?->name,
                'vendor' => $item->vendor?->name,
                'tnkb_number' => $item->tnkb_number,
                'vehicle_type' => $item->vehicleData?->vehicle_type,
                'chassis_number' => $item->vehicleData?->chassis_number,
                'machine_number' => $item->vehicleData?->machine_number,
                'registration_date' => $registrationDate,
                "{$surat}_physical_status" => (bool) $item->{"{$surat}_physical_status"},
                'created_at' => $item->created_at,
            ];
        });

        return $data;
    }

    public function getBPKBReport(Request $request)
    {
        try {
            $query = $this->basicQuery('bpkb_number', $request);

            $allowedSort = [
                'id',
                'process_date',
                'customer_delivery_date',
                'bpkb_registration_date',
                'bpkb_received_date',
                'bpkb_physical_status',
                'created_at'
            ];
            $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $data = $query->orderBy($sortBy, $sortOrder)->paginate($request->per_page ?? 10);
            $data = $this->transformReport($data, 'bpkb');

            return $this->responseSuccess($data, 'BPKB Report retrieved successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while retrieving BPKB Report: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve BPKB Report', 500);
        }
    }

    public function getSTNKReport(Request $request)
    {
        try {
            $query = $this->basicQuery('stnk_number', $request);

            $allowedSort = [
                'id',
                'process_date',
                'customer_delivery_date',
                'stnk_registration_date',
                'stnk_received_date',
                'stnk_physical_status',
                'created_at'
            ];
            $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $data = $query->orderBy($sortBy, $sortOrder)->paginate($request->per_page ?? 10);
            $data = $this->transformReport($data, 'stnk');

            return $this->responseSuccess($data, 'STNK Report retrieved successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while retrieving STNK Report: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve STNK Report', 500);
        }
    }

    public function getSKPDReport(Request $request)
    {
        try {
            $query = $this->basicQuery('skpd_number', $request);

            $allowedSort = [
                'id',
                'process_date',
                'customer_delivery_date',
                'skpd_payment_date',
                'skpd_received_date',
                'skpd_physical_status',
                'created_at'
            ];
            $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $data = $query->orderBy($sortBy, $sortOrder)->paginate($request->per_page ?? 10);
            $data = $this->transformReport($data, 'skpd');

            return $this->responseSuccess($data, 'SKPD Report retrieved successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while retrieving SKPD Report: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve SKPD Report', 500);
        }
    }

    public function getTNKBReport(Request $request)
    {
        try {
            $query = $this->basicQuery('tnkb_number', $request);

            $allowedSort = [
                'id',
                'process_date',
                'customer_delivery_date',
                'tnkb_received_date',
                'tnkb_physical_status',
                'created_at'
            ];
            $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $data = $query->orderBy($sortBy, $sortOrder)->paginate($request->per_page ?? 10);
            $data = $this->transformReport($data, 'tnkb');

            return $this->responseSuccess($data, 'TNKB Report retrieved successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while retrieving TNKB Report: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve TNKB Report', 500);
        }
    }

    public function getOutstandingReport(Request $request)
    {
        try {
            $query = VehicleRegistration::query()->with(['vendor:persons.id,persons.name,persons.code', 'vehicleData']);

            // Filter outstanding: bpkb, stnk, skpd, tnkb receipt dates are null
            $query->whereNull('bpkb_received_date')
                ->whereNull('stnk_received_date')
                ->whereNull('skpd_received_date')
                ->whereNull('tnkb_received_date');

            // Filter: Vendor
            if ($request->filled('vendor_id')) {
                $query->whereHas('ditlantasProcess', function ($q) use ($request) {
                    $q->where('vendor_id', $request->vendor_id);
                });
            }

            // Filter: Dealer
            if ($request->filled('dealer_id')) {
                $query->whereHas('vehicleData', function ($q) use ($request) {
                    $q->where('dealer_id', $request->dealer_id);
                });
            }

            // Filter: Search query (across chassis, machine, brand, type, invoice, stnk name, bpkb number, tnkb number)
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('bpkb_number', 'like', "%$search%")
                        ->orWhere('tnkb_number', 'like', "%$search%")
                        ->orWhereHas('vehicleData', function ($q2) use ($search) {
                            $q2->where('stnk_name', 'like', "%$search%")
                                ->orWhere('chassis_number', 'like', "%$search%")
                                ->orWhere('machine_number', 'like', "%$search%")
                                ->orWhere('invoice_number', 'like', "%$search%")
                                ->orWhere('motorcycle_brand', 'like', "%$search%")
                                ->orWhere('motorcycle_type', 'like', "%$search%");
                        });
                });
            }

            $allowedSort = [
                'id',
                'process_date',
                'customer_delivery_date',
                'created_at'
            ];
            $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $data = $query->orderBy($sortBy, $sortOrder)->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'Outstanding Report retrieved successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while retrieving Outstanding Report: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve Outstanding Report', 500);
        }
    }

    protected function getStatsQuery(Request $request)
    {
        $query = VehicleRegistration::query()->with(['vendor:persons.id,persons.name,persons.code', 'vehicleData']);

        // Filter: Vendor
        if ($request->filled('vendor_id')) {
            $query->whereHas('ditlantasProcess', function ($q) use ($request) {
                $q->where('vendor_id', $request->vendor_id);
            });
        }

        // Filter: Dealer
        if ($request->filled('dealer_id')) {
            $query->whereHas('vehicleData', function ($q) use ($request) {
                $q->where('dealer_id', $request->dealer_id);
            });
        }

        // Filter: Physical Status (bpkb_physical_status, stnk_physical_status, etc.)
        foreach (['bpkb_physical_status', 'stnk_physical_status', 'skpd_physical_status', 'tnkb_physical_status'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->boolean($field));
            }
        }

        // Filter: Dates and Date Ranges
        $dateFields = [
            'process_date',
            'customer_delivery_date',
            'bpkb_registration_date',
            'bpkb_received_date',
            'stnk_registration_date',
            'stnk_received_date',
            'skpd_payment_date',
            'skpd_received_date',
            'tnkb_received_date',
        ];

        foreach ($dateFields as $field) {
            if ($request->filled($field)) {
                $query->whereDate($field, $request->$field);
            }
            if ($request->filled($field . '_start')) {
                $query->whereDate($field, '>=', $request->input($field . '_start'));
            }
            if ($request->filled($field . '_end')) {
                $query->whereDate($field, '<=', $request->input($field . '_end'));
            }
        }

        // Filter: Processing Status
        if ($request->filled('is_already_processed')) {
            $query->where('is_already_processed', $request->boolean('is_already_processed'));
        }

        // Filter: Search query
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('bpkb_number', 'like', "%$search%")
                    ->orWhere('tnkb_number', 'like', "%$search%")
                    ->orWhereHas('vehicleData', function ($q2) use ($search) {
                        $q2->where('stnk_name', 'like', "%$search%")
                            ->orWhere('chassis_number', 'like', "%$search%")
                            ->orWhere('machine_number', 'like', "%$search%")
                            ->orWhere('invoice_number', 'like', "%$search%")
                            ->orWhere('motorcycle_brand', 'like', "%$search%")
                            ->orWhere('motorcycle_type', 'like', "%$search%");
                    });
            });
        }

        return $query;
    }

    private function getDocColumn(string $doc)
    {
        return match ($doc) {
            'bpkb' => 'bpkb_registration_date',
            'stnk' => 'stnk_registration_date',
            'skpd' => 'skpd_payment_date',
            'tnkb' => 'tnkb_received_date',
            default => null,
        };
    }

    public function vehicleDocumentStats(Request $request)
    {
        try {
            $documents = ['bpkb', 'stnk', 'skpd', 'tnkb'];

            $submissionStats = [];
            $completeStats = [];
            $processStats = [];
            $pendingStats = [];

            foreach ($documents as $doc) {
                $column = $this->getDocColumn($doc);

                // 1. Submission Stats: customer_delivery_date is null
                $submissionStats[$doc] = $this->getStatsQuery($request)
                    ->whereNull('customer_delivery_date')
                    ->count();

                // 2. Complete Stats: doc date is not null, customer_delivery_date is not null, BBN bill remaining amount is 0
                $completeQuery = $this->getStatsQuery($request)
                    ->whereNotNull($column)
                    ->whereNotNull('customer_delivery_date')
                    ->with('ditlantasProcess.bbnBill.bbnBillBillings.bbnBillBillingItems');

                $completeStats[$doc] = $completeQuery->get()->filter(function ($reg) {
                    $bbnBill = $reg->ditlantasProcess?->bbnBill;
                    if (!$bbnBill) return false;
                    $billings = $bbnBill->bbnBillBillings;
                    if ($billings->isEmpty()) return false;
                    return $billings->contains(function ($billing) {
                        return $billing->getRemainingAmount() === 0;
                    });
                })->count();

                // 3. Process Stats: doc date is null
                $processStats[$doc] = $this->getStatsQuery($request)
                    ->whereNull($column)
                    ->count();

                // 4. Pending Stats: doc date is not null, customer_delivery_date is not null, BBN bill remaining amount is not 0
                $pendingQuery = $this->getStatsQuery($request)
                    ->whereNotNull($column)
                    ->whereNotNull('customer_delivery_date')
                    ->with('ditlantasProcess.bbnBill.bbnBillBillings.bbnBillBillingItems');

                $pendingStats[$doc] = $pendingQuery->get()->filter(function ($reg) {
                    $bbnBill = $reg->ditlantasProcess?->bbnBill;
                    if (!$bbnBill) return false;
                    $billings = $bbnBill->bbnBillBillings;
                    if ($billings->isEmpty()) return false;
                    return $billings->contains(function ($billing) {
                        return $billing->getRemainingAmount() !== 0;
                    });
                })->count();
            }

            return $this->responseSuccess([
                'submission_stats' => $submissionStats,
                'complete_stats' => $completeStats,
                'process_stats' => $processStats,
                'pending_stats' => $pendingStats,
            ], 'Vehicle document statistics retrieved successfully');
        } catch (Exception $err) {
            Log::error('Error while retrieving vehicle document stats: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve vehicle document stats', 500);
        }
    }

    public function VehicleRegistrationStats(Request $request)
    {
        try {
            $baseQuery = $this->getStatsQuery($request);

            // BBNBill query with filters
            $bbnQuery = BBNBill::query();
            if ($request->filled('vendor_id')) {
                $bbnQuery->whereHas('ditlantasProcess', function ($q) use ($request) {
                    $q->where('vendor_id', $request->vendor_id);
                });
            }
            if ($request->filled('dealer_id')) {
                $bbnQuery->whereHas('ditlantasProcess.vehicleRegistrations.vehicleData', function ($q) use ($request) {
                    $q->where('dealer_id', $request->dealer_id);
                });
            }

            $invoice_stats = $bbnQuery->count();

            $bpkb_register_stats = (clone $baseQuery)
                ->whereNotNull('bpkb_registration_date')
                ->whereNotNull('bpkb_received_date')
                ->where('bpkb_physical_status', true)
                ->count();

            $stnk_register_stats = (clone $baseQuery)
                ->whereNotNull('stnk_registration_date')
                ->whereNotNull('stnk_received_date')
                ->where('stnk_physical_status', true)
                ->count();

            $skpd_register_stats = (clone $baseQuery)
                ->whereNotNull('skpd_payment_date')
                ->whereNotNull('skpd_received_date')
                ->where('skpd_physical_status', true)
                ->count();

            $bpkb_outstanding_stats = (clone $baseQuery)
                ->where(function ($q) {
                    $q->whereNull('bpkb_registration_date')
                      ->orWhereNull('bpkb_received_date')
                      ->orWhere('bpkb_physical_status', false);
                })
                ->count();

            $stnk_outstanding_stats = (clone $baseQuery)
                ->where(function ($q) {
                    $q->whereNull('stnk_registration_date')
                      ->orWhereNull('stnk_received_date')
                      ->orWhere('stnk_physical_status', false);
                })
                ->count();

            $skpd_outstanding_stats = (clone $baseQuery)
                ->where(function ($q) {
                    $q->whereNull('skpd_payment_date')
                      ->orWhereNull('skpd_received_date')
                      ->orWhere('skpd_physical_status', false);
                })
                ->count();

            return $this->responseSuccess([
                'invoice_stats' => $invoice_stats,
                'bpkb_register_stats' => $bpkb_register_stats,
                'stnk_register_stats' => $stnk_register_stats,
                'skpd_register_stats' => $skpd_register_stats,
                'bpkb_outstanding_stats' => $bpkb_outstanding_stats,
                'stnk_outstanding_stats' => $stnk_outstanding_stats,
                'skpd_outstanding_stats' => $skpd_outstanding_stats,
            ], 'Vehicle registration statistics retrieved successfully');
        } catch (Exception $err) {
            Log::error('Error while retrieving vehicle registration stats: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve vehicle registration stats', 500);
        }
    }
}
