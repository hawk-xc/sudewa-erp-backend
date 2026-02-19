<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Repositories\AuthRepository;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MasterAccountController extends Controller
{
    use ResponseTrait;

    protected AuthRepository $authRepository;

    // projection
    protected $accountTable;

    /**
     * AuthController constructor.
     */
    public function __construct(AuthRepository $ar)
    {
        $this->middleware(['permission:master-data:list'])->only(['index', 'show']);
        $this->middleware(['permission:master-data:create'])->only('store');
        $this->middleware(['permission:master-data:edit'])->only('update');
        $this->middleware(['permission:master-data:delete'])->only(['destroy']);

        $this->authRepository = $ar;

        $this->accountTable = ['id', 'uuid', 'code', 'group_code', 'name', 'description', 'type', 'created_at'];
    }

    public function index(Request $request)
    {
        try {
            $query = Account::query();

            $query->select($this->accountTable);

            if ($request->filled('search')) {
                $search = $request->search;
                $caseSensitive = $request->boolean('case_sensitive');

                $query->where(function ($q) use ($search, $caseSensitive) {
                    if ($caseSensitive) {
                        $q->where('name', 'LIKE BINARY', "%$search%")
                            ->orWhere('code', 'LIKE BINARY', "%$search%")
                            ->orWhere('group_code', 'LIKE BINARY', "%$search%")
                            ->orWhere('description', 'LIKE BINARY', "%$search%")
                            ->orWhere('type', 'LIKE BINARY', "%$search%");
                    } else {
                        $q->where('name', 'like', "%$search%")
                            ->orWhere('code', 'like', "%$search%")
                            ->orWhere('group_code', 'like', "%$search%")
                            ->orWhere('description', 'like', "%$search%")
                            ->orWhere('type', 'like', "%$search%");
                    }
                });
            }

            foreach ($this->accountTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $allowedSort = $this->accountTable;

            $sortBy = in_array($request->sort_by, $allowedSort)
                ? $request->sort_by
                : 'id';

            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->per_page ?? 10;

            $data = $query->paginate($perPage);

            return $this->responseSuccess($data, 'Account list retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error While retrieved Account data : '.$err->getMessage());

            return $this->responseError(null, 'Account list retrieved Failed', 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'code' => 'required|string|max:50|unique:accounts,code',
                'group_code' => 'nullable|string|max:50',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'type' => 'required|string|max:50',
            ]);

            $account = DB::transaction(function () use ($validated) {
                return Account::create($validated);
            });

            return $this->responseSuccess(
                $account->fresh(),
                'Account created successfully',
                201
            );

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->responseError($e->errors(), 'Validation failed', 422);
        } catch (\Exception $err) {
            Log::error('Error While storing Account data : '.$err->getMessage());

            return $this->responseError(null, 'Account creation failed', 500);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $account = Account::findOrFail($id);

            $validated = $request->validate([
                'code' => 'sometimes|required|string|max:50|unique:accounts,code,'.$id,
                'group_code' => 'nullable|string|max:50',
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'type' => 'sometimes|required|string|max:50',
            ]);

            DB::transaction(function () use ($account, $validated) {
                $account->update($validated);
            });

            return $this->responseSuccess(
                $account->fresh(),
                'Account updated successfully',
                200
            );

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->responseError($e->errors(), 'Validation failed', 422);
        } catch (\Exception $err) {
            Log::error('Error While updating Account data : '.$err->getMessage());

            return $this->responseError(null, 'Account update failed', 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $account = Account::findOrFail($id);

            DB::transaction(function () use ($account) {
                $account->delete();
            });

            return $this->responseSuccess([], '', 200);
        } catch (Exception $err) {
            return $this->responseError([], '', 500);
        }
    }
}
