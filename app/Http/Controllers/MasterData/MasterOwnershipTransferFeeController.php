<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\OwnershipTransferFee;
use App\Models\Person;
use App\Repositories\AuthRepository;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * @group Master Data
 *
 * API for managing ownership transfer fees (BBN).
 */
class MasterOwnershipTransferFeeController extends Controller
{
    use ResponseTrait;

    protected $OwnershipTransferFeeTable;

    protected AuthRepository $authRepository;

    public function __construct(AuthRepository $ar)
    {
        $this->middleware(['permission:master-data:list'])->only(['index', 'show', 'export']);
        $this->middleware(['permission:master-data:create'])->only('store', 'import');
        $this->middleware(['permission:master-data:edit'])->only('update');
        $this->middleware(['permission:master-data:delete'])->only(['destroy']);

        $this->authRepository = $ar;

        $this->OwnershipTransferFeeTable = ['id', 'uuid', 'dealer_id', 'region_id', 'tnbk_code', 'vehicle_type', 'un_notice_fee', 'garwil_fee', 'countershop_fee', 'other_fee', 'created_at'];
    }

    /**
     * List all ownership transfer fees.
     */
    public function index(Request $request)
    {
        try {
            $query = OwnershipTransferFee::query();

            $query->with(['region:id,uuid,code,name', 'dealer:id,uuid,code,name']);

            $query->select($this->OwnershipTransferFeeTable);

            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%");
                });
            }

            $OwnershipTransferFees = $request->filled('per_page') ? $query->paginate($request->per_page) : $query->get();

            return $this->responseSuccess($OwnershipTransferFees, 'OwnershipTransferFees retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying get OwnershipTransferFees : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Internal Server Error', 500);
        }
    }

    /**
     * Get ownership transfer fee details.
     */
    public function show($id)
    {
        try {
            $OwnershipTransferFee = OwnershipTransferFee::with(['region', 'dealer'])->findOrFail($id);

            return $this->responseSuccess($OwnershipTransferFee, 'OwnershipTransferFee retrieved successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying get OwnershipTransferFee : '.$err->getMessage());

            return $this->responseError('The requested resource could not be found.', 'Resource Not Found', 404);
        }
    }

    /**
     * Store a new ownership transfer fee.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'dealer_id' => 'required|exists:persons,id',
            'region_id' => 'required|exists:regions,id',
            'tnbk_code' => 'required|string',
            'vehicle_type' => 'required|string',
            'un_notice_fee' => 'required|string',
            'garwil_fee' => 'required|string',
            'countershop_fee' => 'required|string',
            'other_fee' => 'required|string',
        ]);

        try {
            $dealer = Person::findOrFail($validated['dealer_id']);

            if ($dealer->type !== 'dealer') {
                throw ValidationException::withMessages([
                    'person_id' => 'For bbn data, person must be a dealer.',
                ]);
            }

            $OwnershipTransferFee = DB::transaction(function () use ($validated) {
                $OwnershipTransferFee = OwnershipTransferFee::create($validated);

                return $OwnershipTransferFee;
            });

            return $this->responseSuccess($OwnershipTransferFee, 'OwnershipTransferFee created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error while trying create OwnershipTransferFee : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Failed to create OwnershipTransferFee', 500);
        }
    }

    /**
     * Update an ownership transfer fee.
     */
    public function update(Request $request, $id)
    {
        $OwnershipTransferFee = OwnershipTransferFee::findOrFail($id);

        $validated = $request->validate([
            'dealer_id' => 'sometimes|exists:persons,id',
            'region_id' => 'sometimes|exists:regions,id',
            'tnbk_code' => 'sometimes|string',
            'vehicle_type' => 'sometimes|string',
            'un_notice_fee' => 'sometimes|string',
            'garwil_fee' => 'sometimes|string',
            'countershop_fee' => 'sometimes|string',
            'other_fee' => 'sometimes|string',
        ]);

        try {
            $OwnershipTransferFee->update($validated);

            return $this->responseSuccess($OwnershipTransferFee, 'OwnershipTransferFee updated successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying update OwnershipTransferFee : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Internal Server Error', 500);
        }
    }

    /**
     * Delete an ownership transfer fee.
     */
    public function destroy($id)
    {
        try {
            $OwnershipTransferFee = OwnershipTransferFee::findOrFail($id);
            $OwnershipTransferFee->delete();

            return $this->responseSuccess(null, 'OwnershipTransferFee deleted successfully', 200);
        } catch (Exception $err) {
            Log::error('Error while trying delete OwnershipTransferFee : '.$err->getMessage());

            return $this->responseError($err->getMessage(), 'Internal Server Error', 500);
        }
    }
}
