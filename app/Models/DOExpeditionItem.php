<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DOExpeditionItem extends Model
{
    use HasFactory;

    protected $table = 'do_expedition_items';

    protected $fillable = [
        'uuid',
        'do_expedition_id',
        'customer_id',
        'loading_in',
        'loading_out',
        'destination',
        'invoice_fee',
        'additional_cost_fee',
        'other_fee',
        'driver_fee',
        'ppn_fee',
        'service_fee',
        'pph_fee',
    ];

    protected $casts = [
        'do_expedition_id' => 'integer',
        'customer_id' => 'integer',
        'invoice_fee' => 'decimal:2',
        'additional_cost_fee' => 'decimal:2',
        'other_fee' => 'decimal:2',
        'driver_fee' => 'decimal:2',
        'ppn_fee' => 'decimal:2',
        'service_fee' => 'decimal:2',
        'pph_fee' => 'decimal:2',
    ];

    public function expedition()
    {
        return $this->belongsTo(DOExpedition::class, 'do_expedition_id');
    }

    public function customer()
    {
        return $this->belongsTo(Person::class, 'customer_id');
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });

        static::saving(function ($model) {
            $model->calculateFees();
        });
    }

    public function calculateFees()
    {
        if ($this->invoice_fee) {
            $this->ppn_fee = $this->invoice_fee * 0.11;
            $this->service_fee = $this->invoice_fee * 0.04;
            $this->pph_fee = $this->invoice_fee * 0.02;
        }
    }
}
