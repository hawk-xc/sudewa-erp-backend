<?php

namespace App\Http\Controllers\MasterData;

use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Traits\GlobalCodeNumberTrait;
use App\Http\Controllers\Controller;
use App\Models\UnitTypePriceArchive;
use App\Repositories\AuthRepository;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\UnitTypePriceChangeImport;

/**
 * @group Master Data
 *
 * API for managing unit type price archives.
 */
class MasterUnitTypePriceArchiveController extends Controller
{
    use ResponseTrait, GlobalCodeNumberTrait;

    protected AuthRepository $authRepository;

    public function __construct(AuthRepository $ar)
    {
        $this->middleware(['permission:master-data:list'])->only(['index', 'show', 'export']);
        $this->middleware(['permission:master-data:create'])->only('store', 'import');
        $this->middleware(['permission:master-data:edit'])->only('update');
        $this->middleware(['permission:master-data:delete'])->only(['destroy']);

        $this->authRepository = $ar;
    }

    /**
     * List all unit type price archives.
     */
    public function index(Request $request)
    {
        try {
            $query = UnitTypePriceArchive::with(['unitType:id,name', 'user:id,name']);

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where('note', 'like', "%{$search}%");
            }

            if ($request->filled('unit_type_id')) {
                $query->where('unit_type_id', $request->unit_type_id);
            }

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }
            
            if ($request->filled('min_buy_price')) {
                $query->where('buy_price', '>=', $request->min_buy_price);
            }

            if ($request->filled('max_buy_price')) {
                $query->where('buy_price', '<=', $request->max_buy_price);
            }

            if ($request->filled('min_sell_price')) {
                $query->where('sell_price', '>=', $request->min_sell_price);
            }

            if ($request->filled('max_sell_price')) {
                $query->where('sell_price', '<=', $request->max_sell_price);
            }

            $unitTypePriceArchives = $request->filled('per_page') ? $query->paginate($request->per_page) : $query->get();

            return $this->responseSuccess($unitTypePriceArchives, 'Unit Type Price Archives retrieved successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while trying get Unit Type Price Archives : ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Internal Server Error', 500);
        }
    }

    /**
     * Get unit type price archive details.
     */
    public function show(int $id)
    {
        try {
            $unitTypePriceArchive = UnitTypePriceArchive::with(['unitType', 'user'])->findOrFail($id);

            return $this->responseSuccess($unitTypePriceArchive, 'Unit Type Price Archive retrieved successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while trying get Unit Type Price Archive : ' . $err->getMessage());

            return $this->responseError('The requested resource could not be found.', 'Resource Not Found', 404);
        }
    }

    /**
     * Store a new unit type price archive.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'unit_type_id' => 'required|exists:unit_types,id',
            'user_id' => 'required|exists:users,id',
            'buy_price' => 'required|integer',
            'sell_price' => 'required|integer',
            'note' => 'nullable|string',
        ]);

        try {
            $unitTypePriceArchive = DB::transaction(function () use ($validated) {
                $unitTypePriceArchive = UnitTypePriceArchive::create($validated);

                return $unitTypePriceArchive;
            });

            return $this->responseSuccess($unitTypePriceArchive->load(['unitType', 'user']), 'Unit Type Price Archive created successfully', 201);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while trying create Unit Type Price Archive : ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Failed to create Unit Type Price Archive', 500);
        }
    }

    /**
     * Update a unit type price archive.
     */
    public function update(Request $request, int $id)
    {
        $unitTypePriceArchive = UnitTypePriceArchive::findOrFail($id);

        $validated = $request->validate([
            'unit_type_id' => 'sometimes|exists:unit_types,id',
            'user_id' => 'sometimes|exists:users,id',
            'buy_price' => 'sometimes|integer',
            'sell_price' => 'sometimes|integer',
            'note' => 'nullable|string',
        ]);

        try {
            DB::transaction(function () use ($validated, $unitTypePriceArchive) {
                $unitTypePriceArchive->update($validated);
            });

            return $this->responseSuccess($unitTypePriceArchive->fresh(), 'Unit Type Price Archive updated successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while trying update Unit Type Price Archive : ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Internal Server Error', 500);
        }
    }

    /**
     * Delete a unit type price archive.
     */
    public function destroy(int $id)
    {
        try {
            $unitTypePriceArchive = UnitTypePriceArchive::findOrFail($id);
            $unitTypePriceArchive->delete();

            return $this->responseSuccess(null, 'Unit Type Price Archive deleted successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while trying delete Unit Type Price Archive : ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Internal Server Error', 500);
        }
    }

    /**
     * Import unit type price archives from Excel.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        try {
            Excel::import(new UnitTypePriceChangeImport(), $request->file('file'));

            return $this->responseSuccess(null, 'Unit Type Price Archive imported successfully', 201);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Unit Type Price Archive import error', [
                'message' => $err->getMessage(),
            ]);

            return $this->responseError($err->getMessage(), 'Unit Type Price Archive import error', 500);
        }
    }
}
