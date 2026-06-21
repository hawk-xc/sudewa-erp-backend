<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class CashUnitTransactionBillingHistory extends Pivot
{
    protected $table = 'cash_unit_transaction_billing_history';

    protected $fillable = [
        'cash_id',
        'unit_transaction_billing_history_id',
        'amount',
        'original_amount',
        'exchange_amount',
    ];

    protected $casts = [
        'cash_id' => 'integer',
        'unit_transaction_billing_history_id' => 'integer',
        'amount' => 'integer',
        'original_amount' => 'integer',
        'exchange_amount' => 'integer',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
