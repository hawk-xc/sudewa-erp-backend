<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FinanceRefund extends Model
{
    use HasFactory;

    protected $table = 'finance_refunds';

    protected $fillable = [
        'uuid',
        'unit_transaction_refund_id',
        'cash_id',
        'status', // waiting, reject, approve
    ];

    protected $casts = [
        'unit_transaction_refund_id' => 'integer',
        'cash_id' => 'integer'
    ];

    public function unitTransactionRefund()
    {
        return $this->belongsTo(UnitTransactionRefund::class, 'unit_transaction_refund_id', 'id');
    }

    public function financeRefundPayments()
    {
        return $this->hasMany(FinanceRefundPayment::class, 'finance_refund_id', 'id');
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
