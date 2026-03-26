<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Imports\PersonImport;
use App\Models\Person;
use App\Repositories\AuthRepository;
use App\Traits\PersonTrait;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class MasterVendorController extends Controller
{
    use PersonTrait, ResponseTrait;

    protected AuthRepository $authRepository;

    // projection
    protected $personTable;

    public function __construct(AuthRepository $ar)
    {
        $this->middleware(['permission:master-data:list'])->only(['index', 'show']);
        $this->middleware(['permission:master-data:create'])->only('store');
        $this->middleware(['permission:master-data:edit'])->only('update');
        $this->middleware(['permission:master-data:delete'])->only(['destroy']);

        $this->authRepository = $ar;

        $this->personTable = ['id', 'uuid', 'pic_name', 'code', 'type', 'name', 'address', 'npwp', 'phone', 'created_at'];
    }

    public function index(Request $request)
    {
        $query = Person::query();

        $query->select($this->personTable)->where('type', 'vendor');

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

            return $this->responseSuccess($data, 'Vendor list retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error While retrieved Vendor data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Vendor list retrieved Failed', 500);
        }
    }

    public function show(string $id)
    {
        try {
            $person = Person::where('type', 'vendor')->where('id', $id)->select($this->personTable)->first();

            if (! $person) {
                return $this->responseError(null, 'Vendor not found', 404);
            }

            return $this->responseSuccess($person, 'Vendor retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error While retrieved Vendor data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Vendor retrieved Failed', 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|integer|exists:companies,id',
            'name' => 'required|string|max:249',
            'address' => 'sometimes|string|max:249',
            'phone' => 'sometimes|string|max:249',
            'npwp' => 'sometimes|string',
            'pic_name' => 'nullable|string',
        ]);

        try {
            $person = DB::transaction(function () use ($validated) {
                $validated['code'] = $this->generateCode('vendor');
                $validated['type'] = 'vendor';

                return Person::create($validated);
            });

            return $this->responseSuccess($person, 'Vendor created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error while trying create Person Data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Error while trying create Person Data', 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'company_id' => 'sometimes|integer|exists:companies,id',
            'name' => 'sometimes|string|max:249',
            'address' => 'sometimes|string|max:249',
            'phone' => 'sometimes|string|max:249',
            'npwp' => 'sometimes|string',
            'pic_name' => 'sometimes|string',
        ]);

        try {
            $data = array_filter($request->only(['company_id', 'pic_name', 'name', 'address', 'phone', 'npwp']), fn ($value) => ! is_null($value) && $value !== '');

            if (empty($data)) {
                return $this->responseError(null, 'No data provided to update', 422);
            }

            $person = DB::transaction(function () use ($id, $data) {
                $person = Person::findOrFail($id);

                $person->update($data);

                return $person->fresh();
            });

            return $this->responseSuccess($person, 'Vendor Update Successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying update Person data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Error while trying update Person data', 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $person = Person::where('type', 'vendor')->findOrFail($id);
            $person->delete();

            return $this->responseSuccess([], 'Vendor Deleted Successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying delete Vendor data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Vendor Deleted Failed');
        }
    }

    public function import(Request $request, string $id)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        try {
            Excel::import(new PersonImport((string) 'vendor', (int) $id), $request->file('file'));

            return $this->responseSuccess(null, 'Person Vendor imported successfully', 201);
        } catch (Exception $err) {
            Log::error('Person Vendor import error', [
                'message' => $err->getMessage(),
            ]);

            return $this->responseError(null, $err->getMessage(), 500);
        }
    }
}
