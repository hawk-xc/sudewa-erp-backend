<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UnitTransaction extends Model
{
    use HasFactory;

    protected $table = 'unit_transactions';

    protected $fillble = [
        'uuid',
        'warehouse_id',
        'person_id',
        'code',
        'type',
        'max_capacity',
        'stock_state',
    ];

    protected $casts = [
        'max_capacity' => 'decimal:2',
        'stock_state' => 'string',
    ];

    public function transactionFlow()
    {
        return $this->hasOne(TransactionFlow::class);
    }

    public function unitTransactionBilling()
    {
        return $this->hasOne(UnitTransactionBilling::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
