<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FinanceRefundPayment extends Model
{
    use HasFactory;

    protected $table = 'finance_refund_payments';

    protected $fillable = [
        'uuid',
        'finance_refund_id',
        'refund_nominal',
        'note'
    ];

    protected $casts = [
        'finance_refund_id' => 'integer',
        'refund_nominal' => 'decimal:2'
    ];

    public function financeRefund()
    {
        return $this->belongsTo(FinanceRefund::class, 'finance_refund_id', 'id');
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
