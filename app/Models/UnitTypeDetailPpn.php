<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UnitTypeDetailPpn extends Model
{
    use HasFactory;

    protected $table = 'unit_type_detail_ppns';

    protected $fillable = [
        'uuid',
        'unit_transaction_item_detail_id',
        'unit_transaction_id',
        'type',
        'fp_date',
        'nsfp_age',
        'nsfp_amount',
        'nsfp_number',
        'amount',
    ];

    protected $casts = [
        'unit_transaction_id' => 'integer',
        'unit_transaction_item_detail_id' => 'integer',
        'nsfp_amount' => 'integer',
        'amount' => 'integer',
        'nsfp_age' => 'date',
        'fp_age' => 'date',
    ];

    public function unitTransactionItemDetails()
    {
        return $this->belongsTo(UnitTransactionItemDetail::class, 'unit_transaction_item_detail_id', 'id');
    }

    public function unitTransaction()
    {
        return $this->belongsTo(UnitTransaction::class, 'unit_transaction_id', 'id');
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
