<?php

namespace App\Http\Controllers\MasterData;

use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Traits\GlobalCodeNumberTrait;
use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\Tarif;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\TarifImport;
use App\Exports\TarifExport;

/**
 * @group Master Data
 *
 * API for managing tariffs.
 */
class MasterTarifController extends Controller
{
    use ResponseTrait, GlobalCodeNumberTrait;

    protected array $tarifTable;

    public function __construct()
    {
        $this->middleware(['permission:master-data:list'])->only(['index', 'show', 'export']);
        $this->middleware(['permission:master-data:create'])->only('store', 'import');
        $this->middleware(['permission:master-data:edit'])->only('update');
        $this->middleware(['permission:master-data:delete'])->only(['destroy']);

        $this->tarifTable = [
            'id', 
            'uuid', 
            'loading_in', 
            'loading_out', 
            'distance', 
            'uj_towing', 
            'uj_cdd', 
            'uj_fuso', 
            'inv_cdd', 
            'inv_fuso', 
            'is_active', 
            'created_at',
            'updated_at'
        ];
    }

    /**
     * List all tariffs.
     */
    public function index(Request $request)
    {
        $query = Tarif::query();

        try {
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('loading_in', 'like', "%$search%")
                        ->orWhere('loading_out', 'like', "%$search%");
                });
            }

            foreach ($this->tarifTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $allowedSort = $this->tarifTable;
            $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $data = $query->orderBy($sortBy, $sortOrder)->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'Tarif list retrieved successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while retrieving Tarif data: '.$err->getMessage());
            return $this->responseError($err->getMessage(), 'Tarif list retrieved Failed', 500);
        }
    }

    /**
     * Get tariff details.
     */
    public function show(string $id)
    {
        try {
            $tarif = Tarif::find($id);

            if (!$tarif) {
                return $this->responseError('The requested resource could not be found.', 'Resource Not Found', 404);
            }

            return $this->responseSuccess($tarif, 'Tarif retrieved successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while retrieving Tarif data: '.$err->getMessage());
            return $this->responseError('The requested resource could not be found.', 'Resource Not Found', 404);
        }
    }

    /**
     * Store a new tariff.
     */
    public function store(Request $request)
    {
        $request->merge(['is_active' => $request->is_active === 'true']);

        $validated = $request->validate([
            'loading_in' => 'required|string|max:249',
            'loading_out' => 'required|string|max:249',
            'distance' => 'required|integer',
            'uj_towing' => 'nullable|integer',
            'uj_cdd' => 'nullable|integer',
            'uj_fuso' => 'nullable|integer',
            'inv_cdd' => 'nullable|integer',
            'inv_fuso' => 'nullable|integer',
            'inv_towing' => 'nullable|integer',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $tarif = DB::transaction(function () use ($validated) {
                return Tarif::create($validated);
            });

            return $this->responseSuccess($tarif, 'Tarif created successfully', 201);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while creating Tarif: '.$err->getMessage());
            return $this->responseError($err->getMessage(), 'Error while trying to create Tarif data', 500);
        }
    }

    /**
     * Update a tariff.
     */
    public function update(Request $request, string $id)
    {
        $request->merge(['is_active' => $request->is_active === 'true']);

        $request->validate([
            'loading_in' => 'sometimes|string|max:249',
            'loading_out' => 'sometimes|string|max:249',
            'distance' => 'sometimes|integer',
            'uj_towing' => 'nullable|integer',
            'uj_cdd' => 'nullable|integer',
            'uj_fuso' => 'nullable|integer',
            'inv_cdd' => 'nullable|integer',
            'inv_fuso' => 'nullable|integer',
            'inv_towing' => 'nullable|integer',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $data = $request->only([
                'loading_in', 
                'loading_out', 
                'distance', 
                'uj_towing', 
                'uj_cdd', 
                'uj_fuso', 
                'inv_cdd', 
                'inv_fuso',
                'inv_towing', 
                'is_active'
            ]);

            $tarif = DB::transaction(function () use ($id, $data) {
                $tarif = Tarif::findOrFail($id);
                $tarif->update($data);
                return $tarif->fresh();
            });

            return $this->responseSuccess($tarif, 'Tarif updated successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while updating Tarif: '.$err->getMessage());
            return $this->responseError($err->getMessage(), 'Error while trying to update Tarif data', 500);
        }
    }

    /**
     * Delete a tariff.
     */
    public function destroy(string $id)
    {
        try {
            $tarif = Tarif::findOrFail($id);
            $tarif->delete();

            return $this->responseSuccess([], 'Tarif deleted successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while deleting Tarif: '.$err->getMessage());
            return $this->responseError($err->getMessage(), 'Tarif deletion failed', 500);
        }
    }

    /**
     * Import tariffs from Excel.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        try {
            Excel::import(new TarifImport(), $request->file('file'));

            return $this->responseSuccess(null, 'Tarif imported successfully', 201);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Tarif import error', [
                'message' => $err->getMessage(),
            ]);

            return $this->responseError($err->getMessage(), 'Tarif import error', 500);
        }
    }
    
    /**
     * Export tariffs to Excel.
     */
    public function export(Request $request)
    {
        try {
            return Excel::download(
                new TarifExport($request, $this->tarifTable),
                'wajira_tarif_data.xlsx'
            );
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error export tarif: '.$err->getMessage());

            return $this->responseError(
                $err->getMessage(),
                'Tarif export failed',
                500
            );
        }
    }
}
