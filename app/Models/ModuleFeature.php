<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModuleFeature extends Model
{
    use HasFactory;

    protected $table = 'module_features';

    protected $fillable = [
        'module_id',
        'feature_id',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function feature()
    {
        return $this->belongsTo(Feature::class);
    }
}
