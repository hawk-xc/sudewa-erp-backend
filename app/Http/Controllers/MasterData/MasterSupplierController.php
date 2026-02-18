<?php

namespace App\Http\Controllers\MasterData;

use Exception;
use App\Models\Person;
use App\Traits\PersonTrait;
use Illuminate\Http\Request;
use App\Traits\ResponseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Repositories\AuthRepository;

class MasterSupplierController extends Controller
{
    use ResponseTrait, PersonTrait;

    /**
     * @var AuthRepository
     */
    protected AuthRepository $authRepository;
    
    // projection
    protected $personTable;

    /**
     * AuthController constructor.
     */
    public function __construct(AuthRepository $ar)
    {
        $this->middleware(['permission:master-data:list'])->only(['index']);
        $this->middleware(['permission:master-data:create'])->only('store');
        $this->middleware(['permission:master-data:edit'])->only('update', 'activateUser', 'deactivateUser');
        $this->middleware(['permission:master-data:delete'])->only(['destroy']);

        $this->authRepository = $ar;

        $this->personTable = ['id', 'uuid', 'code', 'type', 'name', 'address', 'npwp', 'phone'];
    }

    public function index(Request $request) {
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

        return $this->responseSuccess($data, "Supplier list retrieved successfully", 200);
    } catch (Exception $err) {
        Log::error("Error While retrieved Supplier data : " . $err->getMessage());
        return $this->responseError(null, "Supplier list retrieved Failed", 500);
    }
    }

    public function show(string $id) {
        try {
            $person = Person::where('type', 'supplier')->where('id', $id)->select($this->personTable)->first();

            if (!$person) {
                return $this->responseError(null, "Supplier not found", 404);
            }

            return $this->responseSuccess($person, "Supplier retrieved successfully", 200);
        } catch (Exception $err) {
            Log::error("Error While retrieved Supplier data : " . $err->getMessage());
            return $this->responseError(null, "Supplier retrieved Failed", 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:249',
            'address' => 'sometimes|string|max:249',
            'phone' => 'sometimes|string|max:249',
            'user_id' => 'sometimes|integer|exists:users,id',
            'npwp' => 'sometimes|string',
        ]);

        try {
            $person = new Person();

            DB::transaction(function () use ($person, $request) {
                $person->user_id = $request->user_id;
                $person->type = 'supplier';
                $person->code = $this->generateCode('supplier');
                $person->name = $request->name;
                $person->address = $request->address;
                $person->phone = $request->phone;
                $person->npwp = $request->npwp;
                $person->save();
            });

            return $this->responseSuccess($person, 'Supplier created successfully');
        } catch (Exception $err) {
            Log::error('Error while trying create Supplier Data : ' . $err->getMessage());

            return $this->responseError(null, 'Error while trying create Supplier Data', 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'sometimes|string|max:249',
            'address' => 'sometimes|string|max:249',
            'phone' => 'sometimes|string|max:249',
            'user_id' => 'sometimes|integer|exists:users,id',
            'npwp' => 'sometimes|string',
        ]);

        try {
            $person = Person::findOrFail($id);

            $data = array_filter($request->only(['name', 'address', 'phone', 'user_id', 'npwp']), fn($value) => !is_null($value) && $value !== '');

            if (empty($data)) {
                return $this->responseError(null, 'No data provided to update', 422);
            }

            DB::transaction(function () use ($person, $data) {
                $person->update($data);
            });

            return $this->responseSuccess($person, 'Supplier Update Successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying update Supplier data : ' . $err->getMessage());

            return $this->responseError(null, 'Error while trying update Supplier data', 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $person = Person::where('type', 'supplier')->findOrFail($id);
            $person->delete();

            return $this->responseSuccess([], 'Supplier Deleted Successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying delete Supplier data : ' . $err->getMessage());
            return $this->responseError(null, 'Supplier Deleted Failed');
        }
    }
}

