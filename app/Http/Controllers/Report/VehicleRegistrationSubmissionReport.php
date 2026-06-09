<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\VehicleRegistration;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VehicleRegistrationSubmissionReport extends Controller
{
    use ResponseTrait;

    public function index(Request $request)
    {
        try {
            $query = VehicleRegistration::query()->with(['vendor:id,name,code', 'vehicleData']);

            // Filter: customer_delivery_date is null or not null (defaults to null)
            if ($request->has('is_delivered')) {
                if ($request->boolean('is_delivered')) {
                    $query->whereNotNull('customer_delivery_date');
                } else {
                    $query->whereNull('customer_delivery_date');
                }
            } else {
                $query->whereNull('customer_delivery_date');
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

            // Sorting
            $allowedSort = [
                'id', 'process_date', 'customer_delivery_date', 'bpkb_registration_date',
                'bpkb_received_date', 'stnk_registration_date', 'stnk_received_date',
                'skpd_payment_date', 'skpd_received_date', 'tnkb_received_date', 'created_at'
            ];
            $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $data = $query->orderBy($sortBy, $sortOrder)->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'Vehicle Registration Submission Report retrieved successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while retrieving Vehicle Registration Submission Report: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve Vehicle Registration Submission Report', 500);
        }
    }
}
