<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Repositories\AuthRepository;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WarehouseController extends Controller
{
    use ResponseTrait;

    protected AuthRepository $authRepository;

    protected $warehouseTable;

    public function __construct(AuthRepository $ar)
    {
        $this->middleware(['permission:warehouse:list'])->only(['index', 'show']);
        $this->middleware(['permission:warehouse:create'])->only('store');
        $this->middleware(['permission:warehouse:edit'])->only('update');
        $this->middleware(['permission:warehouse:delete'])->only(['destroy']);

        $this->authRepository = $ar;

        $this->warehouseTable = [
            'id',
            'uuid',
            'company_id',
            'name',
            'capacity',
            'description',
            'created_at',
        ];
    }

    public function index(Request $request)
    {
        try {
            $query = Warehouse::with('company');

            $query->select($this->warehouseTable);

            if ($request->company_id) {
                $query->where('company_id', $request->company_id);
            }

            if ($request->search) {
                $query->where('name', 'like', '%'.$request->search.'%');
            }

            $data = $query->latest()->paginate($request->per_page ?? 10);

            return response()->json([
                'success' => true,
                'message' => 'Warehouses retrieved successfully',
                'data' => $data,
            ], 200);
        } catch (Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'capacity' => 'required|numeric',
            'description' => 'nullable|string',
        ]);

        try {
            $data = DB::transaction(function () use ($validated) {
                return Warehouse::create($validated);
            });

            return response()->json([
                'success' => true,
                'message' => 'Warehouse created successfully',
                'data' => $data,
            ], 201);
        } catch (Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $data = Warehouse::with('company')->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Warehouse retrieved successfully',
                'data' => $data,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Warehouse not found',
            ], 404);
        }
    }

    public function getUnitTransaction(Request $request, string $id)
    {
        $type = $request->type;
        $query = Warehouse::find($id)->unitTransactions();

        try {
            switch ($type) {
                case 'inbound':
                    $query = $query->where('type', 'purchase')->get();

                    return $this->responseSuccess($query, 'Unit transaction data successfully fetched', 200);
                    break;
                case 'outbound':
                    $query = $query->where('type', 'sales')->get();

                    return $this->responseSuccess($query, 'Unit transaction data successfully fetched', 200);
                    break;
                default:
                    $query = $query->get();

                    return $this->responseSuccess($query, 'Unit transaction data successfully fetched', 200);
                    break;
            }
        } catch (Exception $err) {
            Log::error('Fetch Warehouse Unit Transaction : '.$err->getMessage());

            return $this->responseError(null, $err->getMessage(), 500);
        }
    }

    public function getStock(Request $request, string $id)
    {
        try {
            $warehouse = Warehouse::with([
                'unitTransactions.unitTransactionItems.unitTransactionItemDetails',
            ])->findOrFail($id);

            $stockInHand = 0;
            $stockForecast = 0;

            foreach ($warehouse->unitTransactions as $transaction) {

                $totalDetails = $transaction->unitTransactionItems
                    ->sum(fn ($item) => $item->unitTransactionItemDetails->count());

                if ($transaction->type === 'purchase' && $transaction->is_stock_in_hand) {
                    $stockInHand += $totalDetails;
                } else {
                    $stockForecast += $totalDetails;
                }
            }

            return $this->responseSuccess([
                'stock_in_hand' => $stockInHand,
                'stock_forecast' => $stockForecast,
            ], 'Successfully fetch warehouse stock data', 200);

        } catch (Exception $err) {
            Log::error('Error while fetch warehouse stock data : '.$err->getMessage());

            return $this->responseError(null, $err->getMessage(), 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $warehouse = Warehouse::findOrFail($id);

        $validated = $request->validate([
            'company_id' => 'sometimes|exists:companies,id', $id,
            'name' => 'sometimes|string|max:255',
            'capacity' => 'sometimes|numeric',
            'description' => 'sometimes|string',
        ]);

        try {
            DB::transaction(function () use ($validated, $warehouse) {
                $warehouse->update($validated);
            });

            return response()->json([
                'success' => true,
                'message' => 'Warehouse updated successfully',
                'data' => $warehouse->fresh(),
            ], 200);
        } catch (Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
            ], 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $data = Warehouse::findOrFail($id);

            DB::transaction(function () use ($data) {
                $data->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Warehouse deleted successfully',
            ], 200);
        } catch (Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
            ], 500);
        }
    }
}
