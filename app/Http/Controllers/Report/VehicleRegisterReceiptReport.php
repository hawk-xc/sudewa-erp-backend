<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\VehicleRegistration;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VehicleRegisterReceiptReport extends Controller
{
    use ResponseTrait;

    public function index(Request $request)
    {
        try {
            $query = VehicleRegistration::query()->with(['vendor:persons.id,persons.name,persons.code', 'vehicleData:id,uuid,dealer_id,region_id,chassis_number,machine_number,stnk_name,motorcycle_brand,motorcycle_type,invoice_receive_date,invoice_date,invoice_number,motorcycle_type']);

            // Filter: invoice_receive_date is not null and input filters
            $query->whereHas('vehicleData', function ($q) use ($request) {
                $q->whereNotNull('customer_delivery_date');

                if ($request->filled('invoice_receive_date')) {
                    $q->whereDate('invoice_receive_date', $request->invoice_receive_date);
                }
                if ($request->filled('invoice_receive_date_start')) {
                    $q->whereDate('invoice_receive_date', '>=', $request->input('invoice_receive_date_start'));
                }
                if ($request->filled('invoice_receive_date_end')) {
                    $q->whereDate('invoice_receive_date', '<=', $request->input('invoice_receive_date_end'));
                }
            });

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
                'id', 'process_date', 'customer_delivery_date', 'created_at'
            ];
            $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $data = $query->orderBy($sortBy, $sortOrder)->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'Vehicle Register Receipt Report retrieved successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while retrieving Vehicle Register Receipt Report: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve Vehicle Register Receipt Report', 500);
        }
    }

    protected function basicQuery(string $dataType, Request $request)
    {
        $query = VehicleRegistration::query()->with([
            'vendor:persons.id,persons.name,persons.code',
            'vehicleData.region',
            'vehicleData.dealer'
        ]);

        $column = match ($dataType) {
            'bpkb' => 'bpkb_registration_date',
            'stnk' => 'stnk_registration_date',
            'skpd' => 'skpd_payment_date',
            'tnkb' => 'tnkb_received_date',
            default => null,
        };

        if ($column) {
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
                'bpkb' => 'bpkb_physical_status',
                'stnk' => 'stnk_physical_status',
                'skpd' => 'skpd_physical_status',
                'tnkb' => 'tnkb_physical_status',
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

    public function getBPKBReceipt(Request $request)
    {
        try {
            $query = $this->basicQuery('bpkb', $request);

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

            return $this->responseSuccess($data, 'BPKB Receipt Report retrieved successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while retrieving BPKB Receipt Report: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve BPKB Receipt Report', 500);
        }
    }

    public function getSTNKReceipt(Request $request)
    {
        try {
            $query = $this->basicQuery('stnk', $request);

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

            return $this->responseSuccess($data, 'STNK Receipt Report retrieved successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while retrieving STNK Receipt Report: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve STNK Receipt Report', 500);
        }
    }

    public function getSKPDReceipt(Request $request)
    {
        try {
            $query = $this->basicQuery('skpd', $request);

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

            return $this->responseSuccess($data, 'SKPD Receipt Report retrieved successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while retrieving SKPD Receipt Report: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve SKPD Receipt Report', 500);
        }
    }

    public function getTNKBReceipt(Request $request)
    {
        try {
            $query = $this->basicQuery('tnkb', $request);

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

            return $this->responseSuccess($data, 'TNKB Receipt Report retrieved successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while retrieving TNKB Receipt Report: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve TNKB Receipt Report', 500);
        }
    }
}
