<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GoodsTransactionBilling extends Model
{
    use HasFactory;

    protected $table = 'goods_transaction_billings';

    protected $fillable = [
        'uuid',
        'code',
        'goods_transaction_id',
    ];

    protected $casts = [
        'goods_transaction_id' => 'integer',
    ];

    public function goodsTransaction()
    {
        return $this->belongsTo(GoodsTransaction::class);
    }

    public function payments()
    {
        return $this->hasMany(GoodsTransactionBillingPayment::class, 'goods_transaction_billing_id');
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
