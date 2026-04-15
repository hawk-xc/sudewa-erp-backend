<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Imports\MaterialImport;
use App\Models\Material;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @group Master Data
 *
 * API for managing materials.
 */
class MasterMaterialController extends Controller
{
    use ResponseTrait;

    protected $materialTable = [
        'id',
        'uuid',
        'code',
        'name',
        'price',
        'type',
        'created_at',
    ];

    /**
     * List all materials.
     */
    public function index(Request $request)
    {
        $query = Material::query()->select($this->materialTable);

        try {
            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%")
                        ->orWhere('code', 'like', "%$search%");
                });
            }

            foreach ($this->materialTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $sortBy = in_array($request->sort_by, $this->materialTable)
                ? $request->sort_by
                : 'id';

            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $data = $query->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'Material list retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error get materials: '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Failed to retrieve materials', 500);
        }
    }

    /**
     * Get material details.
     */
    public function show(string $id)
    {
        try {
            $material = Material::select($this->materialTable)->find($id);

            if (! $material) {
                return $this->responseError(null, 'Material not found', 404);
            }

            return $this->responseSuccess($material, 'Material retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error get material: '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Failed to retrieve material', 500);
        }
    }

    /**
     * Store a new material.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:50|unique:materials,code',
            'name' => 'required|string|max:255',
            'price' => 'required|numeric',
            'type' => 'required|string|max:100|in:pcs,set,box',
        ]);

        try {
            $material = DB::transaction(function () use ($validated) {

                // AUTO GENERATE CODE jika kosong
                if (empty($validated['code'])) {
                    $validated['code'] = $this->generateCode();
                }

                return Material::create($validated);
            });

            return $this->responseSuccess($material, 'Material created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error create material: '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Failed to create material', 500);
        }
    }

    /**
     * Update a material.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'code' => 'sometimes|string|max:50|unique:materials,code,'.$id,
            'name' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric',
            'type' => 'sometimes|string|max:100|in:pcs,set,box',
        ]);

        try {
            $data = array_filter(
                $request->only(['code', 'name', 'price', 'type']),
                fn ($val) => ! is_null($val) && $val !== ''
            );

            if (empty($data)) {
                return $this->responseError(null, 'No data provided to update', 422);
            }

            $material = DB::transaction(function () use ($id, $data) {
                $material = Material::findOrFail($id);
                $material->update($data);

                return $material->fresh();
            });

            return $this->responseSuccess($material, 'Material updated successfully', 200);
        } catch (Exception $err) {
            Log::error('Error update material: '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Failed to update material', 500);
        }
    }

    /**
     * Delete a material.
     */
    public function destroy(string $id)
    {
        try {
            $material = Material::findOrFail($id);
            $material->delete();

            return $this->responseSuccess([], 'Material deleted successfully', 200);
        } catch (Exception $err) {
            Log::error('Error delete material: '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Failed to delete material', 500);
        }
    }

    private function generateCode()
    {
        $last = Material::where('code', 'like', 'TM-%')
            ->orderByDesc('id')
            ->first();

        if (! $last) {
            return 'TM-001';
        }

        $lastNumber = (int) substr($last->code, 3);
        $nextNumber = $lastNumber + 1;

        return 'TM-'.str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Import materials from Excel.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        try {
            Excel::import(new MaterialImport, $request->file('file'));

            return $this->responseSuccess(null, 'Material imported successfully', 201);
        } catch (Exception $err) {
            Log::error('Material import error', [
                'message' => $err->getMessage(),
            ]);

            return $this->responseError($err->getMessage(), 'Material import error', 500);
        }
    }
}
