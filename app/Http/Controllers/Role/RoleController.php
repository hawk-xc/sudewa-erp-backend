<?php

namespace App\Http\Controllers\Role;

use Illuminate\Http\Request;
use App\Traits\ResponseTrait;
use Spatie\Permission\Models\Role;
use App\Repositories\AuthRepository;
use App\Http\Controllers\Controller;

class RoleController extends Controller
{
    use ResponseTrait;

    /**
     * @var AuthRepository
     */
    protected AuthRepository $authRepository;

    /**
     * AuthController constructor.
     */
    public function __construct(AuthRepository $ar)
    {
        $this->middleware(['permission:role:list'])->only(['index', 'show']);
        $this->middleware(['permission:role:create'])->only(['store']);
        $this->middleware(['permission:role:update'])->only(['update']);
        $this->middleware(['permission:role:delete'])->only(['destroy']);
        $this->middleware(['permission:role:assign-permission'])->only([
            'assignPermission',
            'assignPermissions',
            'revokePermission',
            'revokePermissions'
        ]);

        $this->authRepository = $ar;
    }


    public function index(Request $request)
    {
        $roles = Role::withCount('users', 'permissions');

        if ($request->has('search')) {
            $roles->where('name', 'like', '%' . $request->search . '%');
        }

        $roles = $roles->get();

        return $this->responseSuccess($roles, 'Roles retrieved successfully');
    }

    public function show(string $id)
    {
        $role = Role::with(['users', 'permissions'])->find($id);

        if (!$role) {
            return $this->responseError(null, 'Role not found', 404);
        }

        return $this->responseSuccess($role, 'Role retrieved successfully');
    }

    public function showWithoutPermissions(string $id)
    {
        $role = Role::with(['users'])->find($id);

        if (!$role) {
            return $this->responseError(null, 'Role not found', 404);
        }

        return $this->responseSuccess($role, 'Role retrieved successfully');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name'
        ]);

        $permissionNames = $this->parseAndValidatePermissions($request, 'permissions', false);

        $role = Role::create(['name' => $request->name]);

        if (!is_null($permissionNames)) {
            $role->syncPermissions($permissionNames);
        }

        $role->load('permissions');

        return $this->responseSuccess($role, 'Role created successfully');
    }

    public function assignPermissions(Request $request, string $id)
    {
        $role = Role::find($id);

        if (!$role) {
            return $this->responseError(null, 'Role not found', 404);
        }

        $permissionNames = $this->parseAndValidatePermissions($request, 'permissions', true);

        if (!empty($permissionNames)) {
            $role->givePermissionTo($permissionNames);
        }

        $role->load('permissions');

        return $this->responseSuccess($role, 'Permissions assigned successfully');
    }

    public function assignPermission(Request $request, string $id)
    {
        return $this->assignPermissions($request, $id);
    }

    public function revokePermissions(Request $request, string $id)
    {
        $role = Role::find($id);

        if (!$role) {
            return $this->responseError(null, 'Role not found', 404);
        }

        $permissionNames = $this->parseAndValidatePermissions($request, 'permissions', true);

        if (!empty($permissionNames)) {
            $role->revokePermissionTo($permissionNames);
        }

        $role->load('permissions');

        return $this->responseSuccess($role, 'Permissions revoked successfully');
    }

    public function revokePermission(Request $request, string $id)
    {
        return $this->revokePermissions($request, $id);
    }

    public function update(Request $request, string $id)
    {
        $role = Role::find($id);

        if (!$role) {
            return $this->responseError(null, 'Role not found', 404);
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $id
        ]);

        $permissionNames = $this->parseAndValidatePermissions($request, 'permissions', false);

        $role->update(['name' => $request->name]);

        if (!is_null($permissionNames)) {
            $role->syncPermissions($permissionNames);
        }

        $role->load('permissions');

        return $this->responseSuccess($role, 'Role updated successfully');
    }

    public function destroy(string $id)
    {
        $role = Role::find($id);

        if (!$role) {
            return $this->responseError(null, 'Role not found', 404);
        }

        $role->delete();

        return $this->responseSuccess(null, 'Role deleted successfully');
    }

    /**
     * Parse and validate permissions from request.
     *
     * @param Request $request
     * @param string $fieldName
     * @param bool $isRequired
     * @return array|null Null if not present, array of names (could be empty) if present.
     */
    private function parseAndValidatePermissions(Request $request, string $fieldName = 'permissions', bool $isRequired = false): ?array
    {
        if (!$request->has($fieldName)) {
            if ($isRequired) {
                $request->validate([$fieldName => 'required']);
            }
            return null;
        }

        $value = $request->input($fieldName);

        if (is_null($value) || $value === '' || (is_array($value) && empty($value))) {
            return [];
        }

        if (is_array($value)) {
            $permissionNames = $value;
        } else {
            $permissionNames = array_map('trim', explode(',', $value));
            $permissionNames = array_filter($permissionNames, function($val) {
                return $val !== '';
            });
        }

        if (!empty($permissionNames)) {
            $dbPermissionsCount = \Spatie\Permission\Models\Permission::whereIn('name', $permissionNames)->count();

            if (count($permissionNames) !== $dbPermissionsCount) {
                $dbPermissions = \Spatie\Permission\Models\Permission::whereIn('name', $permissionNames)->pluck('name')->all();
                $missingPermissions = array_diff($permissionNames, $dbPermissions);

                $validator = \Illuminate\Support\Facades\Validator::make([], []);
                $validator->errors()->add($fieldName, 'The following permissions do not exist: ' . implode(', ', $missingPermissions));
                throw new \Illuminate\Validation\ValidationException($validator);
            }
        }

        return $permissionNames;
    }
}
