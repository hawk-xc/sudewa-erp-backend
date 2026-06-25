<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinanceBillingItem extends Model
{
    use HasFactory;

    protected $table = 'finance_billing_items';

    protected $fillable = [
        'finance_billing_id',
        'cash_id',
        'account_id',
        'amount',
        'amount_original',
        'payment_proof',
        'payment_at',
        'note',
    ];

    protected $casts = [
        'finance_billing_id' => 'integer',
        'cash_id' => 'integer',
        'account_id' => 'integer',
        'amount' => 'float',
        'amount_original' => 'float',
        'payment_at' => 'date',
    ];

    public function financeBilling()
    {
        return $this->belongsTo(FinanceBilling::class);
    }

    public function cash()
    {
        return $this->belongsTo(Cash::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
