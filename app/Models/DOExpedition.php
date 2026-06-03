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
        'code',
        'do_order_list_id',
        'vehicle_id',
        'driver_id',
        'date',
        'driver_note',
        'is_printed',
    ];

    protected $casts = [
        'do_order_list_id' => 'integer',
        'date' => 'date',
        'vehicle_id' => 'integer',
        'driver_id' => 'integer',
        'is_printed' => 'boolean',
    ];

    public function order_list()
    {
        return $this->belongsTo(DOOrderList::class, 'do_order_list_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(VehicleFleet::class, 'vehicle_id');
    }

    public function driver()
    {
        return $this->belongsTo(Person::class, 'driver_id');
    }

    public function order_list_tarifs()
    {
        return $this->belongsToMany(
            DOOrderListTarif::class,
            'do_expedition_order_list_tarifs',
            'do_expedition_id',
            'do_order_list_tarif_id'
        )->withTimestamps();
    }

    public function uj_driver_billing_payment()
    {
        return $this->hasOne(UJDriverBillingPayment::class, 'do_expedition_id', 'id');
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
