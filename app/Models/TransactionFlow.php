<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TransactionFlow extends Model
{
    use HasFactory;

    protected $table = 'transaction_flows';

    protected $fillable = [
        'uuid',
        'company_id',
        'unit_transaction_id',
        'transaction_date',
        'description',
        'bank_usd_debit',
        'bank_usd_credit',
        'bank_idr_debit',
        'bank_idr_credit',
        'cash_idr_debit',
        'cash_idr_credit',
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
