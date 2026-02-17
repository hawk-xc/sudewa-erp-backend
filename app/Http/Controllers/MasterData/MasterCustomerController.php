<?php

namespace App\Http\Controllers\MasterData;

use Exception;
use App\Models\Person;
use App\Traits\PersonTrait;
use Illuminate\Http\Request;
use App\Traits\ResponseTrait;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Repositories\AuthRepository;

class MasterCustomerController extends Controller
{
    use ResponseTrait, PersonTrait;

     /**
     * @var AuthRepository
     */
    protected AuthRepository $authRepository;

    /**
     * AuthController constructor.
     */
    public function __construct(AuthRepository $ar)
    {
        $this->middleware(['permission:master-data:list'])->only(['index']);
        $this->middleware(['permission:master-data:create'])->only('store');
        // $this->middleware(['permission:master-data:edit'])->only('update', 'activateUser', 'deactivateUser');
        // $this->middleware(['permission:master-data:delete'])->only(['destroy']);
        // $this->middleware(['permission:master-data:create'])->only(['assignRole']);
        // $this->middleware(['permission:master-data:delete'])->only(['revokeRole']);

        $this->authRepository = $ar;
    }

    public function index() 
    {
    }

    public function store(Request $request) 
    {
        $request->validate([
            'name' => 'required|string|max:249',
            'address' => 'sometimes|string|max:249',
            'phone' => 'sometimes|string|max:249',
            'user_id' => 'sometimes|integer|exists:users,id'
        ]);

        try {
            $person = new Person;
            $person->user_id = $request->user_id;
            $person->type = 'customer';
            $person->code = $this->generateCode('customer');
            $person->name = $request->name;
            $person->address = $request->address;
            $person->phone = $request->phone;
            $person->save();

            return $this->responseSuccess($person, 'Customer created successfully');
        } catch (Exception $err) {
            Log::error("Error while trying create Person Data : " . $err->getMessage());

            return $this->responseError(null, "Error while trying create Person Data", 500);
        }
    } 

    public function update(Request $request, string $id) 
    {
        $request->validate([
            'name' => 'required|string|max:249',
            'address' => 'sometimes|string|max:249',
            'phone' => 'sometimes|string|max:249',
            'user_id' => 'sometimes|integer|exists:users,id'
        ]);

        try {
            $person = Person::firstOrFail($id);
            $person->name = $request->name;
            $person->address = $request->address;
            $person->phone = $request->phone;
            $person->user_id = $request->user_id;
            $person->update();

            return $this->responseSuccess($person, "Customer Update Successfully", 200);
        } catch (Exception $err) {
            Log::error("Error while trying update Person data : " . $err->getMessage());

            return $this->responseError(null, "Error while trying update Person data", 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $person = Person::firstOrFail($id);
            $person->delete();

            return $this->responseSuccess([], "Person Deleted Successfully", 200);
        }catch (Exception $err) {
            Log::error("Error while trying delete Person data : " . $err->getMessage());
            return $this->responseError(null, "Person Deleted Failed");
        }
    }
}
