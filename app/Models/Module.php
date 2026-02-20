<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasFactory;

    protected $table = 'modules';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'created_at',
        'updated_at',
    ];

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'company_has_modules');
    }

    public function features()
    {
        return $this->belongsToMany(Feature::class, 'module_has_features');
    }
}
