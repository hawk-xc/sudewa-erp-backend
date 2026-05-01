<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DOExpedition extends Model
{
    use HasFactory;

    protected $table = 'do_expeditions';

    protected $fillable = [
        'uuid',
        'do_code',
        'date',
        'vehicle_id',
        'driver_id',
    ];

    protected $casts = [
        'date' => 'date',
        'vehicle_id' => 'integer',
        'driver_id' => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(DOExpeditionItem::class, 'do_expedition_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(VehicleFleet::class, 'vehicle_id');
    }

    public function driver()
    {
        return $this->belongsTo(Person::class, 'driver_id');
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
