<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\FinanceBBNBilling;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class FinanceBBNBillController extends Controller
{
    use ResponseTrait;

    protected array $financeBBNBillingTable;

    public function __construct()
    {
        $this->middleware(['permission:finance:list'])->only(['index', 'show']);
        $this->middleware(['permission:finance:edit'])->only('update');

        $this->financeBBNBillingTable = [
            'id',
            'uuid',
            'bbn_bill_id',
            'cash_id',
            'amount',
            'created_at',
            'updated_at',
        ];
    }

    public function index(Request $request)
    {
        try {
            $query = FinanceBBNBilling::with([
                'bbnBill:id,code,bill_date,paid_date',
                'cash:id,code,type'
            ]);

            $query->select($this->financeBBNBillingTable);

            foreach ($this->financeBBNBillingTable as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('uuid', 'like', "%$search%")
                      ->orWhereHas('bbnBill', function ($subQ) use ($search) {
                          $subQ->where('code', 'like', "%$search%");
                      })
                      ->orWhereHas('cash', function ($subQ) use ($search) {
                          $subQ->where('code', 'like', "%$search%");
                      });
                });
            }

            $sortBy = in_array($request->sort_by, $this->financeBBNBillingTable) ? $request->sort_by : 'id';
            $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);

            $data = $query->paginate($request->per_page ?? 10);

            return $this->responseSuccess($data, 'Finance BBN Billing list retrieved successfully');
        } catch (Exception $err) {
            Log::error('Error retrieving Finance BBN Billing list: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve Finance BBN Billing list', 500);
        }
    }

    public function show(string $id)
    {
        try {
            $item = FinanceBBNBilling::with([
                'bbnBill:id,code,bill_date,paid_date',
                'cash:id,code,type'
            ])
            ->select($this->financeBBNBillingTable)
            ->findOrFail($id);

            return $this->responseSuccess($item, 'Finance BBN Billing retrieved successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error retrieving Finance BBN Billing: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve Finance BBN Billing', 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'bbn_bill_id' => 'sometimes|required|exists:bbn_bills,id',
            'cash_id' => 'sometimes|required|exists:cashes,id',
            'amount' => 'sometimes|required|numeric|min:1',
        ]);

        try {
            $item = DB::transaction(function () use ($id, $validated) {
                $item = FinanceBBNBilling::findOrFail($id);
                $item->update($validated);
                return $item->fresh(['bbnBill', 'cash']);
            });

            return $this->responseSuccess($item, 'Finance BBN Billing updated successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error updating Finance BBN Billing: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to update Finance BBN Billing', 500);
        }
    }
}
