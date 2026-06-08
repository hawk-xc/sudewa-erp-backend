<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VehicleDocument extends Model
{
    use HasFactory;

    protected $table = 'vehicle_documents';

    protected $fillable = [
        'uuid',
        'code',
        'ditlantas_process_id',
        'receipt_date',
        'description'
    ];

    protected $casts = [
        'receipt_date' => 'date',
    ];

    public function vendor()
    {
        return $this->belongsTo(Person::class);
    }

    public function ditlantasProcess()
    {
        return $this->belongsTo(DitlantasProcess::class, 'ditlantas_process_id', 'id');
    }

    public function vehicleRegistrations()
    {
        return $this->hasMany(VehicleRegistration::class, 'ditlantas_process_id', 'ditlantas_process_id');
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
