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

    protected function basicQuery(string $dataType, Request $request) {
        $query = VehicleRegistration::query()->with(['vendor:id,name,code', 'vehicleData']);

        // Map placeholder fields to actual columns for Receipt Report (must have received date not null)
        $column = match ($dataType) {
            'bpkb_number' => 'bpkb_received_date',
            'stnk_number' => 'stnk_received_date',
            'skpd_number' => 'skpd_received_date',
            'tnkb_number' => 'tnkb_received_date',
            default => $dataType,
        };
        $query->whereNotNull($column);

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

    public function getBPKBReceipt(Request $request)
    {
        try {
            $query = $this->basicQuery('bpkb_number', $request);

            $allowedSort = [
                'id', 'process_date', 'customer_delivery_date', 'bpkb_registration_date',
                'bpkb_received_date', 'bpkb_physical_status', 'created_at'
            ];
            $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $data = $query->orderBy($sortBy, $sortOrder)->paginate($request->per_page ?? 10);

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
            $query = $this->basicQuery('stnk_number', $request);

            $allowedSort = [
                'id', 'process_date', 'customer_delivery_date', 'stnk_registration_date',
                'stnk_received_date', 'stnk_physical_status', 'created_at'
            ];
            $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $data = $query->orderBy($sortBy, $sortOrder)->paginate($request->per_page ?? 10);

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
            $query = $this->basicQuery('skpd_number', $request);

            $allowedSort = [
                'id', 'process_date', 'customer_delivery_date', 'skpd_payment_date',
                'skpd_received_date', 'skpd_physical_status', 'created_at'
            ];
            $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $data = $query->orderBy($sortBy, $sortOrder)->paginate($request->per_page ?? 10);

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
            $query = $this->basicQuery('tnkb_number', $request);

            $allowedSort = [
                'id', 'process_date', 'customer_delivery_date', 'tnkb_received_date',
                'tnkb_physical_status', 'created_at'
            ];
            $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $data = $query->orderBy($sortBy, $sortOrder)->paginate($request->per_page ?? 10);

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
