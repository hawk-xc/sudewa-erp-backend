<?php

namespace App\Http\Controllers\Transaction;

use App\Exports\DOExpeditionExport;
use App\Http\Controllers\Controller;
use App\Models\DOExpedition;
use App\Traits\DOExpeditionTrait;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class DOExpeditionController extends Controller
{
    use ResponseTrait, DOExpeditionTrait;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show', 'export']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only('update');
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);
    }

    public function index(Request $request)
    {
        $query = DOExpedition::with(['vehicle:id,uuid,registration_number', 'driver:id,uuid,name'])
            ->withSum('items as brutto_value', 'invoice_fee')
            ->withSum('items as total_ppn', 'ppn_fee')
            ->withSum('items as total_pph', 'pph_fee')
            ->withSum('items as total_service_fee', 'service_fee')
            ->withSum('items as total_additional_cost', 'additional_cost_fee')
            ->withSum('items as total_other_fee', 'other_fee')
            ->withSum('items as total_driver_fee', 'driver_fee');

        try {
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where('do_code', 'like', "%$search%");
            }

            $data = $query->orderBy('id', 'desc')->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'DO Expedition list retrieved successfully');
        } catch (Exception $err) {
            Log::error('Error retrieving DO Expedition: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve DO Expedition');
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'vehicle_id' => 'required|exists:vehicle_fleets,id',
            'driver_id' => [
                'required',
                Rule::exists('persons', 'id')->where(function ($query) {
                    $query->where('type', 'driver');
                }),
            ],
        ]);

        try {
            $doExpedition = DB::transaction(function () use ($validated) {
                $validated['do_code'] = $this->generateDOCode();
                return DOExpedition::create($validated);
            });

            return $this->responseSuccess($doExpedition, 'DO Expedition created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error creating DO Expedition: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to create DO Expedition');
        }
    }

    public function show($id)
    {
        try {
            $doExpedition = DOExpedition::with(['vehicle', 'driver', 'items.customer', 'items.expeditionDestinations'])
                ->withCount('items')
                ->withSum('items as brutto_value', 'invoice_fee')
                ->withSum('items as total_ppn', 'ppn_fee')
                ->withSum('items as total_pph', 'pph_fee')
                ->withSum('items as total_service_fee', 'service_fee')
                ->withSum('items as total_additional_cost', 'additional_cost_fee')
                ->withSum('items as total_other_fee', 'other_fee')
                ->withSum('items as total_driver_fee', 'driver_fee')
                ->findOrFail($id);
            return $this->responseSuccess($doExpedition, 'DO Expedition retrieved successfully');
        } catch (Exception $err) {
            return $this->responseError('DO Expedition not found', 'Not Found', 404);
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'date' => 'sometimes|required|date',
            'vehicle_id' => 'sometimes|required|exists:vehicle_fleets,id',
            'driver_id' => [
                'sometimes',
                'required',
                Rule::exists('persons', 'id')->where(function ($query) {
                    $query->where('type', 'driver');
                }),
            ],
        ]);

        try {
            $doExpedition = DOExpedition::findOrFail($id);
            $doExpedition->update($validated);
            return $this->responseSuccess($doExpedition, 'DO Expedition updated successfully');
        } catch (Exception $err) {
            Log::error('Error updating DO Expedition: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to update DO Expedition');
        }
    }

    public function destroy($id)
    {
        try {
            $doExpedition = DOExpedition::findOrFail($id);
            $doExpedition->delete();
            return $this->responseSuccess([], 'DO Expedition deleted successfully');
        } catch (Exception $err) {
            Log::error('Error deleting DO Expedition: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to delete DO Expedition');
        }
    }

    public function export(Request $request)
    {
        try {
            return Excel::download(new DOExpeditionExport($request), 'do_expedition_data.xlsx');
        } catch (Exception $err) {
            Log::error('Error exporting DO Expedition: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to export DO Expedition');
        }
    }
}
