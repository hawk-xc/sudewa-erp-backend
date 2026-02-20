<?php

namespace App\Http\Controllers\Global;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GlobalModuleController extends Controller
{
    use ResponseTrait;

    protected $moduleTable = [
        'id',
        'slug',
        'name',
        'description',
        'created_at',
    ];

    public function index()
    {
        try {
            $modules = Module::query();
            $modules->select($this->moduleTable);
            $modules->latest();
            $modules->paginate(10);

            return $this->responseSuccess(
                $modules->get(),
                'Module list retrieved successfully',
                200
            );

        } catch (\Exception $e) {
            Log::error('Error retrieving modules: '.$e->getMessage());

            return $this->responseError(
                null,
                'Failed to retrieve module list',
                500
            );
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:companies,name',
                'description' => 'nullable|string',
            ]);

            $company = DB::transaction(function () use ($validated) {
                return Company::create($validated);
            });

            return $this->responseSuccess(
                $company,
                'Company created successfully',
                201
            );

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->responseError(
                $e->errors(),
                'Validation failed while creating company',
                422
            );

        } catch (\Exception $e) {
            Log::error('Error storing company: '.$e->getMessage());

            return $this->responseError(
                null,
                'Failed to create company',
                500
            );
        }
    }

    public function show(string $id)
    {
        try {
            $module = Module::with('companies')->findOrFail($id);

            return $this->responseSuccess(
                $module,
                'Module retrieved successfully',
                200
            );

        } catch (Exception $e) {
            return $this->responseError(
                null,
                'Module not found',
                404
            );
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $module = Module::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255|unique:modules,name,'.$id,
                'description' => 'nullable|string',
            ]);

            DB::transaction(function () use ($module, $validated) {
                $module->update($validated);
            });

            return $this->responseSuccess(
                $module->fresh(),
                'Company updated successfully',
                200
            );

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->responseError(
                $e->errors(),
                'Validation failed while updating company',
                422
            );

        } catch (\Exception $e) {
            Log::error('Error updating company: '.$e->getMessage());

            return $this->responseError(
                null,
                'Failed to update company',
                500
            );
        }
    }

    public function destroy(string $id)
    {
        try {
            $company = Company::findOrFail($id);

            DB::transaction(function () use ($company) {
                $company->delete();
            });

            return $this->responseSuccess(
                null,
                'Company deleted successfully',
                200
            );

        } catch (\Exception $e) {
            Log::error('Error deleting company: '.$e->getMessage());

            return $this->responseError(
                null,
                'Failed to delete company',
                500
            );
        }
    }
}
