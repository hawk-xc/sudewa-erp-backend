<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\UnitTypePriceVersion;
use App\Repositories\AuthRepository;
use App\Traits\GlobalCodeNumberTrait;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * @group Master Data
 *
 * API for managing unit type price versions.
 */
class MasterUnitTypePriceVersion extends Controller
{
    use ResponseTrait, GlobalCodeNumberTrait;

    protected AuthRepository $authRepository;

    // projection
    protected array $priceVersionTable;

    /**
     * MasterUnitTypePriceVersion constructor.
     */
    public function __construct(AuthRepository $ar)
    {
        $this->middleware(['permission:master-data:list|master-data:read'])->only('index');
        $this->middleware(['permission:master-data:list|master-data:read'])->only('show');
        $this->middleware(['permission:master-data:create'])->only('store');
        $this->middleware(['permission:master-data:edit'])->only('update');
        $this->middleware(['permission:master-data:delete'])->only(['destroy']);

        $this->authRepository = $ar;

        $this->priceVersionTable = [
            'id',
            'uuid',
            'unit_type_id',
            'name',
            'buy_price',
            'sell_price',
            'effective_from',
            'effective_until',
            'is_default',
            'is_lock',
            'created_at'
        ];
    }

    /**
     * List all unit type price versions.
     */
    public function index(Request $request)
    {
        try {
            $query = UnitTypePriceVersion::query()->with('unitType:id,name');

            $query->select($this->priceVersionTable);

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
            }

            if ($request->filled('unit_type_id')) {
                $query->where('unit_type_id', $request->unit_type_id);
            }

            if ($request->filled('is_default')) {
                $query->where('is_default', $request->boolean('is_default'));
            }

            if ($request->filled('is_lock')) {
                $query->where('is_lock', $request->boolean('is_lock'));
            }

            foreach ($this->priceVersionTable as $field) {
                if ($request->filled($field) && !in_array($field, ['is_default', 'is_lock'])) {
                    $query->where($field, $request->$field);
                }
            }

            $allowedSort = $this->priceVersionTable;
            $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->per_page ?? 10;
            $data = $query->paginate($perPage);

            return $this->responseSuccess($data, 'Unit Type Price Version list retrieved successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while retrieving Unit Type Price Version data: ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Unit Type Price Version list retrieved failed', 500);
        }
    }

    /**
     * Get unit type price version details.
     */
    public function show(string $id)
    {
        try {
            $priceVersion = UnitTypePriceVersion::with('unitType:id,name')->select($this->priceVersionTable)->findOrFail((int) $id);

            return $this->responseSuccess($priceVersion, 'Unit Type Price Version retrieved successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            return $this->responseError('The requested resource could not be found.', 'Resource Not Found', 404);
        }
    }

    /**
     * Store a new unit type price version.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'unit_type_id' => 'required|integer|exists:unit_types,id',
                'name' => 'nullable|string|max:255',
                'buy_price' => 'required|integer|min:0',
                'sell_price' => 'required|integer|min:0',
                'effective_from' => 'nullable|date',
                'effective_until' => 'nullable|date|after_or_equal:effective_from',
                'is_default' => 'sometimes|in:1,0',
            ]);

            $priceVersion = DB::transaction(function () use ($validated) {
                if ($validated['is_default'] ?? false) {
                    UnitTypePriceVersion::where('unit_type_id', $validated['unit_type_id'])
                        ->where('is_default', true)
                        ->update(['is_default' => false]);
                }

                return UnitTypePriceVersion::create($validated);
            });

            return $this->responseSuccess($priceVersion->fresh('unitType'), 'Unit Type Price Version created successfully', 201);
        } catch (ValidationException $e) {
            return $this->responseError($e->errors(), 'Validation failed', 422);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while storing Unit Type Price Version: ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Unit Type Price Version creation failed', 500);
        }
    }

    /**
     * Update a unit type price version.
     */
    public function update(Request $request, string $id)
    {
        try {
            $priceVersion = UnitTypePriceVersion::findOrFail((int) $id);

            if ($priceVersion->is_lock) {
                $validated = $request->validate([
                    'name' => 'sometimes|nullable|string|max:255',
                    'effective_from' => 'sometimes|nullable|date',
                    'effective_until' => 'sometimes|nullable|date|after_or_equal:effective_from',
                    'is_default' => 'sometimes|in:1,0',
                ]);
            } else {
                $validated = $request->validate([
                    'unit_type_id' => 'sometimes|integer|exists:unit_types,id',
                    'name' => 'sometimes|nullable|string|max:255',
                    'buy_price' => 'sometimes|required|integer|min:0',
                    'sell_price' => 'sometimes|required|integer|min:0',
                    'effective_from' => 'sometimes|nullable|date',
                    'effective_until' => 'sometimes|nullable|date|after_or_equal:effective_from',
                    'is_default' => 'sometimes|in:1,0',
                ]);
            }

            if (isset($validated['is_default']) && !$validated['is_default'] && $priceVersion->is_default) {
                return $this->responseError('At least one price version must be set as default.', 'Validation Error', 422);
            }

            DB::transaction(function () use ($priceVersion, $validated) {
                if ($validated['is_default'] ?? false) {
                    $unitTypeId = $validated['unit_type_id'] ?? $priceVersion->unit_type_id;
                    UnitTypePriceVersion::where('unit_type_id', $unitTypeId)
                        ->where('id', '!=', $priceVersion->id)
                        ->where('is_default', true)
                        ->update(['is_default' => false]);
                }

                $priceVersion->update($validated);
            });

            return $this->responseSuccess($priceVersion->fresh('unitType'), 'Unit Type Price Version updated successfully', 200);
        } catch (ValidationException $e) {
            return $this->responseError($e->errors(), 'Validation failed', 422);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while updating Unit Type Price Version: ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Unit Type Price Version update failed', 500);
        }
    }

    /**
     * Delete a unit type price version.
     */
    public function destroy(string $id)
    {
        try {
            $priceVersion = UnitTypePriceVersion::findOrFail((int) $id);

            if ($priceVersion->is_lock) {
                return $this->responseError('Locked price versions cannot be deleted.', 'Action Forbidden', 403);
            }

            if ($priceVersion->is_default) {
                return $this->responseError('Default price version cannot be deleted. A unit type must have at least one default price version.', 'Action Forbidden', 403);
            }

            DB::transaction(function () use ($priceVersion) {
                $priceVersion->delete();
            });

            return $this->responseSuccess(null, 'Unit Type Price Version deleted successfully', 200);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error while deleting Unit Type Price Version: ' . $err->getMessage());

            return $this->responseError($err->getMessage(), 'Unit Type Price Version deletion failed', 500);
        }
    }
}
