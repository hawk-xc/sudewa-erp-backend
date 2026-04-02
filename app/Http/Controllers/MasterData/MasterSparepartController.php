<?php

namespace App\Http\Controllers\MasterData;

use App\Exports\SparepartExport;
use App\Http\Controllers\Controller;
use App\Imports\SparepartImport;
use App\Models\Sparepart;
use App\Repositories\AuthRepository;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class MasterSparepartController extends Controller
{
    use ResponseTrait;

    protected $sparepartTable;

    protected AuthRepository $authRepository;

    public function __construct(AuthRepository $ar)
    {
        $this->middleware(['permission:master-data:list'])->only(['index', 'show']);
        $this->middleware(['permission:master-data:create'])->only('store');
        $this->middleware(['permission:master-data:edit'])->only('update');
        $this->middleware(['permission:master-data:delete'])->only(['destroy']);

        $this->authRepository = $ar;

        $this->sparepartTable = ['id', 'sparepart_category_id', 'code', 'name', 'capacity', 'unit_type', 'buy_price', 'sell_price', 'created_at'];
    }

    public function index(Request $request)
    {
        try {
            $query = Sparepart::with('sparepartCategory:id,name');

            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%");
                });
            }

            if ($request->filled('unit_type')) {
                $query->where('unit_type', $request->unit_type);
            }

            if ($request->filled('model_type')) {
                $query->where('model_type', $request->model_type);
            }

            if ($request->filled('min_netto_weight')) {
                $query->where('netto_weight', '>=', $request->min_netto_weight);
            }

            if ($request->filled('max_netto_weight')) {
                $query->where('netto_weight', '<=', $request->max_netto_weight);
            }

            if ($request->filled('min_bruto_weight')) {
                $query->where('bruto_weight', '>=', $request->min_bruto_weight);
            }

            if ($request->filled('max_bruto_weight')) {
                $query->where('bruto_weight', '<=', $request->max_bruto_weight);
            }

            $unitTypes = $request->filled('per_page') ? $query->paginate($request->per_page) : $query->get();

            return $this->responseSuccess($unitTypes, 'Unit Types retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying get Unit Types : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function show($id)
    {
        try {
            $sparepart = Sparepart::with('sparepartCategory')->findOrFail($id);

            return $this->responseSuccess($sparepart, 'Sparepart retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying get Sparepart : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sparepart_category_id' => 'required|exists:sparepart_categories,id',
            'code' => 'required|string|unique:spareparts,code',
            'buy_price' => 'nullable|integer',
            'sell_price' => 'nullable|integer',
            'name' => 'required|string|max:255',
            'capacity' => 'nullable|decimal:0,2|max:100',
            'unit_type' => 'nullable|string|max:255|in:pcs,set,box',
        ]);

        try {
            $sparepart = DB::transaction(function () use ($validated) {
                $sparepart = Sparepart::create($validated);

                return $sparepart;
            });

            return $this->responseSuccess($sparepart, 'Sparepart created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error while trying create Sparepart : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Failed to create Sparepart', 500);
        }
    }

    public function update(Request $request, $id)
    {
        $sparepart = Sparepart::findOrFail($id);

        $validated = $request->validate([
            'sparepart_category_id' => 'sometimes|exists:sparepart_categories,id',
            'code' => 'sometimes|string|unique:spareparts,code,'.$sparepart->id,
            'buy_price' => 'nullable|integer',
            'sell_price' => 'nullable|integer',
            'name' => 'sometimes|string|max:255',
            'capacity' => 'nullable|decimal:0,2|max:100',
            'unit_type' => 'nullable|string|max:255|in:pcs,set,box',
        ]);

        try {
            $sparepart = DB::transaction(function () use ($validated, $sparepart) {
                $sparepart->update($validated);

                return $sparepart;
            });

            return $this->responseSuccess($sparepart->fresh(), 'Sparepart updated successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying update Sparepart : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $unitType = Sparepart::findOrFail($id);
            $unitType->delete();

            return $this->responseSuccess($unitType, 'Unit Type deleted successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying delete Unit Type : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        try {
            Excel::import(new SparepartImport, $request->file('file'));

            return $this->responseSuccess(null, 'Sparepart imported successfully', 201);
        } catch (Exception $err) {
            Log::error('Sparepart import error', [
                'message' => $err->getMessage(),
            ]);

            return $this->responseError(null, $err->getMessage(), 500);
        }
    }

    public function export(Request $request)
    {
        try {
            return Excel::download(
                new SparepartExport($request, $this->sparepartTable),
                'wajira_sparepart_data.xlsx'
            );  
        } catch (Exception $err) {
            Log::error('Error export sparepart : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Internal Server Error', 500);
            return $this->responseError(
                $err->getMessage(),
                'Sparepart export failed',
                500
            );
        }
    }
}
