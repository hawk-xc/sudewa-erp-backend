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
        'bca_payment_amount',
        'bca_payment_usd_amount',
        'cash_payment_amount',
        'payment_proof',
        'payment_at',
        'note',
    ];

    protected $casts = [
        'bca_payment_amount' => 'integer',
        'bca_payment_usd_amount' => 'integer',
        'cash_payment_amount' => 'integer',
        'payment_at' => 'date',
    ];

    public function financeBilling()
    {
        return $this->belongsTo(FinanceBilling::class);
    }
}
