<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithholdingTax extends Model
{
    use HasFactory;

    protected $table = 'withholding_taxes';

    protected $fillable = [
        'source',
        'company_id',
        'cash_id',
        'unit_transaction_id',
        'bbn_bill_id',
        'no_invoice',
        'withholding_number',
        'withholding_age',
        'pph_amount',
        'pph_description',
        'payment_amount',
        'payment_date',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'cash_id' => 'integer',
        'unit_transaction_id' => 'integer',
        'bbn_bill_id' => 'integer',
        'no_invoice' => 'string',
        'withholding_age' => 'integer',
        'pph_amount' => 'integer',
        'payment_amount' => 'integer',
        'payment_date' => 'date',
    ];

    /**
     * Get the company associated with the withholding tax.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the cash associated with the withholding tax.
     */
    public function cash(): BelongsTo
    {
        return $this->belongsTo(Cash::class);
    }

    /**
     * Get the unit transaction associated with the withholding tax.
     */
    public function unitTransaction(): BelongsTo
    {
        return $this->belongsTo(UnitTransaction::class);
    }

    /**
     * Get the BBN bill associated with the withholding tax.
     */
    public function bbnBill(): BelongsTo
    {
        return $this->belongsTo(BBNBill::class);
    }
}
