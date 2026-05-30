<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WarehouseMovement extends Model
{
    use HasFactory;

    protected $table = 'warehouse_movements';

    protected $fillable = [
        'uuid',
        'warehouse_activity_id',
        'serial_number',
        'unit_transaction_id',
        'unit_transaction_item_detail_id',
        'goods_transaction_id',
        'goods_transaction_detail_id',
        'status',
    ];

    protected $casts = [
        'warehouse_activity_id' => 'integer',
        'unit_transaction_id' => 'integer',
        'unit_transaction_item_detail_id' => 'integer',
        'goods_transaction_id' => 'integer',
        'goods_transaction_detail_id' => 'integer',
    ];

    public function unitTransaction()
    {
        return $this->belongsTo(UnitTransaction::class, 'unit_transction_id', 'id');
    }

    public function warehouseActivity()
    {
        return $this->belongsTo(WarehouseActivity::class);
    }

    public function unitTransactionItemDetail()
    {
        return $this->belongsTo(UnitTransactionItemDetail::class, 'unit_transaction_item_detail_id', 'id');
    }

    public function goodsTransaction()
    {
        return $this->belongsTo(GoodsTransaction::class, 'goods_transaction_id', 'id');
    }

    public function goodsTransactionItemDetail()
    {
        return $this->belongsTo(GoodsTransactionDetail::class, 'goods_transaction_detail_id', 'id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {

            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }

            if (empty($model->serial_number)) {

                DB::transaction(function () use ($model) {

                    $today = Carbon::now()->format('Ymd');
                    $prefix = 'TRX'.$today;

                    $last = self::where('serial_number', 'like', $prefix.'%')
                        ->lockForUpdate()
                        ->orderBy('serial_number', 'desc')
                        ->first();

                    if ($last) {
                        $lastNumber = (int) substr($last->serial_number, -5);
                        $nextNumber = $lastNumber + 1;
                    } else {
                        $nextNumber = 1;
                    }

                    $model->serial_number = $prefix.str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
                });
            }
        });
    }
}
