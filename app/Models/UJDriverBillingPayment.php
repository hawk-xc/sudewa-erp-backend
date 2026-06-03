<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UJDriverBillingPayment extends Model
{
    use HasFactory;

    protected $table = 'uj_driver_billing_payments';

    protected $fillable = [
        'uuid',
        'do_expedition_id',
        'cash_id',
        'amount'
    ];

    protected $casts = [
        'do_expedition_id' => 'integer',
        'cash_id' => 'integer',
        'amount' => 'integer'
    ];

    public function doExpedition()
    {
        return $this->belongsTo(DOExpedition::class, 'do_expedition_id', 'id');
    }

    public function cash()
    {
        return $this->belongsTo(Cash::class, 'cash_id', 'id');
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
