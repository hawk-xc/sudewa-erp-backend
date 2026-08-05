<?php

namespace App\Http\Controllers\MasterData;

use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Traits\GlobalCodeNumberTrait;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\UnitType;
use App\Repositories\AuthRepository;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * @group Master Data
 *
 * API for managing brands.
 */
class MasterBrandController extends Controller
{
    use ResponseTrait, GlobalCodeNumberTrait;

    protected $brandTable;

    protected AuthRepository $authRepository;

    public function __construct(AuthRepository $ar)
    {
        $this->middleware(['permission:master-data:list|master-data:read'])->only('index');
        $this->middleware(['permission:master-data:list|master-data:read'])->only(['show', 'export']);
        $this->middleware(['permission:master-data:create'])->only('store', 'import');
        $this->middleware(['permission:master-data:edit'])->only('update');
        $this->middleware(['permission:master-data:delete'])->only(['destroy']);

        $this->authRepository = $ar;

        $this->brandTable = ['id', 'name', 'image', 'created_at'];
    }

    /**
     * List all brands.
     */
    public function index(Request $request)
    {
        try {
            $query = Brand::query();

            $query->select($this->brandTable);

            if ($request->filled('search')) {
                $search = $request->search;
                $caseSensitive = $request->boolean('case_sensitive');

                if ($caseSensitive) {
                    $query->where('name', 'LIKE BINARY', "%$search%");
                } else {
                    $query->where('name', 'like', "%$search%");
                }
            }

            $filterable = ['name'];

            foreach ($filterable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $allowedSort = ['id', 'name', 'created_at'];

            $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'id';

            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->per_page ?? 10;

            $data = $query->paginate($perPage);

            return $this->responseSuccess($data, 'Brand list retrieved successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error('List Brand Error : ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Error while trying to list Brand', 500);
        }
    }

    /**
     * Store a new brand.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:249|unique:brands,name',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        try {
            $brand = DB::transaction(function () use ($request) {
                $imagePath = null;

                if ($request->hasFile('image')) {
                    $manager = new ImageManager(new Driver());

                    $file = $request->file('image');

                    $fileName = Str::uuid() . '.webp';

                    $image = $manager->read($file)->toWebp(90);

                    Storage::disk('public')->put('brands/' . $fileName, $image->toString());

                    $imagePath = 'brands/' . $fileName;
                }

                return Brand::create([
                    'name' => $request->name,
                    'image' => $imagePath,
                ]);
            });

            return $this->responseSuccess($brand, 'Brand created successfully', 201);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error('Error creating brand: ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Error creating brand', 500);
        }
    }

    /**
     * Get brand details.
     */
    public function show(Request $request, string $id)
    {
        try {
            $brand = Brand::findOrFail($id);

            $unitTypeQuery = UnitType::where('brand_id', $brand->id);

            if ($request->filled('search')) {
                $search = $request->search;

                $unitTypeQuery->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('unit_type', 'like', "%{$search}%")
                        ->orWhere('unit_model', 'like', "%{$search}%");
                });
            }

            if ($request->filled('name')) {
                $unitTypeQuery->where('name', 'like', "%{$request->name}%");
            }

            if ($request->filled('unit_type')) {
                $unitTypeQuery->where('unit_type', 'like', "%{$request->unit_type}%");
            }

            if ($request->filled('unit_model')) {
                $unitTypeQuery->where('unit_model', 'like', "%{$request->unit_model}%");
            }

            $sortBy = $request->get('sort_by', 'id');
            $sortDir = $request->get('sort_dir', 'desc');

            $allowedSort = ['id', 'name', 'unit_type', 'unit_model', 'created_at'];

            if (!in_array($sortBy, $allowedSort)) {
                $sortBy = 'id';
            }

            $unitTypeQuery->orderBy($sortBy, $sortDir);

            $perPage = $request->get('per_page', 10);

            $brand->unit_types = $unitTypeQuery->paginate($perPage);

            return $this->responseSuccess($brand, 'Brand retrieved successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            return $this->responseError('The requested resource could not be found.', 'Resource Not Found', 404);
        }
    }

    /**
     * Update a brand.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'sometimes|string|max:249|unique:brands,name,' . $id,
            'image' => 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        try {
            $brand = Brand::findOrFail($id);

            $data = [];

            if ($request->filled('name')) {
                $data['name'] = $request->name;
            }

            if ($request->hasFile('image')) {
                $manager = new ImageManager(new Driver());

                $file = $request->file('image');
                $fileName = Str::uuid() . '.webp';

                $image = $manager->read($file)->toWebp(90);

                Storage::disk('public')->put('brands/' . $fileName, $image->toString());

                if ($brand->image && Storage::disk('public')->exists($brand->image)) {
                    Storage::disk('public')->delete($brand->image);
                }

                $data['image'] = 'brands/' . $fileName;
            }

            if (empty($data)) {
                return $this->responseError(null, 'No data provided to update', 422);
            }

            DB::transaction(function () use ($brand, $data) {
                $brand->update($data);
            });

            return $this->responseSuccess($brand->fresh(), 'Brand updated successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            Log::error('Error updating brand: ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Error updating brand', 500);
        }
    }

    /**
     * Delete a brand.
     */
    public function destroy(string $id)
    {
        try {
            $brand = Brand::findOrFail($id);

            DB::transaction(function () use ($brand) {
                $brand->delete();
            });

            return $this->responseSuccess(null, 'Brand deleted successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (\Exception $err) {
            return $this->responseError('The requested resource could not be found.', 'Resource Not Found', 404);
        }
    }
}
