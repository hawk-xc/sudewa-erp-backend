<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Warehouse extends Model
{
    use HasFactory;

    protected $table = 'warehouses';

    protected $fillable = [
        'uuid',
        'company_id',
        'name',
        'capacity',
        'description',
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

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function unitTransactions()
    {
        return $this->hasMany(UnitTransaction::class);
    }

    public function scopeGetWarehouseCapacityUsage()
    {
        return $this->unitTransactions()->sum('max_capacity');
    }

    public function warehouseMovements()
    {
        return $this->hasMany(WarehouseMovement::class, 'warehouse_id', 'id');
    }
}
