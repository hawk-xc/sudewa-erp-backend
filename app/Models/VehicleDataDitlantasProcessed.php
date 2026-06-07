<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleDataDitlantasProcessed extends Model
{
    use HasFactory;

    protected $table = 'vehicle_data_ditlantas_processed';

    protected $fillable = [
        'ditlantas_process_id',
        'vehicle_data_id'
    ];

    protected $casts = [
        'ditlantas_process_id' => 'integer',
        'vehicle_data_id' => 'integer'
    ];
}
