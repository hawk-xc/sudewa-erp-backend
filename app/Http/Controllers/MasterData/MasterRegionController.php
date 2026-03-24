<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Imports\RegionImport;
use App\Models\Region;
use App\Repositories\AuthRepository;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class MasterRegionController extends Controller
{
    use ResponseTrait;

    protected $RegionTable;

    protected AuthRepository $authRepository;

    public function __construct(AuthRepository $ar)
    {
        $this->middleware(['permission:master-data:list'])->only(['index', 'show']);
        $this->middleware(['permission:master-data:create'])->only('store');
        $this->middleware(['permission:master-data:edit'])->only('update');
        $this->middleware(['permission:master-data:delete'])->only(['destroy']);

        $this->authRepository = $ar;

        $this->RegionTable = ['id', 'uuid', 'code', 'name', 'created_at'];
    }

    public function index(Request $request)
    {
        try {
            $query = Region::query();

            $query->select($this->RegionTable);

            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%");
                });
            }

            $regions = $request->filled('per_page') ? $query->paginate($request->per_page) : $query->get();

            return $this->responseSuccess($regions, 'Regions retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying get Regions : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function show($id)
    {
        try {
            $Region = Region::findOrFail($id);

            return $this->responseSuccess($Region, 'Region retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying get Region : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:regions,code|max:100',
            'name' => 'required|string|max:255',
        ]);

        try {
            $Region = DB::transaction(function () use ($validated) {
                $Region = Region::create($validated);

                return $Region;
            });

            return $this->responseSuccess($Region, 'Region created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error while trying create Region : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Failed to create Region', 500);
        }
    }

    public function update(Request $request, $id)
    {
        $Region = Region::findOrFail($id);

        $validated = $request->validate([
            'code' => 'required|string|max:255|unique:regions,code,'.$id,
            'name' => 'required|string|max:255',
        ]);

        try {
            $Region->update($validated);

            return $this->responseSuccess($Region, 'Region updated successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying update Region : '.$err->getMessage());

            return $this->responseError(null, 'Internal Server Error', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $Region = Region::findOrFail($id);
            $Region->delete();

            return $this->responseSuccess(null, 'Region deleted successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying delete Region : '.$err->getMessage());

            return $this->responseError(null, 'Internal Server Error', 500);
        }
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        try {
            Excel::import(new RegionImport, $request->file('file'));

            return $this->responseSuccess(null, 'Region imported successfully', 201);
        } catch (Exception $err) {
            Log::error('Region import error', [
                'message' => $err->getMessage(),
            ]);

            return $this->responseError(null, $err->getMessage(), 500);
        }
    }
}
