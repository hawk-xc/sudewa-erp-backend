<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\WarehouseActivity;
use App\Repositories\AuthRepository;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WarehouseActivityController extends Controller
{
    use ResponseTrait;

    protected AuthRepository $authRepository;

    protected $activityTable;

    public function __construct(AuthRepository $ar)
    {
        $this->middleware(['permission:warehouse:list'])->only(['index', 'show']);
        $this->middleware(['permission:warehouse:create'])->only('store');
        $this->middleware(['permission:warehouse:edit'])->only('update');
        $this->middleware(['permission:warehouse:delete'])->only(['destroy']);

        $this->authRepository = $ar;

        $this->activityTable = [
            'id',
            'uuid',
            'person_id',
            'warehouse_id',
            'activity_number',
            'activity_type',
            'activity_date',
            'description',
            'created_at',
        ];
    }

    public function index(Request $request)
    {
        try {

            $query = WarehouseActivity::with([
                'warehouse:id,uuid,name',
                'person:id,uuid,name',
            ]);

            $query->select($this->activityTable);

            if ($request->warehouse_id) {
                $query->where('warehouse_id', $request->warehouse_id);
            }

            if ($request->person_id) {
                $query->where('person_id', $request->person_id);
            }

            if ($request->activity_type) {
                $query->where('activity_type', $request->activity_type);
            }

            if ($request->date_from && $request->date_to) {
                $query->whereBetween('activity_date', [
                    $request->date_from,
                    $request->date_to,
                ]);
            }

            if ($request->search) {
                $query->where('activity_number', 'like', '%'.$request->search.'%');
            }

            $data = $query
                ->latest()
                ->paginate($request->per_page ?? 10);

            return response()->json([
                'success' => true,
                'message' => 'Warehouse activities retrieved successfully',
                'data' => $data,
            ], 200);

        } catch (Exception $e) {

            Log::error('WarehouseActivity index error : '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'person_id' => 'required|exists:people,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'activity_type' => 'required|in:inbound,outbound',
            'activity_date' => 'required|date',
            'description' => 'nullable|string',
        ]);

        try {

            $data = DB::transaction(function () use ($validated) {
                return WarehouseActivity::create($validated);
            });

            return response()->json([
                'success' => true,
                'message' => 'Warehouse activity created successfully',
                'data' => $data,
            ], 201);

        } catch (Exception $e) {

            Log::error('WarehouseActivity store error : '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {

            $data = WarehouseActivity::with([
                'warehouse:id,uuid,name',
                'person:id,uuid,name',
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Warehouse activity retrieved successfully',
                'data' => $data,
            ], 200);

        } catch (Exception $e) {

            Log::error('WarehouseActivity show error : '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Warehouse activity not found',
            ], 404);
        }
    }

    public function update(Request $request, string $id)
    {
        $activity = WarehouseActivity::findOrFail($id);

        $validated = $request->validate([
            'person_id' => 'sometimes|exists:people,id',
            'warehouse_id' => 'sometimes|exists:warehouses,id',
            'activity_type' => 'sometimes|in:inbound,outbound',
            'activity_date' => 'sometimes|date',
            'description' => 'sometimes|string',
        ]);

        try {

            DB::transaction(function () use ($validated, $activity) {

                $activity->update($validated);

            });

            return response()->json([
                'success' => true,
                'message' => 'Warehouse activity updated successfully',
                'data' => $activity->fresh(),
            ], 200);

        } catch (Exception $e) {

            Log::error('WarehouseActivity update error : '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
            ], 500);
        }
    }

    public function destroy(string $id)
    {
        try {

            $data = WarehouseActivity::findOrFail($id);

            DB::transaction(function () use ($data) {

                $data->delete();

            });

            return response()->json([
                'success' => true,
                'message' => 'Warehouse activity deleted successfully',
            ], 200);

        } catch (Exception $e) {

            Log::error('WarehouseActivity destroy error : '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
            ], 500);
        }
    }
}
