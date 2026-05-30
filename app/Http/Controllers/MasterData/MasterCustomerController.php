<?php

namespace App\Http\Controllers\MasterData;

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
 * API for managing customers.
 */
class MasterCustomerController extends Controller
{
    use ResponseTrait, GlobalCodeNumberTrait;

    protected AuthRepository $authRepository;

    // projection
    protected $personTable;

    public function __construct(AuthRepository $ar)
    {
        $this->middleware(['permission:master-data:list'])->only(['index', 'show', 'export']);
        $this->middleware(['permission:master-data:create'])->only('store', 'import');
        $this->middleware(['permission:master-data:edit'])->only('update');
        $this->middleware(['permission:master-data:delete'])->only(['destroy']);

        $this->authRepository = $ar;

        $this->personTable = ['id', 'uuid', 'pic_name', 'code', 'type', 'name', 'address', 'npwp', 'phone', 'identity_number', 'drive_license_identity_number', 'image', 'map_link', 'social_media_1_link', 'social_media_2_link', 'social_media_3_link', 'social_media_4_link', 'website_link', 'created_at'];
    }

    /**
     * List all customers.
     */
    public function index(Request $request)
    {
        $query = Person::query();

        $query->select($this->personTable)->where('type', 'customer');

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

            return $this->responseSuccess($data, 'Customer list retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error While retrieved Customer data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Customer list retrieved Failed', 500);
        }
    }

    /**
     * Get customer details.
     */
    public function show(string $id)
    {
        try {
            $person = Person::where('type', 'customer')->where('id', $id)->select($this->personTable)->first();

            if (! $person) {
                return $this->responseError('The requested resource could not be found.', 'Resource Not Found', 404);
            }

            return $this->responseSuccess($person, 'Customer retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error While retrieved Customer data : '.$err->getMessage());

            return $this->responseError('The requested resource could not be found.', 'Resource Not Found', 404);
        }
    }

    /**
     * Store a new customer.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|integer|exists:companies,id',
            'name' => 'required|string|max:249',
            'address' => 'sometimes|string|max:249',
            'phone' => 'sometimes|string|max:249',
            'npwp' => 'sometimes|string',
            'pic_name' => 'nullable|string',
            'map_link' => 'nullable|string'
        ]);

        try {
            $person = DB::transaction(function () use ($validated) {
                $companySlug = \App\Models\Company::where('id', $validated['company_id'])->value('slug') ?? '';
                $validated['code'] = $this->code($companySlug, 'customer');
                $validated['type'] = 'customer';

                return Person::create($validated);
            });

            return $this->responseSuccess($person, 'Customer created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error while trying create Person Data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Error while trying create Person Data', 500);
        }
    }

    /**
     * Update a customer.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'company_id' => 'sometimes|integer|exists:companies,id',
            'name' => 'sometimes|string|max:249',
            'address' => 'sometimes|string|max:249',
            'phone' => 'sometimes|string|max:249',
            'npwp' => 'sometimes|string',
            'pic_name' => 'sometimes|string',
            'map_link' => 'sometimes|string'
        ]);

        try {
            $data = array_filter($request->only(['company_id', 'pic_name', 'name', 'address', 'phone', 'npwp', 'map_link']), fn ($value) => ! is_null($value) && $value !== '');

            if (empty($data)) {
                return $this->responseError(null, 'No data provided to update', 422);
            }

            $person = DB::transaction(function () use ($id, $data) {
                $person = Person::findOrFail($id);

                $person->update($data);

                return $person->fresh();
            });

            return $this->responseSuccess($person, 'Customer Update Successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying update Person data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Error while trying update Person data', 500);
        }
    }

    /**
     * Delete a customer.
     */
    public function destroy(string $id)
    {
        try {
            $person = Person::where('type', 'customer')->findOrFail($id);
            $person->delete();

            return $this->responseSuccess([], 'Customer Deleted Successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying delete Customer data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Customer Deleted Failed');
        }
    }

    /**
     * Import customers from Excel.
     */
    public function import(Request $request, string $id)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        try {
            Excel::import(new PersonImport((string) 'customer', (int) $id), $request->file('file'));

            return $this->responseSuccess(null, 'Person Customer imported successfully', 201);
        } catch (Exception $err) {
            Log::error('Person Customer import error', [
                'message' => $err->getMessage(),
            ]);

            return $this->responseError($err->getMessage(), 'Person Customer import error', 500);
        }
    }

    /**
     * Export customers to Excel.
     */
    public function export(Request $request)
    {
        try {
            return Excel::download(
                new PersonExport($request, $this->personTable, 'customer'),
                'wajira_customer_data.xlsx'
            );
        } catch (Exception $err) {
            Log::error('Error export customer : '.$err->getMessage());
    
            return $this->responseError(
                $err->getMessage(),
                'Customer export failed',
                500
            );
        }
    }
}
