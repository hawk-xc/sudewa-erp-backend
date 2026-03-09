<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WarehouseMovement extends Model
{
    use HasFactory;

    protected $table = 'warehouse_movements';

    protected $fillable = [
        'uuid',
        'warehouse_id',
        'unit_transaction_id',
        'unit_transaction_item_detail_id',
        'status',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'id');
    }

    public function unitTransaction()
    {
        return $this->belongsTo(UnitTransaction::class, 'unit_transction_id', 'id');
    }

    public function unitTransactionItemDetail()
    {
        return $this->belongsTo(UnitTransactionItemDetail::class, 'unit_transaction_item_detail_id', 'id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
