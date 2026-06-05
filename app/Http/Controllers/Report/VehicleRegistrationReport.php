<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\VehicleRegistration;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VehicleRegistrationReport extends Controller
{
    use ResponseTrait;

    protected function basicQuery(string $dataType) {
        $query = VehicleRegistration::query()->with(['vendor:id,name,code', 'vehicleData:id,uuid,']);

        return $query->whereNotNUll($dataType);
    }

    public function getBPKBReport(Request $request)
    {
        $query = $this->basicQuery('bpkb_number');

        try {
            $sortBy = $request->sort_by ?? 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $data = $query->orderBy($sortBy, $sortOrder)->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'Vehicle Registrations retrieved successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while retrieving Vehicle Registrations: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve Vehicle Registrations', 500);
        }
    }

    public function getSTNKReport(Request $request)
    {
        $query = $this->basicQuery('stnk_number');

        try {
            $sortBy = $request->sort_by ?? 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $data = $query->orderBy($sortBy, $sortOrder)->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'Vehicle Registrations retrieved successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while retrieving Vehicle Registrations: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve Vehicle Registrations', 500);
        }
    }

    public function getSKPDReport(Request $request)
    {
        $query = $this->basicQuery('skpd_number');

        try {
            $sortBy = $request->sort_by ?? 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $data = $query->orderBy($sortBy, $sortOrder)->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'Vehicle Registrations retrieved successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while retrieving Vehicle Registrations: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve Vehicle Registrations', 500);
        }
    }

    public function getTNKBReport(Request $request)
    {
        $query = $this->basicQuery('tnkb_number');

        try {
            $sortBy = $request->sort_by ?? 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $data = $query->orderBy($sortBy, $sortOrder)->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'Vehicle Registrations retrieved successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while retrieving Vehicle Registrations: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve Vehicle Registrations', 500);
        }
    }
}
