<?php

namespace App\Http\Controllers\MasterData;

use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Traits\GlobalCodeNumberTrait;
use App\Exports\PersonExport;
use App\Http\Controllers\Controller;
use App\Imports\PersonImport;
use App\Models\Person;
use App\Repositories\AuthRepository;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @group Master Data
 *
 * API for managing suppliers.
 */
class MasterSupplierController extends Controller
{
    use ResponseTrait, GlobalCodeNumberTrait;

    protected AuthRepository $authRepository;

    // projection
    protected array $personTable;

    /**
     * AuthController constructor.
     */
    public function __construct(AuthRepository $ar)
    {
        $this->middleware(['permission:master-data:list'])->only(['index', 'show', 'export']);
        $this->middleware(['permission:master-data:create'])->only('store', 'import');
        $this->middleware(['permission:master-data:edit'])->only('update');
        $this->middleware(['permission:master-data:delete'])->only(['destroy']);

        $this->authRepository = $ar;

        $this->personTable = ['id', 'uuid', 'code', 'type', 'name', 'address', 'npwp', 'phone', 'created_at', 'pic_name'];
    }

    /**
     * List all suppliers.
     */
    public function index(Request $request)
    {
        $query = Person::query();

        $query->select($this->personTable)->where('type', 'supplier');

        try {
            if ($request->filled('search')) {

                $search = $request->search;
                $caseSensitive = $request->boolean('case_sensitive');

                $query->where(function ($q) use ($search, $caseSensitive) {

                    if ($caseSensitive) {
                        $q->where('name', 'LIKE BINARY', "%$search%")
                            ->orWhere('code', 'LIKE BINARY', "%$search%")
                            ->orWhere('phone', 'LIKE BINARY', "%$search%")
                            ->orWhere('npwp', 'LIKE BINARY', "%$search%");
                    } else {
                        $q->where('name', 'like', "%$search%")
                            ->orWhere('code', 'like', "%$search%")
                            ->orWhere('phone', 'like', "%$search%")
                            ->orWhere('npwp', 'like', "%$search%");
                    }

                });
            }

            foreach ($this->personTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $allowedSort = $this->personTable;

            $sortBy = in_array($request->sort_by, $allowedSort)
                ? $request->sort_by
                : 'id';

            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->per_page ?? 10;

            $data = $query->paginate($perPage);

            return $this->responseSuccess($data, 'Supplier list retrieved successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error While retrieved Supplier data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Supplier list retrieved Failed', 500);
        }
    }

    /**
     * Get supplier details.
     */
    public function show(string $id)
    {
        try {
            $person = Person::where('type', 'supplier')->where('id', $id)->select($this->personTable)->first();

            if (! $person) {
                return $this->responseError('The requested resource could not be found.', 'Resource Not Found', 404);
            }

            return $this->responseSuccess($person, 'Supplier retrieved successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error While retrieved Supplier data : '.$err->getMessage());

            return $this->responseError('The requested resource could not be found.', 'Resource Not Found', 404);
        }
    }

    /**
     * Store a new supplier.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'nullable|integer|exists:companies,id',
            'name' => 'required|string|max:249',
            'address' => 'sometimes|string|max:249',
            'phone' => 'sometimes|string|max:249',
            'npwp' => 'sometimes|string',
            'pic_name' => 'nullable|string',
        ]);

        try {
            $person = DB::transaction(function () use ($validated) {
                $validated['type'] = 'supplier';
                $companySlug = '';
                if (!empty($validated['company_id'])) {
                    $companySlug = \App\Models\Company::where('id', $validated['company_id'])->value('slug') ?? '';
                }
                $validated['code'] = $this->code($companySlug, 'supplier');

                return Person::create($validated);
            });

            return $this->responseSuccess($person, 'Supplier created successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while trying create Supplier Data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Error while trying create Supplier Data', 500);
        }
    }

    /**
     * Update a supplier.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'company_id' => 'nullable|integer|exists:companies,id',
            'name' => 'sometimes|string|max:249',
            'address' => 'sometimes|string|max:249',
            'phone' => 'sometimes|string|max:249',
            'npwp' => 'sometimes|string',
            'pic_name' => 'nullable|string',
        ]);

        try {
            $data = array_filter($request->only(['name', 'address', 'phone', 'pic_name', 'npwp']), fn ($value) => ! is_null($value) && $value !== '');

            if (empty($data)) {
                return $this->responseError(null, 'No data provided to update', 422);
            }

            $person = DB::transaction(function () use ($id, $data) {
                $person = Person::findOrFail($id);

                $person->update($data);

                return $person->fresh();
            });

            return $this->responseSuccess($person, 'Supplier Update Successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while trying update Supplier data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Error while trying update Supplier data', 500);
        }
    }

    /**
     * Delete a supplier.
     */
    public function destroy(string $id)
    {
        try {
            $person = Person::where('type', 'supplier')->findOrFail($id);
            $person->delete();

            return $this->responseSuccess([], 'Supplier Deleted Successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while trying delete Supplier data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Supplier Deleted Failed');
        }
    }

    /**
     * Import suppliers from Excel.
     */
    public function import(Request $request, string $id)
    {
        if ($id == null || !is_numeric($id)) {
            return $this->responseError(null, 'Company id cannot null', 404);
        }
        
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        try {
            Excel::import(new PersonImport((string) 'supplier', (int) $id), $request->file('file'));

            return $this->responseSuccess(null, 'Person Supplier imported successfully', 201);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Person Supplier import error', [
                'message' => $err->getMessage(),
            ]);

            return $this->responseError($err->getMessage(), 'Person Supplier import error', 500);
        }
    }

    /**
     * Export suppliers to Excel.
     */
    public function export(Request $request)
    {
        try {
            return Excel::download(
                new PersonExport($request, $this->personTable, 'supplier'),
                'wajira_supplier_data.xlsx'
            );  
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error export supplier : '.$err->getMessage());
    
            return $this->responseError(
                $err->getMessage(),
                'Supplier export failed',
                500
            );
        }
    }
}
