<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DOExpeditionItemDestination extends Model
{
    use HasFactory;

    protected $table = 'do_expedition_item_destinations';

    protected $fillable = [
        'uuid',
        'do_expedition_item_id',
        'destination',
        'order_number',
        'driver_note',
        'maps_url'
    ];

    protected $casts = [
        'do_expedition_item_id' => 'integer',
        'order_number' => 'integer',
    ];

    public function expeditionItem()
    {
        return $this->belongsTo(DOExpeditionItem::class, 'do_expedition_item_id');
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
