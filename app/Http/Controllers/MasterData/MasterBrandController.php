<?php

namespace App\Http\Controllers\MasterData;

use App\Models\Brand;
use App\Traits\ResponseTrait;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Repositories\AuthRepository;
use Intervention\Image\ImageManager;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;

class MasterBrandController extends Controller
{
    use ResponseTrait;

    protected $brandTable;

    /**
     * @var AuthRepository
     */
    protected AuthRepository $authRepository;

    public function __construct(AuthRepository $ar)
    {
        $this->middleware(['permission:master-data:list'])->only(['index', 'show']);
        $this->middleware(['permission:master-data:create'])->only('store');
        $this->middleware(['permission:master-data:edit'])->only('update');
        $this->middleware(['permission:master-data:delete'])->only(['destroy']);

        $this->authRepository = $ar;

        $this->brandTable = ['id', 'name', 'image', 'created_at'];
    }

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
        } catch (\Exception $err) {
            Log::error("List Brand Error : " . $err->getMessage());
            return $this->responseError(null, 'Error while trying to list Brand', 500);
        }
    }

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
        } catch (\Exception $err) {
            Log::error('Error creating brand: ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Error creating brand', 500);
        }
    }

    public function show(string $id)
    {
        try {
            $brand = Brand::with('unitTypes')->findOrFail($id);

            return $this->responseSuccess($brand, 'Brand retrieved successfully', 200);
        } catch (\Exception $err) {
            return $this->responseError(null, 'Brand not found', 404);
        }
    }

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
        } catch (\Exception $err) {
            Log::error('Error updating brand: ' . $err->getMessage());

            return $this->responseError(null, 'Error updating brand', 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $brand = Brand::findOrFail($id);

            DB::transaction(function () use ($brand) {
                $brand->delete();
            });

            return $this->responseSuccess(null, 'Brand deleted successfully', 200);
        } catch (\Exception $err) {
            return $this->responseError(null, 'Brand not found or cannot be deleted', 404);
        }
    }
}
