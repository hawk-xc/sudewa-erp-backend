<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
// use App\Imports\CashImport;
use App\Models\Cash;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use App\Exports\CashExport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\JsonResponse;

class MasterCashController extends Controller
{
    use ResponseTrait;

    protected $cashTable = ['id', 'uuid', 'company_id', 'account_id', 'code', 'description', 'type', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        try {
            $query = Cash::query()->select($this->cashTable)->with('account');

            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%$search%")
                        ->orWhere('description', 'like', "%$search%")
                        ->orWhere('type', 'like', "%$search%");
                });
            }

            foreach (['company_id', 'type', 'code'] as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $sortBy = in_array($request->sort_by, $this->cashTable)
                ? $request->sort_by
                : 'id';

            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $perPage = min($request->per_page ?? 10, 100);

            $data = $query->paginate($perPage);

            return $this->responseSuccess(
                $data,
                'Cash list retrieved successfully',
                200
            );

        } catch (\Exception $e) {
            Log::error('Error retrieving cash list: ' . $e->getMessage());

            return $this->responseError(
                $e->getMessage(),
                'Failed to retrieve cash list',
                500
            );
        }
    }

    // public function store(Request $request)
    // {
    //     try {
    //         $validated = $request->validate([
    //             'company_id' => 'required|exists:companies,id',
    //             'account_id' => 'nullable|exists:accounts,id',
    //             'code' => 'required|string|max:50|unique:cashes,code',
    //             'description' => 'nullable|string',
    //             'type' => 'required|string|max:50|in:cash,bank',
    //         ]);

    //         $cash = DB::transaction(function () use ($validated) {
    //             return Cash::create($validated);
    //         });

    //         return $this->responseSuccess(
    //             $cash->fresh(),
    //             'Cash created successfully',
    //             201
    //         );

    //     } catch (\Illuminate\Validation\ValidationException $e) {
    //         return $this->responseError(
    //             $e->errors(),
    //             'Validation failed while creating cash',
    //             422
    //         );

    //     } catch (\Exception $e) {
    //         Log::error('Error storing cash: ' . $e->getMessage());

    //         return $this->responseError(
    //             $e->getMessage(),
    //             'Failed to create cash',
    //             500
    //         );
    //     }
    // }

    public function show(string $id)
    {
        try {
            $cash = Cash::select($this->cashTable)->with(['company', 'account'])->findOrFail($id);

            return $this->responseSuccess(
                $cash,
                'Cash retrieved successfully',
                200
            );

        } catch (\Exception $e) {
            return $this->responseError(
                $e->getMessage(),
                'Cash not found',
                404
            );
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $cash = Cash::findOrFail($id);

            $validated = $request->validate([
                'account_id' => 'nullable|exists:accounts,id',
                'code' => 'sometimes|required|string|max:50|unique:cashes,code,' . $id,
                'description' => 'nullable|string',
                'type' => 'sometimes|required|string|max:50',
            ]);

            DB::transaction(function () use ($cash, $validated) {
                $cash->update($validated);
            });

            return $this->responseSuccess(
                $cash->fresh(),
                'Cash updated successfully',
                200
            );

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->responseError(
                $e->errors(),
                'Validation failed while updating cash',
                422
            );

        } catch (\Exception $e) {
            Log::error('Error updating cash: ' . $e->getMessage());

            return $this->responseError(
                $e->getMessage(),
                'Failed to update cash',
                500
            );
        }
    }

    // public function destroy(string $id)
    // {
    //     try {
    //         $cash = Cash::findOrFail($id);

    //         DB::transaction(function () use ($cash) {
    //             $cash->delete();
    //         });

    //         return $this->responseSuccess(
    //             null,
    //             'Cash deleted successfully',
    //             200
    //         );

    //     } catch (\Exception $e) {
    //         Log::error('Error deleting cash: ' . $e->getMessage());

    //         return $this->responseError(
    //             $e->getMessage(),
    //             'Failed to delete cash',
    //             500
    //         );
    //     }
    // }

    // public function import(Request $request)
    // {
    //     $request->validate([
    //         'company_id' => 'required|exists:companies,id',
    //         'file' => 'required|file|mimes:xlsx,xls',
    //     ]);

    //     try {
    //         Excel::import(new CashImport((int) $request->company_id), $request->file('file'));

    //         return $this->responseSuccess(null, 'Cash data imported successfully', 201);
    //     } catch (\Exception $e) {
    //         Log::error('Cash import error: ' . $e->getMessage());

    //         return $this->responseError(
    //             $e->getMessage(),
    //             'Cash data imported failed',
    //             500
    //         );
    //     }
    // }

    public function export(Request $request)
    {
        try {
            return Excel::download(
                new CashExport($request, $this->cashTable),
                'wajira_cash_data.xlsx'
            );
        } catch (\Exception $err) {
            Log::error('Error export cash : ' . $err->getMessage());

            return $this->responseError(
                $err->getMessage(),
                'Cash export failed',
                500
            );
        }
    }
}
