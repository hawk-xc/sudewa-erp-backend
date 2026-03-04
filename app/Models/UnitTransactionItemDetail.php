<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UnitTransactionItemDetail extends Model
{
    use HasFactory;

    protected $table = 'unit_transaction_item_details';

    protected $fillable = [
        'uuid',
        'unit_transaction_item_id',
        'color',
        'machine_number',
        'chassis_number',
        'in_stock',
    ];

    protected $casts = [
        'in_stock' => 'bool',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }

    public function unitTransactionItem()
    {
        return $this->belongsTo(UnitTransactionItem::class);
    }
}
