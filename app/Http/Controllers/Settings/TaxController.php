<?php

namespace App\Http\Controllers\Settings;

use Exception;
use App\Models\Tax;
use Illuminate\Http\Request;
use App\Traits\ResponseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TaxController extends Controller
{
    use ResponseTrait;

    protected array $taxTable;

    public function __construct()
    {
        $this->middleware(['permission:settings:list'])->only(['index', 'show']);
        $this->middleware(['permission:settings:create'])->only('store');
        $this->middleware(['permission:settings:edit'])->only('update');
        $this->middleware(['permission:settings:delete'])->only(['destroy']);

        $this->taxTable = ['id', 'code', 'name', 'is_lock', 'created_at'];
    }

    /**
     * List all taxes.
     */
    public function index(Request $request)
    {
        $query = Tax::query();

        $query->select($this->taxTable);

        $query->with(['taxVersions' => function ($q) {
            $q->select(['id', 'tax_id', 'name', 'is_default'])->where('is_default', 1);
        }]);

        try {
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%$search%")
                        ->orWhere('name', 'like', "%$search%");
                });
            }

            foreach ($this->taxTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $sortBy = in_array($request->sort_by, $this->taxTable) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $data = $query->orderBy($sortBy, $sortOrder)->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'Tax list retrieved successfully');
        } catch (Exception $err) {
            Log::error('Error retrieving Tax: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve Tax list', 500);
        }
    }

    /**
     * Store a new tax.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:taxes,code',
            'name' => 'required|string|max:255',
        ]);

        try {
            $data = DB::transaction(function () use ($validated) {
                return Tax::create($validated);
            });

            return $this->responseSuccess($data, 'Tax created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error creating Tax: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Tax creation failed', 500);
        }
    }

    /**
     * Get tax details.
     */
    public function show(string $id)
    {
        try {
            $tax = Tax::with('TaxVersions')->findOrFail($id);
            return $this->responseSuccess($tax, 'Tax retrieved successfully');
        } catch (ModelNotFoundException $err) {
            return $this->responseError(null, 'Tax not found', 404);
        } catch (Exception $err) {
            Log::error('Error showing Tax: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve Tax details', 500);
        }
    }

    public function getDefault(string $code)
    {
        $taxCode = Tax::where('code', $code)->first()->taxVersion->where('is_default', 1)->latest()->first();
    }

    /**
     * Update the specified tax.
     */
    public function update(Request $request, string $id)
    {
        try {
            $tax = Tax::findOrFail($id);

            if ($tax->is_lock) {
                return $this->responseError(null, 'Cannot update locked tax', 422);
            }

            $validated = $request->validate([
                'code' => 'sometimes|required|string|max:50|unique:taxes,code,' . $id,
                'name' => 'sometimes|required|string|max:255',
                'is_lock' => 'boolean',
            ]);

            $data = DB::transaction(function () use ($tax, $validated) {
                $tax->update($validated);
                return $tax->fresh();
            });

            return $this->responseSuccess($data, 'Tax updated successfully');
        } catch (ModelNotFoundException $err) {
            return $this->responseError(null, 'Tax not found', 404);
        } catch (Exception $err) {
            Log::error('Error updating Tax: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Tax update failed', 500);
        }
    }

    /**
     * Delete the specified tax.
     */
    public function destroy(string $id)
    {
        try {
            $tax = Tax::findOrFail($id);

            if ($tax->is_lock) {
                return $this->responseError(null, 'Cannot delete locked tax', 422);
            }

            DB::transaction(function () use ($tax) {
                $tax->TaxVersions()->delete();
                $tax->delete();
            });

            return $this->responseSuccess(null, 'Tax deleted successfully');
        } catch (ModelNotFoundException $err) {
            return $this->responseError(null, 'Tax not found', 404);
        } catch (Exception $err) {
            Log::error('Error deleting Tax: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Tax deletion failed', 500);
        }
    }
}
