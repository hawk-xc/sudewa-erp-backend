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
        'bill_invoice',
        'ppn'
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'bill_invoice' => 'integer',
        'ppn' => 'integer',
    ];

    protected $appends = ['uj_driver', 'loading_in', 'loading_out'];

    public function getLoadingInAttribute()
    {
        if (!$this->relationLoaded('tarifs')) {
            return null;
        }

        return $this->tarifs->pluck('loading_in')->implode(', ');
    }

    public function getLoadingOutAttribute()
    {
        if (!$this->relationLoaded('tarifs')) {
            return null;
        }

        return $this->tarifs->pluck('loading_out')->implode(', ');
    }

    public function getUjDriverAttribute()
    {
        if (!$this->relationLoaded('expeditions')) {
            return 0;
        }

        $total = 0;
        foreach ($this->expeditions as $expedition) {
            // Load vehicle and tarifs if not loaded
            $expedition->loadMissing(['vehicle', 'order_list_tarifs.tarif']);
            
            $vehicleType = $expedition->vehicle?->type;
            if (!$vehicleType) continue;

            // Get the first tarif linked to this expedition for the uj calculation
            $firstTarif = $expedition->order_list_tarifs->first()?->tarif;
            
            // Fallback for existing data: if no pivot link exists, use the first available tarif from this order
            if (!$firstTarif && $this->relationLoaded('tarifs')) {
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

        return $total;
    }

    public function customer()
    {
        return $this->belongsTo(Person::class, 'customer_id');
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
            'qty',
            'load_content',
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
