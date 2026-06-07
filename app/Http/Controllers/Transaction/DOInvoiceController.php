<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\DOExpedition;
use App\Models\DOInvoice;
use App\Models\DOOrderList;
use App\Models\Person;
use App\Rules\RightPersonRule;
use App\Traits\GlobalCodeNumberTrait;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class DOInvoiceController extends Controller
{
    use ResponseTrait, GlobalCodeNumberTrait;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only(['update', 'processInvoice', 'processExpedition']);
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);
    }

    public function index(Request $request)
    {
        $query = DOInvoice::with(['customer:id,uuid,name,code', 'order_list:id,uuid,code', 'financeBillingPayment:id,uuid,do_invoice_id,cash_id,amount']);

        try {
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where('code', 'like', "%$search%")
                    ->orWhere('subject', 'like', "%$search%")
                    ->orWhereHas('customer', function ($q) use ($search) {
                        $q->where('name', 'like', "%$search%");
                    });
            }

            if ($request->filled('is_already_print')) {
                $query->where('is_already_print', $request->boolean('is_already_print'));
            }

            $data = $query->latest()->paginate($request->per_page ?? 10);
            
            // Hide appended attributes from nested order_list to keep response short
            $data->getCollection()->each(function ($invoice) {
                if ($invoice->order_list) {
                    $invoice->order_list->makeHidden(['uj_driver', 'loading_in', 'loading_out']);
                }
            });

            return $this->responseSuccess($data, 'DO Invoice list retrieved successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error retrieving DO Invoice: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to retrieve DO Invoice');
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => [
                'required',
                new RightPersonRule('customer'),
            ],
            'date' => 'nullable|date',
            'subject' => 'nullable|string',
            'letter_content' => 'nullable|string',
            'description' => 'nullable|string',
            'other_fee' => 'nullable|integer',
            'additional_fee' => 'nullable|integer',
        ]);

        try {
            $existingUnprintedInvoice = DOInvoice::where('customer_id', $validated['customer_id'])
                ->where('is_already_print', false)
                ->exists();
            if ($existingUnprintedInvoice) {
                return $this->responseError('Customer already has an unprinted invoice', 'Validation Error', 422);
            }

            $orderLists = DOOrderList::where('customer_id', $validated['customer_id'])->get();
            if ($orderLists->isEmpty()) {
                return $this->responseError('Customer does not have any order lists', 'Validation Error', 422);
            }

            $createdInvoices = DB::transaction(function () use ($validated, $orderLists) {
                $customer = Person::findOrFail((int) $validated['customer_id']);
                $companySlug = $customer?->company?->slug ?? '';
                
                $invoices = [];
                foreach ($orderLists as $order) {
                    $existingInvoice = DOInvoice::where('do_order_list_id', $order->id)->exists();
                    if ($existingInvoice) {
                        continue;
                    }

                    $invoices[] = DOInvoice::create([
                        'code' => $this->code($companySlug, 'invoice'),
                        'customer_id' => $validated['customer_id'],
                        'do_order_list_id' => $order->id,
                        'date' => $validated['date'] ?? null,
                        'subject' => $validated['subject'] ?? null,
                        'letter_content' => $validated['letter_content'] ?? null,
                        'description' => $validated['description'] ?? null,
                        'other_fee' => $validated['other_fee'] ?? 0,
                        'additional_fee' => $validated['additional_fee'] ?? 0,
                        'is_already_print' => false,
                    ]);
                }
                return $invoices;
            });

            if (empty($createdInvoices)) {
                return $this->responseError('All order lists for this customer already have invoices', 'Conflict', 409);
            }

            return $this->responseSuccess($createdInvoices, count($createdInvoices) . ' DO Invoice(s) created successfully', 201);
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error creating DO Invoice: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to create DO Invoice');
        }
    }

    public function show(string $id)
    {
        try {
            $invoice = DOInvoice::with([
                'customer', 
                'order_list.tarifs',
                'order_list.expeditions.vehicle:id,uuid,registration_number,type,machine_number,chassis_number',
                'order_list.expeditions.driver:id,uuid,name',
                'order_list.expeditions.order_list_tarifs.tarif',
                'financeBillingPayment'
            ])->findOrFail($id);
            return $this->responseSuccess($invoice, 'DO Invoice details retrieved successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            return $this->responseError('DO Invoice not found', 'Not Found', 404);
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'customer_id' => [
                'sometimes',
                'required',
                new RightPersonRule('customer'),
            ],
            'date' => 'sometimes|required|date',
            'subject' => 'sometimes|nullable|string',
            'letter_content' => 'sometimes|nullable|string',
            'description' => 'sometimes|nullable|string',
            'other_fee' => 'sometimes|nullable|integer',
            'additional_fee' => 'sometimes|nullable|integer',
            'is_already_print' => 'sometimes|boolean',
        ]);

        try {
            if (isset($validated['customer_id'])) {
                $hasOrderList = DOOrderList::where('customer_id', $validated['customer_id'])->exists();
                if (!$hasOrderList) {
                    return $this->responseError('Customer does not have any order lists', 'Validation Error', 422);
                }

                // Check if customer has an existing unprinted invoice (excluding current invoice)
                $existingUnprintedInvoice = DOInvoice::where('customer_id', $validated['customer_id'])
                    ->where('is_already_print', false)
                    ->where('id', '!=', $id)
                    ->exists();
                if ($existingUnprintedInvoice) {
                    return $this->responseError('Customer already has an unprinted invoice', 'Validation Error', 422);
                }
            }

            $invoice = DOInvoice::findOrFail($id);
            $invoice->update($validated);
            return $this->responseSuccess($invoice, 'DO Invoice updated successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error updating DO Invoice: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to update DO Invoice');
        }
    }

    public function destroy($id)
    {
        try {
            $invoice = DOInvoice::findOrFail($id);
            $invoice->delete();
            return $this->responseSuccess([], 'DO Invoice deleted successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error deleting DO Invoice: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to delete DO Invoice');
        }
    }

    public function processInvoice(Request $request, $id = null)
    {
        $invoiceId = $id ?? $request->id;

        if (!$invoiceId) {
            return $this->responseError('Invoice ID is required', 'Validation Error', 422);
        }

        try {
            $invoice = DOInvoice::with([
                'customer', 
                'order_list.tarifs',
                'order_list.expeditions.vehicle:id,uuid,registration_number,type,machine_number,chassis_number',
                'order_list.expeditions.driver:id,uuid,name',
                'order_list.expeditions.order_list_tarifs.tarif'
            ])->findOrFail($invoiceId);

            $invoice->update(['is_already_print' => true]);

            return $this->responseSuccess($invoice, 'DO Invoice processed and printed successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error processing DO Invoice: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to process DO Invoice');
        }
    }

    public function processExpedition(Request $request, $id = null)
    {
        $expeditionId = $id ?? $request->id;

        if (!$expeditionId) {
            return $this->responseError('Expedition ID is required', 'Validation Error', 422);
        }

        try {
            $expedition = DOExpedition::with([
                'vehicle', 
                'driver', 
                'order_list.customer', 
                'order_list.tarifs'
            ])->findOrFail($expeditionId);

            $expedition->update(['is_printed' => true]);

            return $this->responseSuccess($expedition, 'DO Expedition processed and printed successfully');
        } catch (ModelNotFoundException $err) {
            $model = class_basename($err->getModel() ?: 'Data');
            $friendlyModel = trim(preg_replace('/(?<!^)(?<![A-Z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $model));
            return $this->responseError(null, $friendlyModel . ' not found', 404);
        } catch (Exception $err) {
            Log::error('Error processing DO Expedition: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to process DO Expedition');
        }
    }
}
