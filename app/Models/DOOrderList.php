<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DOOrderList extends Model
{
    use HasFactory;

    protected $table = 'do_order_lists';

    protected $fillable = [
        'uuid',
        'code',
        'customer_id',
        'status', // deliver, process, pending, reject
        'vehicle_type', // fuso, cdd, towing
        'bill_invoice',
        'ppn'
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'bill_invoice' => 'integer',
        'ppn' => 'integer',
    ];

    protected $appends = ['uj_driver', 'loading_in', 'loading_out', 'do_delivery_destination'];

    public function getDoDeliveryDestinationAttribute()
    {
        return $this->do_order_list_tarifs->pluck('delivery_destination')->filter()->unique()->implode(', ') ?: null;
    }

    public function getLoadingInAttribute()
    {
        return $this->tarifs->pluck('loading_in')->implode(', ') ?: null;
    }

    public function getLoadingOutAttribute()
    {
        return $this->tarifs->pluck('loading_out')->implode(', ') ?: null;
    }

    public function getUjDriverAttribute()
    {
        $total = 0;

        if ($this->relationLoaded('expeditions') && $this->expeditions->isNotEmpty()) {
            foreach ($this->expeditions as $expedition) {
                $expedition->loadMissing(['vehicle', 'order_list_tarifs.tarif']);
                
                $vehicleType = $expedition->vehicle?->type;
                if (!$vehicleType) continue;

                $firstTarif = $expedition->order_list_tarifs->first()?->tarif;
                
                if (!$firstTarif) {
                    $firstTarif = $this->tarifs->first();
                }

                if (!$firstTarif) continue;

                $normalizedType = strtolower($vehicleType);
                $matchedType = null;
                if (str_contains($normalizedType, 'towing') || str_contains($normalizedType, 'trailer')) {
                    $matchedType = 'towing';
                } elseif (str_contains($normalizedType, 'cdd')) {
                    $matchedType = 'cdd';
                } elseif (str_contains($normalizedType, 'fuso')) {
                    $matchedType = 'fuso';
                }

                if ($matchedType) {
                    $total += match ($matchedType) {
                        'towing' => $firstTarif->uj_towing ?? 0,
                        'cdd'    => $firstTarif->uj_cdd ?? 0,
                        'fuso'   => $firstTarif->uj_fuso ?? 0,
                        default  => 0,
                    };
                }
            }
        }

        if ($total === 0) {
            $vehicleType = $this->vehicle_type;
            if ($vehicleType) {
                $normalizedType = strtolower($vehicleType);
                $matchedType = null;
                if (str_contains($normalizedType, 'towing') || str_contains($normalizedType, 'trailer')) {
                    $matchedType = 'towing';
                } elseif (str_contains($normalizedType, 'cdd')) {
                    $matchedType = 'cdd';
                } elseif (str_contains($normalizedType, 'fuso')) {
                    $matchedType = 'fuso';
                }

                if ($matchedType) {
                    foreach ($this->tarifs as $tarif) {
                        $total += match ($matchedType) {
                            'towing' => $tarif->uj_towing ?? 0,
                            'cdd'    => $tarif->uj_cdd ?? 0,
                            'fuso'   => $tarif->uj_fuso ?? 0,
                            default  => 0,
                        };
                    }
                }
            }
        }

        return $total;
    }

    public function customer()
    {
        return $this->belongsTo(Person::class, 'customer_id', 'id');
    }

    public function expeditions()
    {
        return $this->hasMany(DOExpedition::class, 'do_order_list_id');
    }

    public function do_order_list_tarifs()
    {
        return $this->hasMany(DOOrderListTarif::class, 'do_orderlist_id');
    }

    public function tarifs()
    {
        return $this->belongsToMany(
            Tarif::class,
            'do_order_list_tarifs',
            'do_orderlist_id',
            'tarif_id'
        )->withPivot([
            'uuid',
            'delivery_destination'
        ])->withTimestamps();
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
