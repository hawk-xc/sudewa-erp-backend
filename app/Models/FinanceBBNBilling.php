<?php

namespace App\Models;

use App\Models\BBNBill;
use App\Models\Cash;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FinanceBBNBilling extends Model
{
    use HasFactory;

    protected $table = 'finance_bbn_billings';

    protected $fillable = [
        'uuid',
        'bbn_bill_id',
        'cash_id',
        'amount',
    ];

    protected $casts = [
        'bbn_bill_id' => 'integer',
        'cash_id' => 'integer',
        'amount' => 'integer',
    ];

    public function cash()
    {
        return $this->belongsTo(Cash::class, 'cash_id', 'id');
    }

    public function bbnBill()
    {
        return $this->belongsTo(BBNBill::class, 'bbn_bill_id', 'id');
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
