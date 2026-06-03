<?php

namespace App\Http\Controllers\MasterData;

use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Traits\GlobalCodeNumberTrait;
use App\Http\Controllers\Controller;
use App\Models\AccountGroup;
use App\Repositories\AuthRepository;
use Exception;
use Illuminate\Http\Request;
use App\Traits\ResponseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @group Master Data
 *
 * API for managing account groups.
 */
class MasterAccountGroupController extends Controller
{
    use ResponseTrait, GlobalCodeNumberTrait;

    protected AuthRepository $authRepository;

    // projection
    protected array $accountGroupTable;

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

        $this->accountGroupTable = ['id', 'uuid', 'group_code', 'description', 'created_at'];
    }

    /**
     * List all account groups.
     */
    public function index(Request $request)
    {
        try {
            $query = AccountGroup::query();

            if ($request->filled('company_id')) {
                $query->where('company_id', $request->company_id);
            }

            $query->select($this->accountGroupTable);

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

            foreach ($this->accountGroupTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $allowedSort = $this->accountGroupTable;

            $sortBy = in_array($request->sort_by, $allowedSort)
                ? $request->sort_by
                : 'id';

            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->per_page ?? 10;

            $data = $query->paginate($perPage);

            return $this->responseSuccess($data, 'Account Group list retrieved successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error While retrieved Account Group data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Account list retrieved Failed', 500);
        }
    }

    /**
     * Get account group details.
     */
    public function show(string $id)
    {
        try {
            $accountGroup = AccountGroup::select($this->accountGroupTable)->with('accounts')->findOrFail($id);

            return $this->responseSuccess(
                $accountGroup,
                'Account Group retrieved successfully',
                200
            );

        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            return $this->responseError('The requested resource could not be found.', 'Resource Not Found', 404);
        }
    }

    /**
     * Store a new account group.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'company_id' => 'required|exists:companies,id',
                'group_code' => 'required|string|max:50|unique:account_groups,group_code',
                'description' => 'nullable|string',
            ]);

            $accountGroup = DB::transaction(function () use ($validated) {
                return AccountGroup::create($validated);
            });

            return $this->responseSuccess(
                $accountGroup->fresh(),
                'Account Group created successfully',
                201
            );

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->responseError($e->errors(), 'Validation failed', 422);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error While storing Account Group data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Account Group creation failed', 500);
        }
    }

    /**
     * Update an account group.
     */
    public function update(Request $request, string $id)
    {
        try {
            $account = AccountGroup::findOrFail($id);

            $validated = $request->validate([
                'group_code' => 'nullable|string|max:50|unique:account_groups,group_code,'.$id,
                'description' => 'nullable|string',
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
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error While updating Account data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Account update failed', 500);
        }
    }

    /**
     * Delete an account group.
     */
    public function destroy(string $id)
    {
        try {
            $accountGroup = AccountGroup::find((int) $id);

            if ($accountGroup) {
                DB::transaction(function () use ($accountGroup) {
                    $accountGroup->delete();       
                });

                return $this->responseSuccess([], "Account Group sucessfully Deleted", 200);
            }

            return $this->responseError('The requested resource could not be found.', 'Resource Not Found', 404);

        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            return $this->responseError($err->getMessage(), "Account Group Not Found or Failed Deleted", 500);
        }
    }
}
