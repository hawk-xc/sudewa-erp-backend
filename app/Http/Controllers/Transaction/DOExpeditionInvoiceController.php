<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\DOExpedition;
use App\Models\DOExpeditionInvoice;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DOExpeditionInvoiceController extends Controller
{
    use ResponseTrait;
 
    public function __construct()
    {
        $this->middleware(['permission:transaction:list'])->only(['index', 'show', 'processInvoice']);
        $this->middleware(['permission:transaction:create'])->only('store');
        $this->middleware(['permission:transaction:edit'])->only('update');
        $this->middleware(['permission:transaction:delete'])->only(['destroy']);
    }

    /**
     * List all DO Expedition Invoices.
     */
    public function index(Request $request)
    {
        $query = DOExpeditionInvoice::with('doExpedition', 'doExpedition.items:id,uuid,do_expedition_id,loading_in,loading_out,invoice_fee,driver_fee,other_fee,additional_cost_fee,ppn_fee,service_fee,pph_fee', 'doExpedition.items.expeditionDestinations:id,uuid,do_expedition_item_id,destination');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('do_letter_code', 'like', "%$search%")
                    ->orWhere('do_assignment_code', 'like', "%$search%")
                    ->orWhereHas('doExpedition', function ($dq) use ($search) {
                        $dq->where('do_code', 'like', "%$search%");
                    });
            });
        }

        $perPage = $request->per_page ?? 10;
        $data = $query->paginate($perPage);

        return $this->responseSuccess($data, 'DO Expedition Invoice list retrieved successfully');
    }

    /**
     * Store a new DO Expedition Invoice.
     * do_expedition_id is filled by looking up do_code.
     */
    public function store(Request $request)
    {
        $request->validate([
            'do_code' => 'required|exists:do_expeditions,do_code',
        ]);

        try {
            $doExpedition = DOExpedition::where('do_code', $request->do_code)->firstOrFail();

            $data = DOExpeditionInvoice::create([
                'do_expedition_id' => $doExpedition->id,
            ]);

            return $this->responseSuccess($data, 'DO Expedition Invoice created successfully', 201);
        } catch (Exception $err) {
            Log::error('Error while creating DO Expedition Invoice: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to create DO Expedition Invoice', 500);
        }
    }

    /**
     * Get DO Expedition Invoice detail.
     */
    public function show($id)
    {
        try {
            $data = DOExpeditionInvoice::with(['doExpedition', 'doExpedition.items', 'doExpedition.items.expeditionDestinations'])->findOrFail($id);
            return $this->responseSuccess($data, 'DO Expedition Invoice details retrieved successfully');
        } catch (Exception $err) {
            return $this->responseError($err->getMessage(), 'DO Expedition Invoice not found', 404);
        }
    }

    /**
     * Update a DO Expedition Invoice.
     * Only fills qty, do_letter_code, and do_assignment_code.
     */
    public function update(Request $request, int $id)
    {
        $request->validate([
            'qty' => 'sometimes|required|integer|min:1',
            'do_letter_code' => 'sometimes|required|string',
            'do_assignment_code' => 'sometimes|required|string',
            'description' => 'sometimes|string'
        ]);

        try {
            $invoice = DOExpeditionInvoice::findOrFail($id);
            
            $invoice->update($request->only([
                'qty',
                'do_letter_code',
                'do_assignment_code',
                'description'
            ]));

            return $this->responseSuccess($invoice->fresh(), 'DO Expedition Invoice updated successfully');
        } catch (Exception $err) {
            Log::error('Error while updating DO Expedition Invoice: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to update DO Expedition Invoice', 500);
        }
    }

    /**
     * Delete a DO Expedition Invoice.
     */
    public function destroy(int $id)
    {
        try {
            $invoice = DOExpeditionInvoice::findOrFail($id);
            $invoice->delete();
            return $this->responseSuccess(null, 'DO Expedition Invoice deleted successfully');
        } catch (Exception $err) {
            Log::error('Error while deleting DO Expedition Invoice: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to delete DO Expedition Invoice', 500);
        }
    }

    public function processInvoice(Request $request): JsonResponse
    {
        if (is_string($request->do_expedition_invoice_ids)) {
            $request->merge([
                'do_expedition_invoice_ids' => json_decode($request->do_expedition_invoice_ids, true)
            ]);
        }

        $validated = $request->validate([
            'date' => 'required|date',
            'subject' => 'required|string',
            'attachment' => 'required|string',
            'letter_content' => 'required|string',
            'do_expedition_invoice_ids' => 'required|array',
            'do_expedition_invoice_ids.*' => 'integer|distinct|exists:do_expedition_invoices,id',
        ]);

        try {
            $ids = $validated['do_expedition_invoice_ids'];

            $data = DOExpeditionInvoice::with([
                'doExpedition',
                'doExpedition.items:id,uuid,do_expedition_id,loading_in,loading_out,invoice_fee,driver_fee,other_fee,additional_cost_fee,ppn_fee,service_fee,pph_fee',
                'doExpedition.items.expeditionDestinations:id,uuid,do_expedition_item_id,destination'
            ])->findOrFail($ids);

            $response = [
                'date' => $request->date,
                'subject' => $request->subject,
                'attachment' => $request->attachment,
                'letter_content' => $request->letter_content,
                'invoices' => $data
            ];

            return $this->responseSuccess($response, 'DO Expedition Invoice processed successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $err) {
            return $this->responseError($err->getMessage(), 'DO Expedition Invoice not found', 404);
        } catch (Exception $err) {
            Log::error('Error while processing DO Expedition Invoice: ' . $err->getMessage());
            return $this->responseError($err->getMessage(), 'Failed to process DO Expedition Invoice', 500);
        }
    }
}
