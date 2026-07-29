<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WarehouseSubBlock extends Model
{
    use HasFactory;

    protected $table = 'warehouse_sub_blocks';

    protected $fillable = [
        'uuid',
        'warehouse_block_id',
        'name',
        'description',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'warehouse_block_id' => 'integer',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
    
    public function warehouseBlock()
    {
        return $this->belongsTo(WarehouseBlock::class, 'warehouse_block_id', 'id');
    }

    public function unitTransactionItemDetails()
    {
        return $this->hasMany(UnitTransactionItemDetail::class, 'warehouse_sub_block_id', 'id');
    }

    public function makeDefault()
    {
        $warehouseBlock = WarehouseSubBlock::where('is_default', 1)->where('warehouse_block_id', $this->warehouse_block_id)->first();
        if ($warehouseBlock) {
            $warehouseBlock->update([
                'is_default' => false,
            ]);
        }

        $this->update([
            'is_default' => true,
        ]);
    }
}
