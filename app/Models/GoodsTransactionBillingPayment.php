<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GoodsTransactionBillingPayment extends Model
{
    use HasFactory;

    protected $table = 'goods_transaction_billing_payments';

    protected $fillable = [
        'uuid',
        'code',
        'goods_transaction_billing_id',
        'cash_id',
        'amount',
        'transaction_date',
        'description',
    ];

    protected $casts = [
        'goods_transaction_billing_id' => 'integer',
        'cash_id' => 'integer',
        'amount' => 'integer',
        'transaction_date' => 'date',
    ];

    public function goodsTransactionBilling()
    {
        return $this->belongsTo(GoodsTransactionBilling::class);
    }

    public function cash()
    {
        return $this->belongsTo(Cash::class);
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
