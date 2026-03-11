<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UnitTransactionBilling extends Model
{
    use HasFactory;

    protected $table = 'unit_transaction_billings';

    protected $fillable = [
        'uuid',
        'unit_transaction_id',
        'bca_payment_amount',
        'bca_payment_usd_amount',
        'cash_payment_amount',
        'bca_payment_liability',
        'bca_payment_usd_liability',
        'cash_payment_liability',
        'payment_at',
        'is_paid',
    ];

    protected $casts = [
        'payment_at' => 'date',
        'bca_payment_amount' => 'decimal:2',
        'bca_payment_usd_amount' => 'decimal:2',
        'cash_payment_amount' => 'decimal:2',
        'bca_payment_liability' => 'decimal:2',
        'bca_payment_usd_liability' => 'decimal:2',
        'cash_payment_liability' => 'decimal:2',
        'is_paid' => 'boolean',
    ];

    public function unitTransaction()
    {
        return $this->belongsTo(UnitTransaction::class);
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
