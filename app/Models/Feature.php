<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feature extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'is_active',
    ];

    public function modules()
    {
        return $this->belongsToMany(Module::class, 'module_has_features', 'feature_id', 'module_id');
    }
}
