<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\DOInvoice;
use App\Models\DOOrderList;
use App\Traits\DOTrait;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class DOInvoiceController extends Controller
{
    use ResponseTrait, DOTrait;

    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only('update');
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);
    }

    public function index(Request $request)
    {
        $query = DOInvoice::with(['customer:id,uuid,name,code', 'order_list:id,uuid,code']);

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
                Rule::exists('persons', 'id')->where(function ($query) {
                    $query->where('type', 'customer');
                }),
            ],
            'date' => 'nullable|date',
            'subject' => 'nullable|string',
            'letter_content' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        try {
            // Check if customer has an existing unprinted invoice
            $existingUnprintedInvoice = DOInvoice::where('customer_id', $validated['customer_id'])
                ->where('is_already_print', false)
                ->exists();
            if ($existingUnprintedInvoice) {
                return $this->responseError('Customer already has an unprinted invoice', 'Validation Error', 422);
            }

            // Get all order lists for the customer
            $orderLists = DOOrderList::where('customer_id', $validated['customer_id'])->get();
            if ($orderLists->isEmpty()) {
                return $this->responseError('Customer does not have any order lists', 'Validation Error', 422);
            }

            $createdInvoices = [];

            foreach ($orderLists as $order) {
                // Check if this specific order list already has an invoice
                $existingInvoice = DOInvoice::where('do_order_list_id', $order->id)->exists();
                if ($existingInvoice) {
                    continue;
                }

                $createdInvoices[] = DOInvoice::create([
                    'code' => $this->generateDOCode('invoice'),
                    'customer_id' => $validated['customer_id'],
                    'do_order_list_id' => $order->id,
                    'date' => $validated['date'] ?? null,
                    'subject' => $validated['subject'] ?? null,
                    'letter_content' => $validated['letter_content'] ?? null,
                    'description' => $validated['description'] ?? null,
                    'is_already_print' => false,
                ]);
            }

            if (empty($createdInvoices)) {
                return $this->responseError('All order lists for this customer already have invoices', 'Conflict', 409);
            }

            return $this->responseSuccess($createdInvoices, count($createdInvoices) . ' DO Invoice(s) created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error creating DO Invoice: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to create DO Invoice');
        }
    }

    public function show($id)
    {
        try {
            $invoice = DOInvoice::with([
                'customer', 
                'order_list.tarifs',
                'order_list.expeditions.vehicle',
                'order_list.expeditions.order_list_tarifs.tarif'
            ])->findOrFail($id);
            return $this->responseSuccess($invoice, 'DO Invoice details retrieved successfully');
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
                Rule::exists('persons', 'id')->where(function ($query) {
                    $query->where('type', 'customer');
                }),
            ],
            'date' => 'sometimes|required|date',
            'subject' => 'sometimes|nullable|string',
            'letter_content' => 'sometimes|nullable|string',
            'description' => 'sometimes|nullable|string',
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
        } catch (Exception $err) {
            Log::error('Error deleting DO Invoice: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to delete DO Invoice');
        }
    }
}
