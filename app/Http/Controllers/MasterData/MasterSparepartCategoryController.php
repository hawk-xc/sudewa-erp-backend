<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\SparepartCategory;
use App\Repositories\AuthRepository;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @group Master Data
 *
 * API for managing sparepart categories.
 */
class MasterSparepartCategoryController extends Controller
{
    use ResponseTrait;

    protected $sparepartCategoryTable;

    protected AuthRepository $authRepository;

    public function __construct(AuthRepository $ar)
    {
        $this->middleware(['permission:master-data:list'])->only(['index', 'show', 'export']);
        $this->middleware(['permission:master-data:create'])->only('store', 'import');
        $this->middleware(['permission:master-data:edit'])->only('update');
        $this->middleware(['permission:master-data:delete'])->only(['destroy']);

        $this->authRepository = $ar;

        $this->sparepartCategoryTable = ['id', 'uuid', 'code', 'name', 'created_at'];
    }

    /**
     * List all sparepart categories.
     */
    public function index(Request $request)
    {
        try {
            $query = SparepartCategory::query();

            $query->select($this->sparepartCategoryTable);

            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%");
                });
            }

            $sparepartCategories = $request->filled('per_page') ? $query->paginate($request->per_page) : $query->get();

            return $this->responseSuccess($sparepartCategories, 'Sparepart Categories retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying get Sparepart Categories : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Internal Server Error', 500);
        }
    }

    /**
     * Get sparepart category details.
     */
    public function show($id)
    {
        try {
            $sparepartCategory = SparepartCategory::with('spareparts')->findOrFail($id);

            return $this->responseSuccess($sparepartCategory, 'Sparepart Category retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying get Sparepart Category : '.$err->getMessage());

            return $this->responseError('The requested resource could not be found.', 'Resource Not Found', 404);
        }
    }

    /**
     * Store a new sparepart category.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:sparepart_categories,code|max:100',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        try {
            $sparepartCategory = DB::transaction(function () use ($validated) {
                $sparepartCategory = SparepartCategory::create($validated);

                return $sparepartCategory;
            });

            return $this->responseSuccess($sparepartCategory, 'Sparepart Category created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error while trying create Sparepart Category : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Failed to create Sparepart Category', 500);
        }
    }

    /**
     * Update a sparepart category.
     */
    public function update(Request $request, $id)
    {
        $sparepartCategory = SparepartCategory::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        try {
            $sparepartCategory->update($validated);

            return $this->responseSuccess($sparepartCategory, 'Sparepart Category updated successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying update Sparepart Category : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Internal Server Error', 500);
        }
    }

    /**
     * Delete a sparepart category.
     */
    public function destroy($id)
    {
        try {
            $sparepartCategory = SparepartCategory::findOrFail($id);
            $sparepartCategory->delete();

            return $this->responseSuccess(null, 'Sparepart Category deleted successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying delete Sparepart Category : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Internal Server Error', 500);
        }
    }
}
