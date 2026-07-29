<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use App\Traits\GlobalCodeNumberTrait;

class WarehouseActivity extends Model
{
    use HasFactory, GlobalCodeNumberTrait;

    protected $table = 'warehouse_activities';

    protected $fillable = [
        'uuid',
        'person_id',
        'cash_id', // relation with cash
        'warehouse_id',
        'unit_transaction_id',
        'activity_number',
        'activity_type',
        'activity_date',
        'description',
        'state',
    ];

    protected $casts = [
        'person_id' => 'integer',
        'cash_id' => 'integer',
        'warehouse_id' => 'integer',
        'unit_transaction_id' => 'integer',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function warehouseMovements()
    {
        return $this->hasMany(WarehouseMovement::class);
    }

    public function cash()
    {
        return $this->belongsTo(Cash::class, 'cash_id', 'id');
    }

    public function unitTransaction()
    {
        return $this->belongsTo(UnitTransaction::class, 'unit_transaction_id', 'id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }

            if (empty($model->activity_number)) {
                $warehouse = Warehouse::with('company')->find($model->warehouse_id);
                $companySlug = $warehouse && $warehouse->company ? $warehouse->company->slug : 'wjm';
                $feature = $model->activity_type === 'receipt' ? 'penerimaan_unit' : 'pengeluaran_unit';
                $model->activity_number = (new self)->code($companySlug, $feature);
            }
        });
    }
}
