<?php

namespace App\Http\Controllers\Global;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Module;
use App\Services\CurrencyService;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GlobalCompanyController extends Controller
{
    use ResponseTrait;

    protected $companyTable = [
        'id',
        'uuid',
        'slug',
        'name',
        'code',
        'description',
        'type',
        'created_at',
    ];

    public function index()
    {
        try {
            $companies = Company::query();
            $companies->select($this->companyTable);
            $companies->latest();
            $companies->paginate(10);

            return $this->responseSuccess(
                $companies->get(),
                'Company list retrieved successfully',
                200
            );

        } catch (\Exception $e) {
            Log::error('Error retrieving companies: '.$e->getMessage());

            return $this->responseError(
                null,
                'Failed to retrieve company list',
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
                'type' => 'sometimes|in:office,transport_office',
                'modules' => 'sometimes|string',
            ]);

            if (isset($validated['modules'])) {
                $validModules = ['master-data', 'transaction', 'warehouse', 'finance', 'report'];
                $modules = explode(',', $validated['modules']);

                foreach ($modules as $module) {
                    if (! in_array($module, $validModules)) {
                        return $this->responseError(
                            null,
                            'Invalid module name '.$module,
                            422
                        );
                    } else {
                        $validated['modules'] = $modules;
                    }
                }
            }
            $company = DB::transaction(function () use ($validated) {
                return Company::create($validated);
            });

            $company->modules()->attach(Module::where('slug', $validated['modules'])->first()->pluck('id'));

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
            $company = Company::with('modules')->find($id);

            if (!$company) {
                $company = Company::with('modules')
                    ->where('slug', $id)
                    ->first();
            }

            if (!$company) {
                return $this->responseError(
                    null,
                    'Company not found',
                    404
                );
            }

            return $this->responseSuccess(
                $company,
                'Company retrieved successfully',
                200
            );

        } catch (\Exception $e) {
            return $this->responseError(
                null,
                'Company not found',
                404
            );
        }
    }

    public function showBySlug(string $slug)
    {
        try {
            $company = Company::with('modules')->where('slug', (string) $slug)->first();

            return $this->responseSuccess(
                $company,
                'Company retrieved successfully',
                200
            );

        } catch (\Exception $e) {
            return $this->responseError(
                null,
                'Company not found',
                404
            );
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $company = Company::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255|unique:companies,name,'.$id,
                'description' => 'nullable|string',
            ]);

            DB::transaction(function () use ($company, $validated) {
                $company->update($validated);
            });

            return $this->responseSuccess(
                $company->fresh(),
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

    public function assignModule(Request $request, string $id)
    {
        $validated = $request->validate([
            'modules' => 'required|string',
        ]);

        if (isset($validated['modules'])) {
            $validModules = ['master-data', 'transaction', 'warehouse', 'finance', 'report'];
            $modules = explode(',', $validated['modules']);

            foreach ($modules as $module) {
                if (! in_array($module, $validModules)) {
                    return $this->responseError(
                        null,
                        'Invalid module name '.$module,
                        422
                    );
                } else {
                    $validated['modules'] = $modules;
                }
            }
        }

        $company = Company::findOrFail($id);

        DB::transaction(function () use ($company, $validated) {
            $company->modules()->sync(Module::where('slug', $validated['modules'])->first()->pluck('id'));
        });

        return $this->responseSuccess(
            $company->fresh(),
            'Module assigned successfully',
            200
        );
    }

    public function covertIdrToUsd(CurrencyService $currencyService, Request $request)
    {
        $request->validate([
            'amount' => 'required|integer',
        ]);

        $usdAmount = $currencyService->convertIdrToUsd($request->amount);

        if ($usdAmount) {
            return $this->responseSuccess(
                [
                    'result' => $usdAmount,
                ],
                'IDR converted to USD',
                200
            );
        } else {
            return $this->responseError([], 'Currency Convert Service Error', 500);
        }
    }

    public function convertUsdToIdr(CurrencyService $currencyService, Request $request)
    {
        $request->validate([
            'amount' => 'required|integer',
        ]);

        $idrAmount = $currencyService->convertUsdToIdr($request->amount);

        if ($idrAmount) {
            return $this->responseSuccess(
                [
                    'result' => $idrAmount,
                ],
                'USD converted to IDR',
                200
            );
        } else {
            return $this->responseError([], 'Currency Convert Service Error', 500);
        }
    }
}
