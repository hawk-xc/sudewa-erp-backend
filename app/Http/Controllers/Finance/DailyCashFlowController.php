<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\CashFlow;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DailyCashFlowController extends Controller
{
    use ResponseTrait;

    protected $cashFlowTable;

    public function __construct()
    {
        $this->middleware(['permission:finance:list'])->only(['index', 'show']);
        $this->middleware(['permission:finance:create'])->only('store');
        $this->middleware(['permission:finance:edit'])->only('update');
        $this->middleware(['permission:finance:delete'])->only(['destroy']);

        $this->cashFlowTable = ['id', 'uuid', 'company_id', 'code', 'cash_id', 'date', 'note', 'debet', 'credit', 'created_at'];
    }

    public function index(Request $request)
    {
        $query = CashFlow::query();

        $query->with(['cash:id,uuid,code,description,type', 'company:id,uuid,name']);

        try {
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%$search%")
                        ->orWhere('note', 'like', "%$search%")
                        ->orWhereHas('cash', function ($qCash) use ($search) {
                            $qCash->where('code', 'like', "%$search%")
                                ->orWhere('description', 'like', "%$search%");
                        });
                });
            }

            foreach ($this->cashFlowTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $allowedSort = $this->cashFlowTable;
            $sortBy = in_array($request->sort_by, $allowedSort) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->per_page ?? 10;
            $data = $query->paginate($perPage);

            return $this->responseSuccess($data, 'Cash Flow list retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while retrieving Cash Flow data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Cash Flow list retrieved Failed', 500);
        }
    }

    public function show(string $id)
    {
        try {
            $cashFlow = CashFlow::with(['cash:id,uuid,code,description,type', 'company:id,uuid,name'])->find($id);

            if (! $cashFlow) {
                return $this->responseError(null, 'Cash Flow not found', 404);
            }

            return $this->responseSuccess($cashFlow, 'Cash Flow retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while retrieving Cash Flow data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Cash Flow retrieved Failed', 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|integer|exists:companies,id',
            'cash_id' => 'required|integer|exists:cashes,id',
            'date' => 'required|date',
            'note' => 'nullable|string',
            'debet' => 'required|numeric|min:0',
            'credit' => 'required|numeric|min:0',
        ]);

        try {
            $cashFlow = DB::transaction(function () use ($validated) {
                return CashFlow::create($validated);
            });

            return $this->responseSuccess($cashFlow, 'Cash Flow created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error while creating Cash Flow : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Error while creating Cash Flow', 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'company_id' => 'sometimes|integer|exists:companies,id',
            'cash_id' => 'sometimes|integer|exists:cashes,id',
            'date' => 'sometimes|date',
            'note' => 'nullable|string',
            'debet' => 'sometimes|numeric|min:0',
            'credit' => 'sometimes|numeric|min:0',
        ]);

        try {
            $data = array_filter($request->only(['company_id', 'cash_id', 'date', 'note', 'debet', 'credit']), fn ($value) => ! is_null($value) && $value !== '');

            if (empty($data)) {
                return $this->responseError(null, 'No data provided to update', 422);
            }

            $cashFlow = DB::transaction(function () use ($id, $data) {
                $cashFlow = CashFlow::findOrFail($id);
                $cashFlow->update($data);

                return $cashFlow->fresh();
            });

            return $this->responseSuccess($cashFlow, 'Cash Flow updated successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while updating Cash Flow : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Error while updating Cash Flow', 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $cashFlow = CashFlow::findOrFail($id);
            $cashFlow->delete();

            return $this->responseSuccess([], 'Cash Flow deleted successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while deleting Cash Flow : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Cash Flow deleted Failed', 500);
        }
    }
}
