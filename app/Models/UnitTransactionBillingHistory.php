<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UnitTransactionBillingHistory extends Model
{
    use HasFactory;

    protected $table = 'unit_transaction_billing_histories';

    protected $fillable = [
        'uuid',
        'unit_transaction_billing_id',
        'foreignId',
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

    public function unitTransactionBilling()
    {
        return $this->belongsTo(UnitTransactionBilling::class, 'unit_transaction_billing_id', 'id');
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
