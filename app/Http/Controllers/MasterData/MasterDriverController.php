<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Imports\PersonImport;
use App\Models\Person;
use App\Repositories\AuthRepository;
use App\Exports\PersonExport;
use App\Traits\PersonTrait;
use App\Traits\ResponseTrait;
use App\Traits\FileTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @group Master Data
 *
 * API for managing drivers.
 */
class MasterDriverController extends Controller
{
    use PersonTrait, ResponseTrait, FileTrait;

    protected AuthRepository $authRepository;

    // projection
    protected $personTable;

    public function __construct(AuthRepository $ar)
    {
        $this->middleware(['permission:master-data:list'])->only(['index', 'show']);
        $this->middleware(['permission:master-data:create'])->only('store');
        $this->middleware(['permission:master-data:edit'])->only('update');
        $this->middleware(['permission:master-data:delete'])->only(['destroy']);

        $this->authRepository = $ar;

        $this->personTable = ['id', 'uuid', 'pic_name', 'code', 'type', 'name', 'address', 'npwp', 'phone', 'identity_number', 'drive_license_identity_number', 'image', 'map_link', 'social_media_1_link', 'social_media_2_link', 'social_media_3_link', 'social_media_4_link', 'website_link', 'join_date', 'created_at'];
    }

    /**
     * List all drivers.
     */
    public function index(Request $request)
    {
        $query = Person::query();

        $query->select($this->personTable)->where('type', 'driver');

        try {
            if ($request->filled('search')) {

                $search = $request->search;
                $caseSensitive = $request->boolean('case_sensitive');

                $query->where(function ($q) use ($search, $caseSensitive) {

                    if ($caseSensitive) {
                        $q->where('name', 'LIKE BINARY', "%$search%")
                            ->orWhere('code', 'LIKE BINARY', "%$search%")
                            ->orWhere('phone', 'LIKE BINARY', "%$search%")
                            ->orWhere('npwp', 'LIKE BINARY', "%$search%");
                    } else {
                        $q->where('name', 'like', "%$search%")
                            ->orWhere('code', 'like', "%$search%")
                            ->orWhere('phone', 'like', "%$search%")
                            ->orWhere('npwp', 'like', "%$search%");
                    }

                });
            }

            foreach ($this->personTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            $allowedSort = $this->personTable;

            $sortBy = in_array($request->sort_by, $allowedSort)
                ? $request->sort_by
                : 'id';

            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->per_page ?? 10;

            $data = $query->paginate($perPage);

            return $this->responseSuccess($data, 'driver list retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error While retrieved driver data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'driver list retrieved Failed', 500);
        }
    }

    /**
     * Get driver details.
     */
    public function show(string $id)
    {
        try {
            $person = Person::where('type', 'driver')->where('id', $id)->select($this->personTable)->first();

            if (! $person) {
                return $this->responseError(null, 'driver not found', 404);
            }

            return $this->responseSuccess($person, 'driver retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error While retrieved driver data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'driver retrieved Failed', 500);
        }
    }

    /**
     * Store a new driver.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|integer|exists:companies,id',
            'name' => 'required|string|max:249',
            'address' => 'sometimes|string|max:249',
            'phone' => 'sometimes|string|max:249',
            'npwp' => 'sometimes|string',
            'pic_name' => 'nullable|string',
            'identity_number' => 'nullable|string|max:255',
            'drive_license_identity_number' => 'nullable|string|max:255',
            'image' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
            'map_link' => 'nullable|string',
            'social_media_1_link' => 'nullable|string',
            'social_media_2_link' => 'nullable|string',
            'social_media_3_link' => 'nullable|string',
            'social_media_4_link' => 'nullable|string',
            'website_link' => 'nullable|string',
            'join_date' => 'nullable|date',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $this->storeFile(
                $request->file('image'),
                'person_images'
            );
        }

        try {
            $person = DB::transaction(function () use ($validated) {
                $validated['code'] = $this->generateCode('driver');
                $validated['type'] = 'driver';

                return Person::create($validated);
            });

            return $this->responseSuccess($person, 'driver created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error while trying create Person Data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Error while trying create Person Data', 500);
        }
    }

    /**
     * Update a driver.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'company_id' => 'sometimes|integer|exists:companies,id',
            'name' => 'sometimes|string|max:249',
            'address' => 'sometimes|string|max:249',
            'phone' => 'sometimes|string|max:249',
            'npwp' => 'sometimes|string',
            'pic_name' => 'sometimes|string',
            'identity_number' => 'nullable|string|max:255',
            'drive_license_identity_number' => 'nullable|string|max:255',
            'image' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
            'map_link' => 'nullable|string',
            'social_media_1_link' => 'nullable|string',
            'social_media_2_link' => 'nullable|string',
            'social_media_3_link' => 'nullable|string',
            'social_media_4_link' => 'nullable|string',
            'website_link' => 'nullable|string',
            'join_date' => 'nullable|date',
        ]);

        try {
            $person = Person::findOrFail($id);

            $data = array_filter($request->only([
                'company_id',
                'pic_name',
                'name',
                'address',
                'phone',
                'npwp',
                'identity_number',
                'drive_license_identity_number',
                'map_link',
                'social_media_1_link',
                'social_media_2_link',
                'social_media_3_link',
                'social_media_4_link',
                'website_link',
                'join_date',
            ]), fn ($value) => ! is_null($value) && $value !== '');

            if ($request->hasFile('image')) {
                if ($person->image) {
                    $this->destroyFile($person->image);
                }

                $data['image'] = $this->storeFile(
                    $request->file('image'),
                    'person_images'
                );
            }

            if (empty($data)) {
                return $this->responseError(null, 'No data provided to update', 422);
            }

            $person = DB::transaction(function () use ($person, $data) {
                $person->update($data);

                return $person->fresh();
            });

            return $this->responseSuccess($person, 'driver Update Successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying update Person data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Error while trying update Person data', 500);
        }
    }

    /**
     * Delete a driver.
     */
    public function destroy(string $id)
    {
        try {
            $person = Person::where('type', 'driver')->findOrFail($id);
            
            if ($person->image) {
                $this->destroyFile($person->image);
            }

            $person->delete();

            return $this->responseSuccess([], 'driver Deleted Successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying delete driver data : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'driver Deleted Failed');
        }
    }

    /**
     * Import drivers from Excel.
     */
    public function import(Request $request, string $id)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        try {
            Excel::import(new PersonImport((string) 'driver', (int) $id), $request->file('file'));

            return $this->responseSuccess(null, 'Person driver imported successfully', 201);
        } catch (Exception $err) {
            Log::error('Person driver import error', [
                'message' => $err->getMessage(),
            ]);

            return $this->responseError($err->getMessage(), 'Person driver import error', 500);
        }
    }

    /**
     * Export drivers to Excel.
     */
    public function export(Request $request)
    {
        try {
            return Excel::download(
                new PersonExport($request, $this->personTable, 'driver'),
                'wajira_driver_data.xlsx'
            );  
        } catch (Exception $err) {
            Log::error('Error export driver : '.$err->getMessage());
    
            return $this->responseError(
                $err->getMessage(),
                'Driver export failed',
                500
            );
        }
    }
}
