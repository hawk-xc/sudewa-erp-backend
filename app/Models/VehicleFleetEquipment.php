<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleFleetEquipment extends Model
{
    use HasFactory;

    protected $table = 'vehicle_fleet_equipments';

    protected $fillable = [
        'vehicle_fleet_id',

        // category 1
        "radio_tape",
        "jack",
        "spare_tire",
        "toolkit",
        "jack_handle",
        "pressure_pipe_1",
        "first_aid_kit",
        "cigarette_lighter",
        "pressure_pipe_2",
  
        //   category 2
        "seat_saddle",
        "handlebar_hose",
        "fire_extinguisher",
        "large_tie_down_strap",
        "rearview_mirror",
        "ati_foam",
        "small_tie_down_strap",
        "toolbox_lock",
        "service_book"
    ];

    protected $casts = [
        "vehicle_fleet_id" => "integer",

        // category 1
        "radio_tape" => "integer",
        "jack" => "integer",
        "spare_tire" => "integer",
        "toolkit" => "integer",
        "jack_handle" => "integer",
        "pressure_pipe_1" => "integer",
        "first_aid_kit" => "integer",
        "cigarette_lighter" => "integer",
        "pressure_pipe_2" => "integer",
  
        //   category 2
        "seat_saddle" => "integer",
        "handlebar_hose" => "integer",
        "fire_extinguisher" => "integer",
        "large_tie_down_strap" => "integer",
        "rearview_mirror" => "integer",
        "ati_foam" => "integer",
        "small_tie_down_strap" => "integer",
        "toolbox_lock" => "integer",
        "service_book" => "integer"
    ];
    
    public function vehicleFleet()
    {
        return $this->belongsTo(VehicleFleet::class);
    }
}
