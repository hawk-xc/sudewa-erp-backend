<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WarehouseBlock extends Model
{
    use HasFactory;

    protected $table = 'warehouse_blocks';

    protected $fillable = [
        'uuid',
        'warehouse_id',
        'name',
        'description'
    ];

    protected $casts = [
        'warehouse_id' => 'integer'
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'id');
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

    public function warehouseSubBlocks()
    {
        return $this->hasMany(WarehouseSubBlock::class, 'warehouse_block_id', 'id');
    }
}
