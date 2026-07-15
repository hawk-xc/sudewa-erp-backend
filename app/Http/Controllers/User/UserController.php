<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\User;
use App\Repositories\AuthRepository;
use App\Traits\FileTrait;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    use FileTrait, ResponseTrait;

    protected AuthRepository $authRepository;

    /**
     * AuthController constructor.
     */
    public function __construct(AuthRepository $ar)
    {
        $this->middleware(['permission:user:list'])->only(['index', 'show', 'getUserPassword']);
        $this->middleware(['permission:user:create'])->only('store');
        $this->middleware(['permission:user:edit'])->only('update', 'activateUser', 'deactivateUser');
        $this->middleware(['permission:user:delete'])->only(['destroy']);
        $this->middleware(['permission:role:create'])->only(['assignRole']);
        $this->middleware(['permission:role:delete'])->only(['revokeRole']);

        $this->authRepository = $ar;
    }

    public function index(Request $request): JsonResponse
    {
        $users = User::with('roles');

        if ($request->filled('status')) {
            $users->where('is_active', $request->status === 'true' ? true : false);
        }

        if ($request->filled('search')) {
            $users->where(function ($query) use ($request) {
                if ($request->filled('strict') && $request->strict == 'true') {
                    $query->where('name', $request->search)
                        ->orWhere('email', $request->search)
                        ->orWhere('username', $request->search);
                } else {
                    $query->where('name', 'like', '%'.$request->search.'%')
                        ->orWhere('email', 'like', '%'.$request->search.'%')
                        ->orWhere('username', 'like', '%'.$request->search.'%');
                }
            });
        }

        if ($request->has('role')) {
            $users->whereHas('roles', function ($query) use ($request) {
                $query->where('name', $request->role);
            });
        }

        $users = $users->get();

        return $this->responseSuccess($users, 'Users retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users',
            'password' => 'nullable|string|min:8|confirmed',
            'username' => 'required|string|max:255|unique:users',
            'firstname' => 'nullable|string|max:255',
            'lastname' => 'nullable|string|max:255',
            'roles' => 'nullable|string',
            'roles.*' => 'string|exists:roles,name',
            'avatar' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
        ]);

        DB::beginTransaction();
        try {
            $data = [
                'is_active' => false,
                'name' => $request->name,
                'email' => $request->email,
                'username' => $request->username,
                'firstname' => $request->firstname,
                'lastname' => $request->lastname,
                'fullname' => $request->firstname.' '.$request->lastname,
            ];

            if ($request->filled('password')) {
                $plainPassword = $request->password;
            } else {
                $plainPassword = Str::random(10);
            }

            $data['password'] = Hash::make($plainPassword);
            $data['secure_password'] = encrypt($plainPassword);

            if ($request->hasFile('avatar')) {
                $data['avatar'] = $this->storeFile(
                    $request->file('avatar'),
                    'user_avatar'
                );
            }

            $user = User::create($data);

            if ($request->has('roles') && ! empty($request->roles)) {
                $user->assignRole($request->roles);
            }

            DB::commit();

            $user->plain_password = $plainPassword;

            return $this->responseSuccess($user, 'User created successfully');
        } catch (Exception $e) {
            DB::rollBack();

            return $this->responseError(null, $e->getMessage(), 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        $user = User::with('roles')->find($id);
        if (! $user) {
            return $this->responseError(null, 'User not found', 404);
        }

        return $this->responseSuccess($user, 'User retrieved successfully');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);
        if (! $user) {
            return $this->responseError(null, 'User not found', 404);
        }

        $keys = ['name', 'email', 'password', 'username', 'firstname', 'lastname', 'role', 'roles'];
        if ($request->hasFile('avatar') || $request->has('avatar')) {
            $keys[] = 'avatar';
        }

        $data = array_filter($request->only($keys), function ($value) {
            return ! is_null($value) && $value !== '';
        });

        if (empty($data)) {
            return $this->responseError(null, 'No data provided to update', 422);
        }

        $rules = [];
        if ($request->hasFile('avatar') || array_key_exists('avatar', $data)) {
            $rules['avatar'] = 'nullable|file|mimes:jpg,jpeg,png|max:2048';
        }
        if (array_key_exists('name', $data)) {
            $rules['name'] = 'string|max:255';
        }
        if (array_key_exists('email', $data)) {
            $rules['email'] = 'string|email|max:255|unique:users,email,'.$id;
        }
        if (array_key_exists('password', $data)) {
            $rules['password'] = 'string|min:8';
        }
        if (array_key_exists('username', $data)) {
            $rules['username'] = 'string|max:255|unique:users,username,'.$id;
        }
        if (array_key_exists('firstname', $data)) {
            $rules['firstname'] = 'string|max:255';
        }
        if (array_key_exists('lastname', $data)) {
            $rules['lastname'] = 'nullable|string|max:255';
        }
        $request->validate($rules);

        $rolesToSync = null;
        if (array_key_exists('role', $data) || array_key_exists('roles', $data)) {
            $rolesToSync = [];
            if (array_key_exists('role', $data)) {
                $rolesToSync[] = $data['role'];
            }
            if (array_key_exists('roles', $data)) {
                $rolesVal = $data['roles'];
                if (is_array($rolesVal)) {
                    $rolesToSync = array_merge($rolesToSync, $rolesVal);
                } elseif (is_string($rolesVal)) {
                    if (str_contains($rolesVal, ',')) {
                        $rolesToSync = array_merge($rolesToSync, array_map('trim', explode(',', $rolesVal)));
                    } else {
                        $rolesToSync[] = $rolesVal;
                    }
                }
            }
            $rolesToSync = array_values(array_unique(array_filter($rolesToSync, function ($val) {
                return !is_null($val) && $val !== '';
            })));

            if (!empty($rolesToSync)) {
                $existingRolesCount = DB::table('roles')->whereIn('name', $rolesToSync)->count();
                if ($existingRolesCount !== count($rolesToSync)) {
                    return $this->responseError(null, 'One or more provided roles do not exist.', 422);
                }
            }

            if ($user->hasRole('admin') && !in_array('admin', $rolesToSync)) {
                $adminCount = User::role('admin')->count();
                if ($adminCount <= 1) {
                    return $this->responseError(null, 'Cannot change role of the last admin user. There must be at least one admin.', 422);
                }
            }
        }

        DB::beginTransaction();
        try {
            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
                $data['secure_password'] = encrypt($data['password']);
            }

            if (isset($data['firstname']) || isset($data['lastname'])) {
                $firstname = $data['firstname'] ?? $user->firstname;
                $lastname = $data['lastname'] ?? $user->lastname;
                $data['fullname'] = $firstname.' '.$lastname;
            }

            if ($request->hasFile('avatar')) {
                if ($user->avatar) {
                    $this->destroyFile('user_avatar/' . $user->avatar);
                }

                $data['avatar'] = $this->storeFile(
                    $request->file('avatar'),
                    'user_avatar'
                );
            }

            $modelData = $data;
            unset($modelData['role'], $modelData['roles']);

            $user->fill($modelData);
            $user->save();

            if (is_array($rolesToSync)) {
                $user->syncRoles($rolesToSync);
            }

            DB::commit();

            return $this->responseSuccess($user->load('roles'), 'User updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->responseError(null, $e->getMessage(), 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        $user = User::find($id);
        if (! $user) {
            return $this->responseError(null, 'User not found', 404);
        }

        if ($user->hasRole('admin')) {
            return $this->responseError(null, 'Cannot delete admin user', 403);
        }

        DB::beginTransaction();
        try {
            $user->delete();
            DB::commit();

            return $this->responseSuccess(null, 'User deleted successfully');
        } catch (Exception $e) {
            DB::rollBack();

            return $this->responseError(null, $e->getMessage(), 500);
        }
    }

    public function assignRole(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return $this->responseError(null, 'User not found', 404);
        }

        $request->validate([
            'roles' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            $user->assignRole($request->roles);
            DB::commit();

            return $this->responseSuccess($user->load('roles'), 'Roles assigned successfully');
        } catch (Exception $e) {
            DB::rollBack();

            return $this->responseError(null, $e->getMessage(), 500);
        }
    }

    public function revokeRole(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);
        if (! $user) {
            return $this->responseError(null, 'User not found', 404);
        }

        $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'string|exists:roles,name',
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->roles as $role) {
                if ($user->hasRole($role)) {
                    $user->removeRole($role);
                }
            }
            DB::commit();

            return $this->responseSuccess($user->load('roles'), 'Roles revoked successfully');
        } catch (Exception $e) {
            DB::rollBack();

            return $this->responseError(null, $e->getMessage(), 500);
        }
    }

    public function activateUser(string $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return $this->responseError(null, 'User not found', 404);
        }

        DB::beginTransaction();

        try {
            $user->is_active = true;
            $user->save();
            DB::commit();

            return $this->responseSuccess($user, 'User activated successfully');
        } catch (Exception $e) {
            DB::rollBack();

            return $this->responseError(null, $e->getMessage(), 500);
        }
    }

    public function deactivateUser(string $id): JsonResponse
    {
        $user = User::find($id);
        if (! $user) {
            return $this->responseError(null, 'User not found', 404);
        }

        DB::beginTransaction();
        try {
            $user->is_active = false;
            $user->save();
            DB::commit();

            return $this->responseSuccess($user, 'User deactivated successfully');
        } catch (Exception $e) {
            DB::rollBack();

            return $this->responseError(null, $e->getMessage(), 500);
        }
    }

    public function getUserPassword(string $id): JsonResponse
    {
        $user = User::find($id);

        if ($user->secure_password == null) {
            return $this->responseError(null, 'User secure password not found', 404);
        }

        $user_password = decrypt($user->secure_password);

        if (! $user_password) {
            return $this->responseError(null, 'User password not found', 404);
        }

        return $this->responseSuccess(
            [
                'secure_password' => $user_password,
            ],
            'User password fetched successfully',
        );
    }

    public function getStatus(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->query('status') === 'true') {
            $query->where('is_active', true);
        } else {
            $query->where('is_active', false);
        }

        if ($request->query('sum') === 'count_data') {
            return $this->responseSuccess(
                [
                    'count' => $query->count(),
                ],
                'Matched Users Counted Successfully!',
            );
        }

        return $this->responseSuccess(
            [
                'data' => $query->get(),
                'status' => $request->query('status') === 'active' ? 'active' : 'inactive',
            ],
            'Users retrieved successfully',
        );
    }

    public function showModule(string $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);

            $modules = Module::with([
                'features' => function ($q) {
                    $q->where('is_active', true)
                        ->select('features.id', 'slug', 'name', 'description');
                }
            ])->get();

            $isAdmin = $user->hasRole('admin');
            $userPermissions = $user->getAllPermissions()->pluck('name')->toArray();

            $data = $modules->map(function ($module) use ($isAdmin, $userPermissions) {
                $moduleSlug = $module->slug;

                $filteredFeatures = $module->features->filter(function ($feature) use ($isAdmin, $userPermissions, $moduleSlug) {
                    if ($isAdmin) {
                        return true;
                    }

                    $featureSlug = $feature->slug;
                    $singularFeatureSlug = Str::singular($featureSlug);

                    // We consider a feature available if the user has any permission matching:
                    // - module_slug:feature_slug:*
                    // - feature_slug:*
                    // - singular_feature_slug:*
                    $prefixes = [
                        $moduleSlug.':'.$featureSlug,
                        $featureSlug,
                        $singularFeatureSlug,
                    ];

                    foreach ($userPermissions as $permission) {
                        foreach ($prefixes as $prefix) {
                            if ($permission === $prefix || str_starts_with($permission, $prefix.':')) {
                                return true;
                            }
                        }
                    }

                    // Fallback: If user has general access to the module (e.g., master-data:list)
                    $modulePermissions = [$moduleSlug.':list', $moduleSlug.':view', $moduleSlug.':all'];
                    foreach ($modulePermissions as $modPerm) {
                        if (in_array($modPerm, $userPermissions)) {
                            return true;
                        }
                    }

                    return false;
                })->values();

                if ($filteredFeatures->isEmpty()) {
                    return null;
                }

                return [
                    'module' => [
                        'id' => $module->id,
                        'name' => $module->name,
                        'slug' => $module->slug,
                        'description' => $module->description,
                    ],
                    'features' => $filteredFeatures,
                ];
            })->filter()->values();

            return $this->responseSuccess($data, 'User modules and features retrieved successfully', 200);

        } catch (Exception $err) {
            return $this->responseError(
                $err->getMessage(),
                'Failed to retrieve modules and features',
                500
            );
        }
    }
}
