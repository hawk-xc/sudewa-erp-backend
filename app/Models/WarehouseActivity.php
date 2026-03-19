<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WarehouseActivity extends Model
{
    use HasFactory;

    protected $table = 'warehouse_activities';

    protected $fillable = [
        'uuid',
        'person_id',
        'warehouse_id',
        'activity_number',
        'activity_type',
        'activity_date',
        'description',
    ];

    protected $casts = [
        'person_id' => 'int',
        'warehouse_id' => 'int',
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

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }

            if (empty($model->activity_number)) {
                DB::transaction(function () use ($model) {

                    $today = Carbon::now()->format('Ymd');
                    $prefix = 'TMU'.$today;

                    $last = self::where('activity_number', 'like', $prefix.'%')
                        ->lockForUpdate()
                        ->orderBy('activity_number', 'desc')
                        ->first();

                    if ($last) {
                        $lastNumber = (int) substr($last->activity_number, -5);
                        $nextNumber = $lastNumber + 1;
                    } else {
                        $nextNumber = 1;
                    }

                    $model->activity_number = $prefix.str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
                });
            }
        });
    }
}
