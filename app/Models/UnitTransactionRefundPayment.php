<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UnitTransactionRefundPayment extends Model
{
    use HasFactory;

    protected $table = 'unit_transaction_refund_payments';

    protected $fillable = [
        'uuid',
        'unit_transaction_refund_id',
        'code', 
        'amount',
        'payment_date',
    ];
    
    protected $casts = [
        'unit_transaction_refund_id' => 'integer',
        'payment_date' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function unitTransactionRefund()
    {
        return $this->belongsTo(UnitTransactionRefund::class, 'unit_transaction_refund_id', 'id');
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
