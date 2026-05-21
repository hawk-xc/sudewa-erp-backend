<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UnitTransactionRefund extends Model
{
    use HasFactory;

    protected $table = 'unit_transaction_refunds';

    protected $fillable = [
        'uuid',
        'unit_transaction_id',
        'code',
        'qty',
        'refund_date',
        'refund_amount',
        'note'
    ];

    protected $casts = [
        'refund_date' => 'datetime',
        'refund_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function unitTransaction()
    {
        return $this->belongsTo(UnitTransaction::class, 'unit_transaction_id', 'id');
    }

    public function unitTransactionRefundPayments()
    {
        return $this->hasMany(UnitTransactionRefundPayment::class, 'unit_transaction_refund_id', 'id');
    }

    public function financeRefund()
    {
        return $this->hasOne(FinanceRefund::class, 'unit_transaction_refund_id', 'id');
    }

    public function unitTransactionItemDetails()
    {
        return $this->belongsToMany(
            UnitTransactionItemDetail::class,
            'unit_transaction_refund_item_detail',
            'unit_transaction_refund_id',
            'unit_transaction_item_detail_id'
        );
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
