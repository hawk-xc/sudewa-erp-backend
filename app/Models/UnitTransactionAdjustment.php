<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UnitTransactionAdjustment extends Model
{
    use HasFactory;

    protected $table = 'unit_transaction_adjustments';

    protected $fillable = [
        'uuid',
        'unit_transaction_id',
        'cash_id',
        'amount',
        'description',
        'type',
    ];

    protected $casts = [
        'unit_transaction_id' => 'integer',
        'cash_id' => 'integer',
        'amount' => 'integer',
    ];

    public function unitTransaction()
    {
        return $this->belongsTo(UnitTransaction::class);
    }

    public function cash()
    {
        return $this->belongsTo(Cash::class);
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
