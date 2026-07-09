<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FinanceBilling extends Model
{
    use HasFactory;

    protected $table = 'finance_billings';

    protected $fillable = [
        'uuid',
        'cash_flow_id',
        'cash_id',
        'account_id',
        'amount',
        'amount_original',
        'payment_proof',
        'payment_at',
        'note',
    ];

    protected $casts = [
        'cash_flow_id' => 'integer',
        'cash_id' => 'integer',
        'account_id' => 'integer',
        'amount' => 'integer',
        'amount_original' => 'integer',
        'payment_at' => 'date',
    ];

    protected $hidden = [
        'cashFlow',
    ];

    public function getUnitTransactionBillingAttribute()
    {
        return $this->cashFlow?->unitTransactionBilling;
    }

    public function getGoodsTransactionBillingAttribute()
    {
        return $this->cashFlow?->goodsTransactionBilling;
    }

    public function getUnitTransactionBillingIdAttribute()
    {
        return $this->cashFlow?->unit_transaction_billing_id;
    }

    public function getGoodsTransactionBillingIdAttribute()
    {
        return $this->cashFlow?->goods_transaction_billing_id;
    }

    public function cashFlow()
    {
        return $this->belongsTo(CashFlow::class);
    }

    public function cash()
    {
        return $this->belongsTo(Cash::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
