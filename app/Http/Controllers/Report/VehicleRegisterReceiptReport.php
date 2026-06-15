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
}
