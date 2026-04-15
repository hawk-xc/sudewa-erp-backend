<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Imports\AccountImport;
use App\Models\Account;
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
 * API for managing accounts.
 */
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

        $this->accountTable = ['id', 'uuid', 'code', 'account_group_id', 'name', 'description', 'type', 'created_at'];
    }

    /**
     * List all accounts.
     */
    public function index(Request $request)
    {
        try {
            $query = Account::query()->with('accountGroup:id,company_id,group_code');

            $query->select($this->accountTable);

            if ($request->filled('search')) {
                $search = $request->search;
                $caseSensitive = $request->boolean('case_sensitive');

                $query->where(function ($q) use ($search, $caseSensitive) {
                    if ($caseSensitive) {
                        $q->where('name', 'LIKE BINARY', "%$search%")
                            ->orWhere('code', 'LIKE BINARY', "%$search%")
                            ->orWhere('account_group_id', 'LIKE BINARY', "%$search%")
                            ->orWhere('description', 'LIKE BINARY', "%$search%")
                            ->orWhere('type', 'LIKE BINARY', "%$search%");
                    } else {
                        $q->where('name', 'like', "%$search%")
                            ->orWhere('code', 'like', "%$search%")
                            ->orWhere('account_group_id', 'like', "%$search%")
                            ->orWhere('description', 'like', "%$search%")
                            ->orWhere('type', 'like', "%$search%");
                    }
                });
            }

            if ($request->filled('account_group_id')) {
                $query->where('account_group_id', $request->account_group_id);
            }

            foreach ($this->accountTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $allowedSort = $this->accountTable;

            $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'id';

            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->per_page ?? 10;

            $data = $query->paginate($perPage);

            return $this->responseSuccess($data, 'Account list retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error While retrieved Account data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Account list retrieved Failed', 500);
        }
    }

    /**
     * Get account details.
     */
    public function show(string $id)
    {
        try {
            $account = Account::with('accountGroup')->select($this->accountTable)->findOrFail($id);

            return $this->responseSuccess($account, 'Account retrieved successfully', 200);
        } catch (Exception $err) {
            return $this->responseError($err->getMessage(), 'Account Group not found', 404);
        }
    }

    /**
     * Store a new account.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'account_group_id' => 'required|integer|exists:account_groups,id',
                'code' => 'required|string|max:50|unique:accounts,code',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'type' => 'required|in:debet,credit',
            ]);

            $account = DB::transaction(function () use ($validated) {
                return Account::create($validated);
            });

            return $this->responseSuccess($account->fresh(), 'Account created successfully', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->responseError($e->errors(), 'Validation failed', 422);
        } catch (Exception $err) {
            Log::error('Error While storing Account data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Account creation failed', 500);
        }
    }

    /**
     * Update an account.
     */
    public function update(Request $request, string $id)
    {
        try {
            $account = Account::findOrFail((int) $id);

            if ($account) {
                $validated = $request->validate([
                    'account_group_id' => 'sometimes|integer|exists:account_groups,id',
                    'code' => 'sometimes|required|string|max:50|unique:accounts,code,'.$id,
                    'name' => 'sometimes|required|string|max:255',
                    'description' => 'nullable|string',
                    'type' => 'sometimes|required|in:debet,credit',
                ]);

                DB::transaction(function () use ($account, $validated) {
                    $account->update($validated);
                });

                return $this->responseSuccess($account->fresh(), 'Account updated successfully', 200);
            } else {
                return $this->responseError(null, 'Account not found', 404);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->responseError($e->errors(), 'Validation failed', 422);
        } catch (Exception $err) {
            Log::error('Error While updating Account data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Account update failed', 500);
        }
    }

    /**
     * Delete an account.
     */
    public function destroy(string $id)
    {
        try {
            $account = Account::findOrFail($id);

            DB::transaction(function () use ($account) {
                $account->delete();
            });

            return $this->responseSuccess([], 'Account sucessfully Deleted', 200);
        } catch (Exception $err) {
            return $this->responseError($err->getMessage(), 'Account Not Found or Failed Deleted', 500);
        }
    }

    /**
     * Import accounts from Excel.
     */
    public function import(Request $request, string $id)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        try {
            Excel::import(new AccountImport($id), $request->file('file'));

            return $this->responseSuccess(null, 'Account imported successfully', 201);
        } catch (Exception $e) {
            Log::error('Account import error', [
                'message' => $e->getMessage(),
            ]);

            return $this->responseError($e->getMessage(), 'Account import error', 500);
        }
    }
}
