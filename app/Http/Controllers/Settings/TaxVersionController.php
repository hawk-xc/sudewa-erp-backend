<?php

namespace App\Http\Controllers\Settings;

use Exception;
use App\Models\Tax;
use App\Models\TaxVersion;
use Illuminate\Http\Request;
use App\Traits\ResponseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TaxVersionController extends Controller
{
    use ResponseTrait;

    protected array $taxVersionTable;

    public function __construct()
    {
        $this->middleware(['permission:settings:list'])->only(['index', 'show']);
        $this->middleware(['permission:settings:create'])->only('store');
        $this->middleware(['permission:settings:edit'])->only('update');
        $this->middleware(['permission:settings:delete'])->only(['destroy']);

        $this->taxVersionTable = ['id', 'tax_id', 'name', 'rate', 'effective_from', 'effective_until', 'is_default', 'created_at'];
    }

    /**
     * List all tax versions.
     */
    public function index(Request $request)
    {
        $query = TaxVersion::with('tax');

        try {
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%");
                });
            }

            foreach ($this->taxVersionTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $sortBy = in_array($request->sort_by, $this->taxVersionTable) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $data = $query->orderBy($sortBy, $sortOrder)->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'Tax Version list retrieved successfully');
        } catch (Exception $err) {
            Log::error('Error retrieving Tax Version: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve Tax Version list', 500);
        }
    }

    /**
     * Store a new tax version.
     */
    public function store(Request $request, string $taxId)
    {
        try {
            $tax = Tax::findOrFail($taxId);

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'rate' => 'required|integer|min:0',
                'effective_from' => 'nullable|date',
                'effective_until' => 'nullable|date|after_or_equal:effective_from',
                'is_default' => 'boolean',
            ]);

            $data = DB::transaction(function () use ($tax, $validated) {
                if (!empty($validated['is_default']) && $validated['is_default']) {
                    TaxVersion::where('tax_id', $tax->id)
                        ->where('is_default', true)
                        ->update(['is_default' => false]);
                }

                return $tax->TaxVersions()->create($validated);
            });

            return $this->responseSuccess($data, 'Tax Version created successfully', 201);
        } catch (ModelNotFoundException $err) {
            return $this->responseError(null, 'Tax not found', 404);
        } catch (Exception $err) {
            Log::error('Error creating Tax Version: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Tax Version creation failed', 500);
        }
    }

    /**
     * Get tax version details.
     */
    public function show(string $id)
    {
        try {
            $taxVersion = TaxVersion::with('tax')->findOrFail($id);
            return $this->responseSuccess($taxVersion, 'Tax Version retrieved successfully');
        } catch (ModelNotFoundException $err) {
            return $this->responseError(null, 'Tax Version not found', 404);
        } catch (Exception $err) {
            Log::error('Error showing Tax Version: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve Tax Version details', 500);
        }
    }

    /**
     * Update the specified tax version.
     */
    public function update(Request $request, string $id)
    {
        try {
            $taxVersion = TaxVersion::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'rate' => 'sometimes|required|integer|min:0',
                'effective_from' => 'nullable|date',
                'effective_until' => 'nullable|date|after_or_equal:effective_from',
                'is_default' => 'boolean',
            ]);

            $data = DB::transaction(function () use ($taxVersion, $validated) {
                if (!empty($validated['is_default']) && $validated['is_default']) {
                    TaxVersion::where('tax_id', $taxVersion->tax_id)
                        ->where('id', '!=', $taxVersion->id)
                        ->where('is_default', true)
                        ->update(['is_default' => false]);
                }

                $taxVersion->update($validated);
                return $taxVersion->fresh();
            });

            return $this->responseSuccess($data, 'Tax Version updated successfully');
        } catch (ModelNotFoundException $err) {
            return $this->responseError(null, 'Tax Version not found', 404);
        } catch (Exception $err) {
            Log::error('Error updating Tax Version: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Tax Version update failed', 500);
        }
    }

    /**
     * Delete the specified tax version.
     */
    public function destroy(string $id)
    {
        try {
            $taxVersion = TaxVersion::findOrFail($id);

            $count = TaxVersion::where('tax_id', $taxVersion->tax_id)->count();
            if ($count <= 1) {
                return $this->responseError(null, 'Cannot delete the last tax version', 422);
            }

            if ($taxVersion->is_default) {
                return $this->responseError(null, 'Cannot delete default tax version', 422);
            }

            DB::transaction(function () use ($taxVersion) {
                $taxVersion->delete();
            });

            return $this->responseSuccess(null, 'Tax Version deleted successfully');
        } catch (ModelNotFoundException $err) {
            return $this->responseError(null, 'Tax Version not found', 404);
        } catch (Exception $err) {
            Log::error('Error deleting Tax Version: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Tax Version deletion failed', 500);
        }
    }
}
