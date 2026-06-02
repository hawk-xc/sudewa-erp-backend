<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class CashUnitTransactionBillingHistory extends Pivot
{
    protected $table = 'cash_unit_transaction_billing_history';

    protected $casts = [
        'amount' => 'integer',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
