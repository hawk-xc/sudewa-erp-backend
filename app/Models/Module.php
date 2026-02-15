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
    ];

    public function features()
    {
        return $this->belongsToMany(Feature::class, 'module_features');
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'company_modules');
    }
}
